# analytic_service

Native Go analytics microservice.

- **API** (`analytic_api`) — `GET /analytics/users|orders|orders/stores|orders/categories|search`, `GET /healthz`
- **Consumers** (`analytic_consumers`) — Kafka topics `user.events`, `order.events`, `search.events`, `product.created`, `product.updated` → ClickHouse
- **Storage** — ClickHouse (`analytic_clickhouse`)

Access is enforced by `auth_service` gateway (`role:admin,analyst`):

- `GET /api/analytics/users` — registrations growth by period
- `GET /api/analytics/orders` — cohort funnel by `created` in period (`paid` / `cancelled` / `open`, plus `completed`=shipped), conversions `created→paid` and `created→cancelled`, revenue/AOV
- `GET /api/analytics/orders/stores` — ranking by store (name + id), or detail with `?store_id=` (line amounts + funnel for orders containing that store). Always includes `options` (`[{id,name}]`) from `product_dim` for admin dropdown filters
- `GET /api/analytics/orders/categories` — ranking by category (name + slug + id), or detail with `?category_id=`. Always includes `options` (`[{id,name,slug}]`) for admin dropdown filters
- `GET /api/analytics/search` — popular queries and zero-result queries

Optional query params: `from`, `to` (YYYY-MM-DD or RFC3339), `limit` (search / ranking, default 20), `store_id` / `category_id` (detail mode). Ranking/detail names fall back to `product_dim` when line-item names are empty.

Admin UI (React): `/admin/analytics/users|orders|search|stores|categories` — stores/categories pages filter via name dropdown (`options`), not raw ID input.  

Ship confirmed orders: `POST /api/admin/orders/{id}/ship` (admin/analyst).

ClickHouse Play (built-in SQL UI): [http://localhost:8123/play](http://localhost:8123/play)  
or Traefik HTTP: [http://clickhouse.localhost/play](http://clickhouse.localhost/play) (add `clickhouse.localhost` to `/etc/hosts` → `127.0.0.1`).

Loadtest / synthetic traffic: events with header `skip-analytics: true` or body `skip_analytics: true` are consumed but **not** written to ClickHouse (Kafka publish still happens on search for realistic perf tests).

## Consumer groups

| Topic | Group |
|-------|-------|
| `user.events` | `analytic-service-users-v3` |
| `order.events` | `analytic-service-orders-v5` |
| `search.events` | `analytic-service-search-v3` |
| `product.created` / `product.updated` | `analytic-service-products-v1` |

Order service product snapshots use `order-service-products-v2` (re-reads catalog product events to backfill `category_id` / names).

After schema/metric changes, truncate facts so re-consume is clean:

```sql
TRUNCATE TABLE IF EXISTS order_funnel_events;
TRUNCATE TABLE IF EXISTS order_line_items;
TRUNCATE TABLE IF EXISTS product_dim;
TRUNCATE TABLE IF EXISTS user_registrations;
TRUNCATE TABLE IF EXISTS search_queries;
```

Then restart `analytic_consumers` (and order-service product consumer). Prefer letting `product_dim` fill first, then truncate/re-consume `order_line_items` so categories enrich from the dimension table.

## Local stack

From repo root:

```bash
docker compose up -d --build analytic_clickhouse analytic_api analytic_consumers
```

## Tests

Unit tests (helpers / handlers / HTTP parsing / `formatMoney`) always run. Store aggregation tests need ClickHouse native protocol on the host (default `localhost:19000`).

```bash
# from repo root — expose native CH port 19000 (host 9000 is MinIO)
docker compose -f compose.yml -f analytic_service/compose.test.yml up -d analytic_clickhouse

cd analytic_service
go test ./...

# Kafka + ClickHouse integration (needs kafka on localhost:9092)
go test -tags=integration ./internal/kafka/...
```

Env overrides: `CLICKHOUSE_ADDR`, `CLICKHOUSE_DATABASE` (default `analytics_test`), `KAFKA_BROKERS`, `MIGRATIONS_DIR`.

## Development build

```bash
docker build -t analytic_service:local .
```
