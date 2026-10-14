package kafka

import (
	"context"
	"encoding/json"
	"fmt"
	"log/slog"
	"strings"
	"time"

	"analytic_service/internal/store"

	"github.com/segmentio/kafka-go"
	"golang.org/x/sync/errgroup"
)

// EventStore is the ClickHouse write/lookup surface used by Kafka handlers.
type EventStore interface {
	InsertUserRegistration(ctx context.Context, row store.UserRegistration) error
	InsertOrderFunnelEvent(ctx context.Context, row store.OrderFunnelEvent) error
	InsertOrderLineItems(ctx context.Context, rows []store.OrderLineItem) error
	InsertProductDim(ctx context.Context, row store.ProductDim) error
	LookupProductDim(ctx context.Context, productID uint64) (store.ProductDim, bool, error)
	InsertSearchQuery(ctx context.Context, row store.SearchQuery) error
}

type Consumers struct {
	brokers []string
	store   EventStore
	log     *slog.Logger
}

func NewConsumers(brokers []string, st EventStore, log *slog.Logger) *Consumers {
	if log == nil {
		log = slog.Default()
	}
	return &Consumers{brokers: brokers, store: st, log: log}
}

func (c *Consumers) Run(ctx context.Context) error {
	g, ctx := errgroup.WithContext(ctx)

	g.Go(func() error {
		return c.runReader(ctx, "analytic-service-users-v3", "user.events", c.handleUser)
	})
	g.Go(func() error {
		return c.runReader(ctx, "analytic-service-orders-v5", "order.events", c.handleOrder)
	})
	g.Go(func() error {
		return c.runReader(ctx, "analytic-service-search-v3", "search.events", c.handleSearch)
	})
	g.Go(func() error {
		return c.runReader(ctx, "analytic-service-products-v1", "product.created", c.handleProduct)
	})
	g.Go(func() error {
		return c.runReader(ctx, "analytic-service-products-v1", "product.updated", c.handleProduct)
	})

	return g.Wait()
}

type handlerFunc func(ctx context.Context, msg kafka.Message) error

func (c *Consumers) runReader(ctx context.Context, groupID, topic string, handle handlerFunc) error {
	r := kafka.NewReader(kafka.ReaderConfig{
		Brokers:        c.brokers,
		GroupID:        groupID,
		Topic:          topic,
		MinBytes:       1,
		MaxBytes:       10e6,
		MaxWait:        time.Second,
		StartOffset:    kafka.FirstOffset,
		CommitInterval: 0, // manual commit after successful insert
	})
	defer r.Close()

	c.log.Info("kafka consumer started", "group", groupID, "topic", topic)

	for {
		msg, err := r.FetchMessage(ctx)
		if err != nil {
			if ctx.Err() != nil {
				return nil
			}
			return fmt.Errorf("%s fetch: %w", groupID, err)
		}

		// Retry the same message until success — do not FetchMessage again on error
		// (that would skip offsets and let later commits mark failed rows as done).
		for {
			if err := handle(ctx, msg); err != nil {
				c.log.Error("handle message failed",
					"group", groupID,
					"topic", msg.Topic,
					"partition", msg.Partition,
					"offset", msg.Offset,
					"err", err,
				)
				select {
				case <-ctx.Done():
					return nil
				case <-time.After(time.Second):
				}
				continue
			}
			break
		}

		if err := r.CommitMessages(ctx, msg); err != nil {
			return fmt.Errorf("%s commit: %w", groupID, err)
		}
	}
}

func (c *Consumers) handleUser(ctx context.Context, msg kafka.Message) error {
	body, err := parseJSONBody(msg)
	if err != nil {
		c.log.Warn("skip invalid user json", "err", err)
		return nil
	}
	et := resolveEventType(msg, body)
	if et != "user.registered" {
		return nil
	}

	ts := parseTime(asString(body["registered_at"]), messageTime(msg))
	row := store.UserRegistration{
		UserID:         asUint64(body["user_id"]),
		Email:          asString(body["email"]),
		Name:           asString(body["name"]),
		RegisteredAt:   ts,
		KafkaTopic:     msg.Topic,
		KafkaPartition: int32(msg.Partition),
		KafkaOffset:    msg.Offset,
	}
	if row.UserID == 0 {
		c.log.Warn("skip user.registered without user_id")
		return nil
	}
	return c.store.InsertUserRegistration(ctx, row)
}

func (c *Consumers) handleProduct(ctx context.Context, msg kafka.Message) error {
	body, err := parseJSONBody(msg)
	if err != nil {
		c.log.Warn("skip invalid product json", "err", err)
		return nil
	}
	et := resolveEventType(msg, body)
	if et != "product.created" && et != "product.updated" {
		return nil
	}

	data, _ := body["data"].(map[string]any)
	if data == nil {
		c.log.Warn("skip product event without data")
		return nil
	}

	productID := asUint64(data["id"])
	if productID == 0 {
		c.log.Warn("skip product event without id", "event_type", et)
		return nil
	}

	categoryID := asUint64(data["category_id"])
	categoryName := ""
	categorySlug := ""
	if cat, ok := data["category"].(map[string]any); ok {
		if categoryID == 0 {
			categoryID = asUint64(cat["id"])
		}
		categoryName = asString(cat["name"])
		categorySlug = asString(cat["slug"])
	}

	storeID := asUint64(data["store_id"])
	storeName := ""
	if st, ok := data["store"].(map[string]any); ok {
		if storeID == 0 {
			storeID = asUint64(st["id"])
		}
		storeName = asString(st["name"])
	}

	return c.store.InsertProductDim(ctx, store.ProductDim{
		ProductID:      productID,
		StoreID:        storeID,
		StoreName:      storeName,
		CategoryID:     categoryID,
		CategoryName:   categoryName,
		CategorySlug:   categorySlug,
		KafkaTopic:     msg.Topic,
		KafkaPartition: int32(msg.Partition),
		KafkaOffset:    msg.Offset,
	})
}

func (c *Consumers) handleOrder(ctx context.Context, msg kafka.Message) error {
	body, err := parseJSONBody(msg)
	if err != nil {
		c.log.Warn("skip invalid order json", "err", err)
		return nil
	}
	et := resolveEventType(msg, body)

	var short string
	switch et {
	case "order.created":
		short = "created"
	case "order.paid":
		short = "paid"
	case "order.shipped":
		short = "completed"
	case "order.cancelled":
		short = "cancelled"
	default:
		return nil
	}

	tsRaw := asString(body["occurred_at"])
	if tsRaw == "" {
		tsRaw = asString(body["created_at"])
	}
	ts := parseTime(tsRaw, messageTime(msg))

	row := store.OrderFunnelEvent{
		OrderID:        asUint64(body["order_id"]),
		UserID:         asUint64(body["user_id"]),
		EventType:      short,
		TotalAmount:    asString(body["total_amount"]),
		Reason:         asString(body["reason"]),
		OccurredAt:     ts,
		KafkaTopic:     msg.Topic,
		KafkaPartition: int32(msg.Partition),
		KafkaOffset:    msg.Offset,
	}
	if row.OrderID == 0 {
		c.log.Warn("skip order event without order_id", "event_type", et)
		return nil
	}
	if row.TotalAmount == "" {
		row.TotalAmount = "0.00"
	}
	if err := c.store.InsertOrderFunnelEvent(ctx, row); err != nil {
		return err
	}
	if short != "created" {
		return nil
	}
	return c.insertOrderLineItems(ctx, msg, body, row.OrderID, ts)
}

func (c *Consumers) insertOrderLineItems(ctx context.Context, msg kafka.Message, body map[string]any, orderID uint64, ts time.Time) error {
	rawItems, ok := body["items"].([]any)
	if !ok || len(rawItems) == 0 {
		return nil
	}
	lines := make([]store.OrderLineItem, 0, len(rawItems))
	for _, raw := range rawItems {
		item, ok := raw.(map[string]any)
		if !ok {
			continue
		}
		productID := asUint64(item["product_id"])
		if productID == 0 {
			continue
		}
		unitPrice := asString(item["unit_price"])
		if unitPrice == "" {
			unitPrice = "0.00"
		}

		storeID := asUint64(item["store_id"])
		storeName := asString(item["store_name"])
		categoryID := asUint64(item["category_id"])
		categoryName := asString(item["category_name"])
		categorySlug := asString(item["category_slug"])

		needDim := categoryID == 0 || storeName == "" || (categoryID != 0 && (categoryName == "" || categorySlug == ""))
		if needDim {
			dim, found, err := c.store.LookupProductDim(ctx, productID)
			if err != nil {
				return err
			}
			if found {
				if storeID == 0 {
					storeID = dim.StoreID
				}
				if storeName == "" {
					storeName = dim.StoreName
				}
				if categoryID == 0 {
					categoryID = dim.CategoryID
				}
				if categoryName == "" {
					categoryName = dim.CategoryName
				}
				if categorySlug == "" {
					categorySlug = dim.CategorySlug
				}
			}
		}

		lines = append(lines, store.OrderLineItem{
			OrderID:        orderID,
			ProductID:      productID,
			StoreID:        storeID,
			StoreName:      storeName,
			CategoryID:     categoryID,
			CategoryName:   categoryName,
			CategorySlug:   categorySlug,
			Quantity:       asUint32(item["quantity"]),
			UnitPrice:      unitPrice,
			OccurredAt:     ts,
			KafkaTopic:     msg.Topic,
			KafkaPartition: int32(msg.Partition),
			KafkaOffset:    msg.Offset,
		})
	}
	return c.store.InsertOrderLineItems(ctx, lines)
}

func (c *Consumers) handleSearch(ctx context.Context, msg kafka.Message) error {
	body, err := parseJSONBody(msg)
	if err != nil {
		c.log.Warn("skip invalid search json", "err", err)
		return nil
	}
	et := resolveEventType(msg, body)
	if et != "search.performed" {
		return nil
	}
	if isSkipAnalytics(msg, body) {
		return nil
	}

	data, _ := body["data"].(map[string]any)
	if data == nil {
		data = map[string]any{}
	}

	filtersRaw := "{}"
	if f, ok := data["filters"]; ok && f != nil {
		b, err := json.Marshal(f)
		if err == nil {
			filtersRaw = string(b)
		}
	}

	query := asString(data["query"])
	if query == "" {
		query = strings.TrimSpace(asString(body["query"]))
	}

	ts := parseTime(asString(body["timestamp"]), messageTime(msg))

	row := store.SearchQuery{
		Query:          query,
		ResultsCount:   asUint32(data["results_count"]),
		Filters:        filtersRaw,
		Sort:           asString(data["sort"]),
		OccurredAt:     ts,
		KafkaTopic:     msg.Topic,
		KafkaPartition: int32(msg.Partition),
		KafkaOffset:    msg.Offset,
	}
	return c.store.InsertSearchQuery(ctx, row)
}
