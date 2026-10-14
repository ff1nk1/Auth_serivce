package store

import "time"

type UserRegistration struct {
	UserID         uint64
	Email          string
	Name           string
	RegisteredAt   time.Time
	KafkaTopic     string
	KafkaPartition int32
	KafkaOffset    int64
}

type OrderFunnelEvent struct {
	OrderID        uint64
	UserID         uint64
	EventType      string // created | paid | completed | cancelled
	TotalAmount    string
	Reason         string
	OccurredAt     time.Time
	KafkaTopic     string
	KafkaPartition int32
	KafkaOffset    int64
}

type SearchQuery struct {
	Query          string
	ResultsCount   uint32
	Filters        string
	Sort           string
	OccurredAt     time.Time
	KafkaTopic     string
	KafkaPartition int32
	KafkaOffset    int64
}

type DayCount struct {
	Date       string `json:"date"`
	Count      uint64 `json:"count"`
	Cumulative uint64 `json:"cumulative,omitempty"`
}

type UsersMetrics struct {
	TotalUsers uint64     `json:"total_users"`
	Growth     []DayCount `json:"growth"`
}

type OrderFunnel struct {
	Created   uint64 `json:"created"`
	Paid      uint64 `json:"paid"`
	Completed uint64 `json:"completed"`
	Cancelled uint64 `json:"cancelled"`
}

type OrderConversions struct {
	CreatedToPaid      float64 `json:"created_to_paid"`
	PaidToCompleted    float64 `json:"paid_to_completed"`
	CreatedToCompleted float64 `json:"created_to_completed"`
	CancelledRate      float64 `json:"cancelled_rate"`
}

type RevenueDay struct {
	Date   string `json:"date"`
	Amount string `json:"amount"`
}

type RevenueMetrics struct {
	Total string       `json:"total"`
	ByDay []RevenueDay `json:"by_day"`
}

type OrdersMetrics struct {
	Funnel        OrderFunnel       `json:"funnel"`
	Conversions   OrderConversions  `json:"conversions"`
	Revenue       RevenueMetrics    `json:"revenue"`
	AvgOrderValue string            `json:"avg_order_value"`
}

type QueryCount struct {
	Query string `json:"query"`
	Count uint64 `json:"count"`
}

type SearchMetrics struct {
	TotalSearches      uint64       `json:"total_searches"`
	ZeroResultCount    uint64       `json:"zero_result_count"`
	ZeroResultRate     float64      `json:"zero_result_rate"`
	PopularQueries     []QueryCount `json:"popular_queries"`
	ZeroResultQueries  []QueryCount `json:"zero_result_queries"`
}
