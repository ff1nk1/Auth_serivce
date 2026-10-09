# Order Service integration tests

- **Real:** PostgreSQL (`order_db_test`), Redis (DB 15), Stripe test mode API
- **Faked:** Kafka publish/consume (`Kafka::fake`) — stock steps invoke handlers directly to avoid flaky broker consume with live workers

## Prerequisites

```bash
docker compose up -d order_db order_redis order_app
```

`STRIPE_SECRET` and `STRIPE_WEBHOOK_SECRET` must be set in `order_service/.env`.

## Run

```bash
docker compose exec order_app php artisan test -c phpunit.integration.xml
```

Fast suite (sqlite + fakes) remains:

```bash
docker compose exec order_app php artisan test
```
