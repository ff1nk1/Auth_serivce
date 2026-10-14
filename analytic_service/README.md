# analytic_service

Native Go analytics microservice.

- **API** (`analytic_api`) — `GET /analytics/users|orders|search`, `GET /healthz`
- **Consumers** (`analytic_consumers`) — Kafka topics `user.events`, `order.events`, `search.events` → ClickHouse
- **Storage** — ClickHouse (`analytic_clickhouse`)

Access is enforced by `auth_service` gateway (`role:admin,analyst`):

- `GET /api/analytics/users` — registrations growth by period
- `GET /api/analytics/orders` — funnel `created → paid → completed (order.shipped) / cancelled`, step conversions, revenue/AOV
- `GET /api/analytics/search` — popular queries and zero-result queries

Optional query params: `from`, `to` (YYYY-MM-DD or RFC3339), `limit` (search only).

Admin UI (React): `/admin/analytics/users|orders|search`.  
Ship confirmed orders: `POST /api/admin/orders/{id}/ship` (admin/analyst).

ClickHouse Play (built-in SQL UI): [http://localhost:8123/play](http://localhost:8123/play)  
or Traefik HTTP: [http://clickhouse.localhost/play](http://clickhouse.localhost/play) (add `clickhouse.localhost` to `/etc/hosts` → `127.0.0.1`).

Loadtest / synthetic traffic: events with header `skip-analytics: true` or body `skip_analytics: true` are consumed but **not** written to ClickHouse (Kafka publish still happens on search for realistic perf tests).

## Consumer groups

Readers use `analytic-service-*-v3` (earliest). After schema/metric changes, truncate facts so re-consume is clean:

```sql
TRUNCATE TABLE IF EXISTS order_funnel_events;
TRUNCATE TABLE IF EXISTS user_registrations;
TRUNCATE TABLE IF EXISTS search_queries;
```

Then restart `analytic_consumers` (new group ids already re-read from earliest on first start).

## Local stack

From repo root:

```bash
docker compose up -d --build analytic_clickhouse analytic_api analytic_consumers
```

## Development build

```bash
docker build -t analytic_service:local .
```
