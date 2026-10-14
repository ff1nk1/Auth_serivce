package config

import (
	"fmt"
	"os"
	"strconv"
	"strings"
)

type Config struct {
	HTTPAddr string

	KafkaBrokers []string

	ClickHouseAddr     string
	ClickHouseDatabase string
	ClickHouseUser     string
	ClickHousePassword string
}

func Load() (Config, error) {
	cfg := Config{
		HTTPAddr:           envOr("HTTP_ADDR", ":8006"),
		KafkaBrokers:       splitCSV(envOr("KAFKA_BROKERS", "kafka:29092")),
		ClickHouseAddr:     envOr("CLICKHOUSE_ADDR", "analytic_clickhouse:9000"),
		ClickHouseDatabase: envOr("CLICKHOUSE_DATABASE", "analytics"),
		ClickHouseUser:     envOr("CLICKHOUSE_USER", "default"),
		ClickHousePassword: envOr("CLICKHOUSE_PASSWORD", ""),
	}

	if len(cfg.KafkaBrokers) == 0 {
		return Config{}, fmt.Errorf("KAFKA_BROKERS is empty")
	}
	if cfg.ClickHouseAddr == "" {
		return Config{}, fmt.Errorf("CLICKHOUSE_ADDR is empty")
	}
	return cfg, nil
}

func envOr(key, fallback string) string {
	if v := strings.TrimSpace(os.Getenv(key)); v != "" {
		return v
	}
	return fallback
}

func splitCSV(s string) []string {
	parts := strings.Split(s, ",")
	out := make([]string, 0, len(parts))
	for _, p := range parts {
		p = strings.TrimSpace(p)
		if p != "" {
			out = append(out, p)
		}
	}
	return out
}

// EnvInt is available for optional tunables.
func EnvInt(key string, fallback int) int {
	v := strings.TrimSpace(os.Getenv(key))
	if v == "" {
		return fallback
	}
	n, err := strconv.Atoi(v)
	if err != nil {
		return fallback
	}
	return n
}
