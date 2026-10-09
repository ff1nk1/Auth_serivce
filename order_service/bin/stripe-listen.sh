#!/usr/bin/env bash
# Forward Stripe test webhooks to the local gateway route.
# Prerequisites:
#   1. stripe CLI installed (~/.local/bin/stripe or apt)
#   2. stripe login
#   3. Paste printed whsec_… into order_service/.env as STRIPE_WEBHOOK_SECRET
#   4. docker compose up -d --force-recreate order_app
set -euo pipefail

STRIPE_BIN="${STRIPE_BIN:-}"
if [[ -z "$STRIPE_BIN" ]]; then
  if command -v stripe >/dev/null 2>&1; then
    STRIPE_BIN="$(command -v stripe)"
  elif [[ -x "${HOME}/.local/bin/stripe" ]]; then
    STRIPE_BIN="${HOME}/.local/bin/stripe"
  else
    echo "stripe CLI not found. Install to ~/.local/bin/stripe or set STRIPE_BIN." >&2
    exit 1
  fi
fi

FORWARD_TO="${STRIPE_FORWARD_TO:-https://app.localhost/api/webhooks/stripe}"

echo "Forwarding to ${FORWARD_TO}"
echo "Copy the whsec_… webhook signing secret into order_service/.env → STRIPE_WEBHOOK_SECRET"
echo "then: docker compose up -d --force-recreate order_app"
echo

exec "$STRIPE_BIN" listen \
  --forward-to "$FORWARD_TO" \
  --events checkout.session.completed,checkout.session.expired
