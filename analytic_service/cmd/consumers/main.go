package main

import (
	"context"
	"log/slog"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"

	chclient "analytic_service/internal/clickhouse"
	"analytic_service/internal/config"
	kafkaconsumer "analytic_service/internal/kafka"
	"analytic_service/internal/store"
)

func main() {
	log := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))

	cfg, err := config.Load()
	if err != nil {
		log.Error("config", "err", err)
		os.Exit(1)
	}

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	if err := chclient.EnsureDatabase(ctx, cfg); err != nil {
		log.Error("ensure database", "err", err)
		os.Exit(1)
	}

	conn, err := chclient.Connect(ctx, cfg)
	if err != nil {
		log.Error("clickhouse connect", "err", err)
		os.Exit(1)
	}
	defer conn.Close()

	migrationsDir := envOr("MIGRATIONS_DIR", "migrations")
	if abs, err := filepath.Abs(migrationsDir); err == nil {
		migrationsDir = abs
	}
	if err := chclient.Migrate(ctx, conn, migrationsDir); err != nil {
		log.Error("migrate", "err", err)
		os.Exit(1)
	}

	st := store.New(conn)
	consumers := kafkaconsumer.NewConsumers(cfg.KafkaBrokers, st, log)

	log.Info("analytic consumers starting", "brokers", cfg.KafkaBrokers)
	if err := consumers.Run(ctx); err != nil {
		log.Error("consumers stopped with error", "err", err)
		os.Exit(1)
	}
	log.Info("analytic consumers stopped")
}

func envOr(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
