# search_service integration tests

Real **OpenSearch** index/search coverage. Kafka publish is asserted via `Kafka::fake()` (same pattern as `order_service`); catalog consume path invokes handlers with stub `ConsumerMessage` against the live index.

## Prerequisites

```bash
docker compose up -d search_opensearch
```

From the app container (preferred — uses `http://search_opensearch:9200`):

```bash
docker compose exec search_app php artisan test -c phpunit.integration.xml
```

From the host (OpenSearch port published as `9200`):

```bash
OPENSEARCH_HOSTS=http://localhost:9200 php artisan test -c phpunit.integration.xml
```

Tests use isolated indices `products_test` / `categories_test` and recreate them in each test `setUp`.
