package main

import (
	"context"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"
	"time"

	chclient "analytic_service/internal/clickhouse"
	"analytic_service/internal/config"
	"analytic_service/internal/httpapi"
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
	srv := &http.Server{
		Addr:              cfg.HTTPAddr,
		Handler:           httpapi.NewServer(st).Routes(),
		ReadHeaderTimeout: 5 * time.Second,
	}

	go func() {
		log.Info("analytic api listening", "addr", cfg.HTTPAddr)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Error("http server", "err", err)
			stop()
		}
	}()

	<-ctx.Done()
	shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	_ = srv.Shutdown(shutdownCtx)
	log.Info("analytic api stopped")
}

func envOr(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
