# Запуск

```bash
./scripts/bootstrap.sh
docker compose up -d --build
./scripts/test-all.sh
./scripts/lint-all.sh
./scripts/demo-prep.sh
```

В отдельной консоли (Stripe webhooks):

```bash
stripe listen --forward-to https://app.localhost/api/webhooks/stripe --skip-verify
```
