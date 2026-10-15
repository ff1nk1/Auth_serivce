#!/usr/bin/env bash
# Apply pending migrations and seeders for a demo-ready local stack.
#
# Usage (from repo root):
#   ./scripts/demo-prep.sh                 # all services
#   ./scripts/demo-prep.sh auth catalog    # filter by short name
#
# Does NOT start containers. Bring the stack up first, e.g.:
#   docker compose up -d
#
# Order matters: search indices before catalog seed (Kafka republish),
# catalog before order seed (ProductSnapshotSeeder reads catalog DB).
#
# Analytic ClickHouse migrations run automatically when analytic_api /
# analytic_consumers start — no artisan step here.
#
# Short names: auth notification search catalog order

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose)

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BOLD='\033[1m'
NC='\033[0m'

FAILED=0
declare -a RESULTS=()

log() { printf '%b\n' "$*"; }

require_running() {
  local svc="$1"
  if [[ -z "$("${COMPOSE[@]}" ps --status running -q "$svc" 2>/dev/null)" ]]; then
    log "${RED}ERROR:${NC} compose service '${BOLD}${svc}${NC}' is not running."
    log "  Start it with: docker compose up -d ${svc}"
    return 1
  fi
  return 0
}

should_run() {
  local name="$1"
  if [[ ${#FILTER[@]} -eq 0 ]]; then
    return 0
  fi
  local f
  for f in "${FILTER[@]}"; do
    if [[ "$f" == "$name" ]]; then
      return 0
    fi
  done
  return 1
}

run_step() {
  local label="$1"
  local app="$2"
  shift 2
  local start end elapsed rc
  log ""
  log "${BOLD}═══ [${label}] $* ═══${NC}"
  start=$(date +%s)
  if "${COMPOSE[@]}" exec -T "$app" "$@"; then
    rc=0
  else
    rc=$?
  fi
  end=$(date +%s)
  elapsed=$((end - start))
  if [[ $rc -eq 0 ]]; then
    log "${GREEN}PASS${NC} [${label}] (${elapsed}s)"
    RESULTS+=("PASS ${label}")
  else
    log "${RED}FAIL${NC} [${label}] exit=${rc} (${elapsed}s)"
    RESULTS+=("FAIL ${label}")
    FAILED=1
  fi
}

FILTER=("$@")

VALID=(auth notification search catalog order)
for f in "${FILTER[@]+"${FILTER[@]}"}"; do
  ok=0
  for v in "${VALID[@]}"; do
    if [[ "$f" == "$v" ]]; then ok=1; break; fi
  done
  if [[ $ok -eq 0 ]]; then
    log "${RED}ERROR:${NC} unknown service '${f}'. Valid: ${VALID[*]}"
    exit 2
  fi
done

declare -a NEED=()
add_need() {
  local s
  for s in "$@"; do
    NEED+=("$s")
  done
}

if should_run auth; then
  add_need auth_app
fi
if should_run notification; then
  add_need notification_app
fi
if should_run search; then
  add_need search_app search_opensearch
fi
if should_run catalog; then
  add_need catalog_app kafka catalog_kafka_outbox_worker \
    search_kafka_product_consumer search_kafka_category_consumer search_kafka_stock_consumer
fi
if should_run order; then
  add_need order_app catalog_db
fi

declare -A SEEN=()
declare -a UNIQUE=()
for s in "${NEED[@]}"; do
  if [[ -z "${SEEN[$s]+x}" ]]; then
    SEEN[$s]=1
    UNIQUE+=("$s")
  fi
done

log "${BOLD}Checking running containers...${NC}"
CHECK_FAIL=0
for s in "${UNIQUE[@]}"; do
  if ! require_running "$s"; then
    CHECK_FAIL=1
  fi
done
if [[ $CHECK_FAIL -ne 0 ]]; then
  log ""
  log "${YELLOW}Hint:${NC} from repo root, start missing services then re-run:"
  log "  docker compose up -d ${UNIQUE[*]}"
  exit 1
fi
log "${GREEN}All required containers are running.${NC}"

TOTAL_START=$(date +%s)

# Fixed order for cross-service dependencies (filter still skips unselected).
if should_run auth; then
  run_step auth auth_app php artisan migrate --seed --force
fi
if should_run notification; then
  run_step notification notification_app php artisan migrate --seed --force
fi
if should_run search; then
  run_step search search_app php artisan opensearch:setup
fi
if should_run catalog; then
  run_step catalog catalog_app php artisan migrate --seed --force
fi
if should_run order; then
  run_step order order_app php artisan migrate --seed --force
fi

TOTAL_END=$(date +%s)
TOTAL_ELAPSED=$((TOTAL_END - TOTAL_START))

log ""
log "${BOLD}═══ Summary (${TOTAL_ELAPSED}s) ═══${NC}"
for r in "${RESULTS[@]+"${RESULTS[@]}"}"; do
  if [[ "$r" == PASS* ]]; then
    log "  ${GREEN}${r}${NC}"
  else
    log "  ${RED}${r}${NC}"
  fi
done

log ""
log "${YELLOW}Note:${NC} analytic ClickHouse schema migrates on analytic_api / analytic_consumers startup."

if [[ $FAILED -ne 0 ]]; then
  log ""
  log "${RED}${BOLD}Some steps failed.${NC}"
  exit 1
fi

log ""
log "${GREEN}${BOLD}Demo stack is ready.${NC}"
exit 0
