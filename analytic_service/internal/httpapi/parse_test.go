package httpapi

import (
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestParseDate(t *testing.T) {
	t.Parallel()

	d, err := parseDate("2024-05-01")
	require.NoError(t, err)
	assert.Equal(t, time.Date(2024, 5, 1, 0, 0, 0, 0, time.UTC), d)

	d, err = parseDate("2024-05-01T15:04:05Z")
	require.NoError(t, err)
	assert.Equal(t, time.Date(2024, 5, 1, 15, 4, 5, 0, time.UTC), d)

	_, err = parseDate("nope")
	assert.Error(t, err)
}

func TestParseRange(t *testing.T) {
	t.Parallel()

	r := httptest.NewRequest(http.MethodGet, "/?from=2024-01-01&to=2024-01-31", nil)
	from, to, err := parseRange(r)
	require.NoError(t, err)
	require.NotNil(t, from)
	require.NotNil(t, to)
	assert.Equal(t, time.Date(2024, 1, 1, 0, 0, 0, 0, time.UTC), *from)
	// date-only to is exclusive end-of-day (+24h)
	assert.Equal(t, time.Date(2024, 2, 1, 0, 0, 0, 0, time.UTC), *to)

	r = httptest.NewRequest(http.MethodGet, "/?to=2024-01-31T12:00:00Z", nil)
	_, to, err = parseRange(r)
	require.NoError(t, err)
	require.NotNil(t, to)
	assert.Equal(t, time.Date(2024, 1, 31, 12, 0, 0, 0, time.UTC), *to)

	from, to, err = parseRange(httptest.NewRequest(http.MethodGet, "/", nil))
	require.NoError(t, err)
	assert.Nil(t, from)
	assert.Nil(t, to)
}

func TestParseLimit(t *testing.T) {
	t.Parallel()

	n, err := parseLimit(httptest.NewRequest(http.MethodGet, "/", nil), 20)
	require.NoError(t, err)
	assert.Equal(t, 20, n)

	n, err = parseLimit(httptest.NewRequest(http.MethodGet, "/?limit=50", nil), 20)
	require.NoError(t, err)
	assert.Equal(t, 50, n)

	_, err = parseLimit(httptest.NewRequest(http.MethodGet, "/?limit=0", nil), 20)
	assert.Error(t, err)

	_, err = parseLimit(httptest.NewRequest(http.MethodGet, "/?limit=101", nil), 20)
	assert.Error(t, err)

	_, err = parseLimit(httptest.NewRequest(http.MethodGet, "/?limit=abc", nil), 20)
	assert.Error(t, err)
}

func TestParseOptionalUint64(t *testing.T) {
	t.Parallel()

	v, err := parseOptionalUint64(httptest.NewRequest(http.MethodGet, "/", nil), "store_id")
	require.NoError(t, err)
	assert.Nil(t, v)

	v, err = parseOptionalUint64(httptest.NewRequest(http.MethodGet, "/?store_id=42", nil), "store_id")
	require.NoError(t, err)
	require.NotNil(t, v)
	assert.Equal(t, uint64(42), *v)

	_, err = parseOptionalUint64(httptest.NewRequest(http.MethodGet, "/?store_id=0", nil), "store_id")
	assert.Error(t, err)

	_, err = parseOptionalUint64(httptest.NewRequest(http.MethodGet, "/?store_id=x", nil), "store_id")
	assert.Error(t, err)
}
