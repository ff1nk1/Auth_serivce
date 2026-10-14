//CLICKHOUSE GET ONLY ONE ROW INSTEAD OF MANY AT ONE TIME BECAUSE ITS A DEV MODE


package store

import (
	"context"
	"fmt"
	"time"

	"github.com/ClickHouse/clickhouse-go/v2/lib/driver"
	"github.com/shopspring/decimal"
)

type Store struct {
	conn driver.Conn
}

func New(conn driver.Conn) *Store {
	return &Store{conn: conn}
}

func (s *Store) Ping(ctx context.Context) error {
	return s.conn.Ping(ctx)
}

func (s *Store) InsertUserRegistration(ctx context.Context, row UserRegistration) error {
	batch, err := s.conn.PrepareBatch(ctx, `
		INSERT INTO user_registrations
			(user_id, email, name, registered_at, kafka_topic, kafka_partition, kafka_offset)
	`)
	if err != nil {
		return fmt.Errorf("prepare user batch: %w", err)
	}
	if err := batch.Append(
		row.UserID,
		row.Email,
		row.Name,
		row.RegisteredAt,
		row.KafkaTopic,
		row.KafkaPartition,
		row.KafkaOffset,
	); err != nil {
		return fmt.Errorf("append user: %w", err)
	}
	return batch.Send()
}

func (s *Store) InsertOrderFunnelEvent(ctx context.Context, row OrderFunnelEvent) error {
	amount, err := decimal.NewFromString(row.TotalAmount)
	if err != nil {
		amount = decimal.Zero
	}

	batch, err := s.conn.PrepareBatch(ctx, `
		INSERT INTO order_funnel_events
			(order_id, user_id, event_type, total_amount, reason, occurred_at, kafka_topic, kafka_partition, kafka_offset)
	`)
	if err != nil {
		return fmt.Errorf("prepare order batch: %w", err)
	}
	if err := batch.Append(
		row.OrderID,
		row.UserID,
		row.EventType,
		amount,
		row.Reason,
		row.OccurredAt,
		row.KafkaTopic,
		row.KafkaPartition,
		row.KafkaOffset,
	); err != nil {
		return fmt.Errorf("append order: %w", err)
	}
	return batch.Send()
}

func (s *Store) InsertSearchQuery(ctx context.Context, row SearchQuery) error {
	batch, err := s.conn.PrepareBatch(ctx, `
		INSERT INTO search_queries
			(query, results_count, filters, sort, occurred_at, kafka_topic, kafka_partition, kafka_offset)
	`)
	if err != nil {
		return fmt.Errorf("prepare search batch: %w", err)
	}
	if err := batch.Append(
		row.Query,
		row.ResultsCount,
		row.Filters,
		row.Sort,
		row.OccurredAt,
		row.KafkaTopic,
		row.KafkaPartition,
		row.KafkaOffset,
	); err != nil {
		return fmt.Errorf("append search: %w", err)
	}
	return batch.Send()
}

func (s *Store) UsersMetrics(ctx context.Context, from, to *time.Time) (UsersMetrics, error) {
	var out UsersMetrics

	totalQ := `SELECT count() FROM user_registrations FINAL`
	args := []any{}
	if from != nil || to != nil {
		totalQ += ` WHERE 1=1`
		if from != nil {
			totalQ += ` AND registered_at >= ?`
			args = append(args, *from)
		}
		if to != nil {
			totalQ += ` AND registered_at < ?`
			args = append(args, *to)
		}
	}
	if err := s.conn.QueryRow(ctx, totalQ, args...).Scan(&out.TotalUsers); err != nil {
		return out, fmt.Errorf("total users: %w", err)
	}

	growthQ := `
		SELECT toDate(registered_at) AS d, count() AS c
		FROM user_registrations FINAL
		WHERE 1=1
	`
	growthArgs := []any{}
	if from != nil {
		growthQ += ` AND registered_at >= ?`
		growthArgs = append(growthArgs, *from)
	}
	if to != nil {
		growthQ += ` AND registered_at < ?`
		growthArgs = append(growthArgs, *to)
	}
	growthQ += ` GROUP BY d ORDER BY d`

	rows, err := s.conn.Query(ctx, growthQ, growthArgs...)
	if err != nil {
		return out, fmt.Errorf("growth query: %w", err)
	}
	defer rows.Close()

	var cumulative uint64
	out.Growth = make([]DayCount, 0)
	for rows.Next() {
		var d time.Time
		var c uint64
		if err := rows.Scan(&d, &c); err != nil {
			return out, fmt.Errorf("growth scan: %w", err)
		}
		cumulative += c
		out.Growth = append(out.Growth, DayCount{
			Date:       d.Format("2006-01-02"),
			Count:      c,
			Cumulative: cumulative,
		})
	}
	return out, rows.Err()
}

func (s *Store) OrdersMetrics(ctx context.Context, from, to *time.Time) (OrdersMetrics, error) {
	var out OrdersMetrics

	funnelQ := `
		SELECT
			countIf(event_type = 'created') AS created,
			countIf(event_type = 'paid') AS paid,
			countIf(event_type = 'completed') AS completed,
			countIf(event_type = 'cancelled') AS cancelled
		FROM order_funnel_events FINAL
		WHERE 1=1
	`
	args := []any{}
	if from != nil {
		funnelQ += ` AND occurred_at >= ?`
		args = append(args, *from)
	}
	if to != nil {
		funnelQ += ` AND occurred_at < ?`
		args = append(args, *to)
	}
	if err := s.conn.QueryRow(ctx, funnelQ, args...).Scan(
		&out.Funnel.Created,
		&out.Funnel.Paid,
		&out.Funnel.Completed,
		&out.Funnel.Cancelled,
	); err != nil {
		return out, fmt.Errorf("funnel: %w", err)
	}

	if out.Funnel.Created > 0 {
		out.Conversions.CreatedToPaid = float64(out.Funnel.Paid) / float64(out.Funnel.Created)
		out.Conversions.CreatedToCompleted = float64(out.Funnel.Completed) / float64(out.Funnel.Created)
		out.Conversions.CancelledRate = float64(out.Funnel.Cancelled) / float64(out.Funnel.Created)
	}
	if out.Funnel.Paid > 0 {
		out.Conversions.PaidToCompleted = float64(out.Funnel.Completed) / float64(out.Funnel.Paid)
	}

	revQ := `
		SELECT
			toFloat64(ifNull(sum(total_amount), 0)) AS total,
			toFloat64(ifNull(avg(total_amount), 0)) AS aov
		FROM order_funnel_events FINAL
		WHERE event_type = 'paid'
	`
	revArgs := []any{}
	if from != nil {
		revQ += ` AND occurred_at >= ?`
		revArgs = append(revArgs, *from)
	}
	if to != nil {
		revQ += ` AND occurred_at < ?`
		revArgs = append(revArgs, *to)
	}

	var total, aov float64
	if err := s.conn.QueryRow(ctx, revQ, revArgs...).Scan(&total, &aov); err != nil {
		return out, fmt.Errorf("revenue: %w", err)
	}
	out.Revenue.Total = formatMoney(total)
	out.AvgOrderValue = formatMoney(aov)

	byDayQ := `
		SELECT toDate(occurred_at) AS d, toFloat64(sum(total_amount)) AS amount
		FROM order_funnel_events FINAL
		WHERE event_type = 'paid'
	`
	byDayArgs := []any{}
	if from != nil {
		byDayQ += ` AND occurred_at >= ?`
		byDayArgs = append(byDayArgs, *from)
	}
	if to != nil {
		byDayQ += ` AND occurred_at < ?`
		byDayArgs = append(byDayArgs, *to)
	}
	byDayQ += ` GROUP BY d ORDER BY d`

	rows, err := s.conn.Query(ctx, byDayQ, byDayArgs...)
	if err != nil {
		return out, fmt.Errorf("revenue by day: %w", err)
	}
	defer rows.Close()

	out.Revenue.ByDay = make([]RevenueDay, 0)
	for rows.Next() {
		var d time.Time
		var amount float64
		if err := rows.Scan(&d, &amount); err != nil {
			return out, fmt.Errorf("revenue by day scan: %w", err)
		}
		out.Revenue.ByDay = append(out.Revenue.ByDay, RevenueDay{
			Date:   d.Format("2006-01-02"),
			Amount: formatMoney(amount),
		})
	}
	return out, rows.Err()
}

func (s *Store) SearchMetrics(ctx context.Context, from, to *time.Time, limit int) (SearchMetrics, error) {
	var out SearchMetrics
	if limit <= 0 {
		limit = 20
	}

	statsQ := `
		SELECT
			count() AS total,
			countIf(results_count = 0) AS zero_cnt
		FROM search_queries FINAL
		WHERE 1=1
	`
	args := []any{}
	if from != nil {
		statsQ += ` AND occurred_at >= ?`
		args = append(args, *from)
	}
	if to != nil {
		statsQ += ` AND occurred_at < ?`
		args = append(args, *to)
	}
	if err := s.conn.QueryRow(ctx, statsQ, args...).Scan(&out.TotalSearches, &out.ZeroResultCount); err != nil {
		return out, fmt.Errorf("search stats: %w", err)
	}
	if out.TotalSearches > 0 {
		out.ZeroResultRate = float64(out.ZeroResultCount) / float64(out.TotalSearches)
	}

	popular, err := s.topQueries(ctx, from, to, false, limit)
	if err != nil {
		return out, err
	}
	out.PopularQueries = popular

	zero, err := s.topQueries(ctx, from, to, true, limit)
	if err != nil {
		return out, err
	}
	out.ZeroResultQueries = zero

	return out, nil
}

func (s *Store) topQueries(ctx context.Context, from, to *time.Time, zeroOnly bool, limit int) ([]QueryCount, error) {
	q := `
		SELECT if(query = '', '(empty)', query) AS q, count() AS c
		FROM search_queries FINAL
		WHERE 1=1
	`
	args := []any{}
	if zeroOnly {
		q += ` AND results_count = 0`
	}
	if from != nil {
		q += ` AND occurred_at >= ?`
		args = append(args, *from)
	}
	if to != nil {
		q += ` AND occurred_at < ?`
		args = append(args, *to)
	}
	q += ` GROUP BY q ORDER BY c DESC LIMIT ?`
	args = append(args, limit)

	rows, err := s.conn.Query(ctx, q, args...)
	if err != nil {
		return nil, fmt.Errorf("top queries: %w", err)
	}
	defer rows.Close()

	out := make([]QueryCount, 0)
	for rows.Next() {
		var item QueryCount
		if err := rows.Scan(&item.Query, &item.Count); err != nil {
			return nil, fmt.Errorf("top queries scan: %w", err)
		}
		out = append(out, item)
	}
	return out, rows.Err()
}

func formatMoney(v float64) string {
	if v != v { // NaN
		return "0.00"
	}
	return fmt.Sprintf("%.2f", v)
}
