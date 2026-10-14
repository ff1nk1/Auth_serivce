package store

import (
	"math"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestFormatMoney(t *testing.T) {
	t.Parallel()

	assert.Equal(t, "0.00", formatMoney(math.NaN()))
	assert.Equal(t, "0.00", formatMoney(0))
	assert.Equal(t, "12.50", formatMoney(12.5))
	assert.Equal(t, "12.35", formatMoney(12.345))
	assert.Equal(t, "12.34", formatMoney(12.344))
}
