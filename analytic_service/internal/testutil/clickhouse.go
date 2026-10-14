package testutil

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
	"testing"
	"time"

	chclient "analytic_service/internal/clickhouse"
	"analytic_service/internal/config"
	"analytic_service/internal/store"

	"github.com/ClickHouse/clickhouse-go/v2/lib/driver"
	"github.com/stretchr/testify/require"
)

// OpenTestStore connects to ClickHouse (default localhost:19000 / analytics_test).
// Skips the test when ClickHouse is unreachable.
func OpenTestStore(t *testing.T) (*store.Store, driver.Conn) {
	t.Helper()

	cfg := config.Config{
		ClickHouseAddr:     envOr("CLICKHOUSE_ADDR", "localhost:19000"),
		ClickHouseDatabase: envOr("CLICKHOUSE_DATABASE", "analytics_test"),
		ClickHouseUser:     envOr("CLICKHOUSE_USER", "default"),
		ClickHousePassword: envOr("CLICKHOUSE_PASSWORD", ""),
	}

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := chclient.EnsureDatabase(ctx, cfg); err != nil {
		t.Skipf("clickhouse unavailable at %s: %v", cfg.ClickHouseAddr, err)
	}

	conn, err := chclient.Connect(ctx, cfg)
	if err != nil {
		t.Skipf("clickhouse connect failed at %s: %v", cfg.ClickHouseAddr, err)
	}

	migrationsDir := MigrationsDir(t)
	require.NoError(t, chclient.Migrate(ctx, conn, migrationsDir))

	t.Cleanup(func() {
		_ = conn.Close()
	})

	return store.New(conn), conn
}

// TruncateAnalytics clears fact tables between cases.
func TruncateAnalytics(t *testing.T, conn driver.Conn) {
	t.Helper()
	ctx := context.Background()
	tables := []string{
		"order_funnel_events",
		"order_line_items",
		"product_dim",
		"user_registrations",
		"search_queries",
	}
	for _, table := range tables {
		require.NoError(t, conn.Exec(ctx, fmt.Sprintf("TRUNCATE TABLE IF EXISTS %s", table)))
	}
}

// MigrationsDir resolves analytic_service/migrations relative to this source file.
func MigrationsDir(t *testing.T) string {
	t.Helper()
	if v := os.Getenv("MIGRATIONS_DIR"); v != "" {
		abs, err := filepath.Abs(v)
		require.NoError(t, err)
		return abs
	}
	_, file, _, ok := runtime.Caller(0)
	require.True(t, ok)
	dir := filepath.Join(filepath.Dir(file), "..", "..", "migrations")
	abs, err := filepath.Abs(dir)
	require.NoError(t, err)
	return abs
}

func KafkaBrokers() []string {
	raw := envOr("KAFKA_BROKERS", "localhost:9092")
	parts := splitCSV(raw)
	if len(parts) == 0 {
		return []string{"localhost:9092"}
	}
	return parts
}

func envOr(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func splitCSV(s string) []string {
	out := make([]string, 0)
	start := 0
	for i := 0; i <= len(s); i++ {
		if i == len(s) || s[i] == ',' {
			p := s[start:i]
			// trim spaces
			for len(p) > 0 && (p[0] == ' ' || p[0] == '\t') {
				p = p[1:]
			}
			for len(p) > 0 && (p[len(p)-1] == ' ' || p[len(p)-1] == '\t') {
				p = p[:len(p)-1]
			}
			if p != "" {
				out = append(out, p)
			}
			start = i + 1
		}
	}
	return out
}
