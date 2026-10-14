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

type Consumers struct {
	brokers []string
	store   *store.Store
	log     *slog.Logger
}

func NewConsumers(brokers []string, st *store.Store, log *slog.Logger) *Consumers {
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
		return c.runReader(ctx, "analytic-service-orders-v3", "order.events", c.handleOrder)
	})
	g.Go(func() error {
		return c.runReader(ctx, "analytic-service-search-v3", "search.events", c.handleSearch)
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
	return c.store.InsertOrderFunnelEvent(ctx, row)
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
