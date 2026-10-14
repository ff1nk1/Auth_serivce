package kafka

import (
	"encoding/json"
	"testing"
	"time"

	"github.com/segmentio/kafka-go"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestResolveEventType(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name string
		msg  kafka.Message
		body map[string]any
		want string
	}{
		{
			name: "header wins over body",
			msg: kafka.Message{Headers: []kafka.Header{
				{Key: "event-type", Value: []byte("order.paid")},
			}},
			body: map[string]any{"event_type": "order.created"},
			want: "order.paid",
		},
		{
			name: "body event_type",
			msg:  kafka.Message{},
			body: map[string]any{"event_type": "user.registered"},
			want: "user.registered",
		},
		{
			name: "body event fallback",
			msg:  kafka.Message{},
			body: map[string]any{"event": "search.performed"},
			want: "search.performed",
		},
		{
			name: "empty",
			msg:  kafka.Message{},
			body: map[string]any{},
			want: "",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()
			assert.Equal(t, tt.want, resolveEventType(tt.msg, tt.body))
		})
	}
}

func TestIsSkipAnalytics(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name string
		msg  kafka.Message
		body map[string]any
		want bool
	}{
		{
			name: "header true",
			msg: kafka.Message{Headers: []kafka.Header{
				{Key: "skip-analytics", Value: []byte("true")},
			}},
			body: map[string]any{},
			want: true,
		},
		{
			name: "header yes",
			msg: kafka.Message{Headers: []kafka.Header{
				{Key: "Skip-Analytics", Value: []byte("YES")},
			}},
			body: map[string]any{},
			want: true,
		},
		{
			name: "body bool",
			msg:  kafka.Message{},
			body: map[string]any{"skip_analytics": true},
			want: true,
		},
		{
			name: "body string 1",
			msg:  kafka.Message{},
			body: map[string]any{"skip_analytics": "1"},
			want: true,
		},
		{
			name: "false",
			msg:  kafka.Message{},
			body: map[string]any{"skip_analytics": false},
			want: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()
			assert.Equal(t, tt.want, isSkipAnalytics(tt.msg, tt.body))
		})
	}
}

func TestAsUint64(t *testing.T) {
	t.Parallel()

	assert.Equal(t, uint64(42), asUint64(float64(42)))
	assert.Equal(t, uint64(7), asUint64(int64(7)))
	assert.Equal(t, uint64(3), asUint64(3))
	assert.Equal(t, uint64(9), asUint64("9"))
	assert.Equal(t, uint64(11), asUint64(json.Number("11")))
	assert.Equal(t, uint64(0), asUint64(float64(-1)))
	assert.Equal(t, uint64(0), asUint64(struct{}{}))
}

func TestAsString(t *testing.T) {
	t.Parallel()

	assert.Equal(t, "hello", asString("hello"))
	assert.Equal(t, "1.5", asString(float64(1.5)))
	assert.Equal(t, "", asString(nil))
	assert.Equal(t, `{"a":1}`, asString(map[string]any{"a": 1}))
}

func TestParseTime(t *testing.T) {
	t.Parallel()

	fallback := time.Date(2024, 1, 1, 0, 0, 0, 0, time.UTC)

	assert.Equal(t, fallback, parseTime("", fallback))
	assert.Equal(t, fallback, parseTime("not-a-time", fallback))

	got := parseTime("2024-06-15T12:30:00Z", fallback)
	assert.Equal(t, time.Date(2024, 6, 15, 12, 30, 0, 0, time.UTC), got)

	got = parseTime("2024-06-15 12:30:00", fallback)
	assert.Equal(t, time.Date(2024, 6, 15, 12, 30, 0, 0, time.UTC), got)
}

func TestParseJSONBody(t *testing.T) {
	t.Parallel()

	empty, err := parseJSONBody(kafka.Message{})
	require.NoError(t, err)
	assert.Empty(t, empty)

	body, err := parseJSONBody(kafka.Message{Value: []byte(`{"user_id":1}`)})
	require.NoError(t, err)
	assert.Equal(t, float64(1), body["user_id"])

	_, err = parseJSONBody(kafka.Message{Value: []byte(`{bad`)})
	assert.Error(t, err)
}
