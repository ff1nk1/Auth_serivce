import http from 'k6/http';
import { check, sleep } from 'k6';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';
import { pickQuery } from './queries.js';
//docker compose --profile loadtest run --rm search_k6 run /scripts/search-load.js
const BASE_URL = __ENV.BASE_URL || 'http://search_service_app:8005';
const TARGET_VUS = Number(__ENV.VUS || 50);
const HOLD = __ENV.DURATION || '1m';

export const options = {
  stages: [
    { duration: '30s', target: Math.max(1, Math.floor(TARGET_VUS * 0.2)) },
    { duration: '30s', target: Math.max(1, Math.floor(TARGET_VUS * 0.6)) },
    { duration: HOLD, target: TARGET_VUS },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
  },
  summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
};

export default function () {
  const params = pickQuery(__ITER);
  // Still publishes search.performed to Kafka (realistic latency); analytic skips ClickHouse.
  const res = http.get(`${BASE_URL}/api/search`, {
    params,
    headers: { 'X-Skip-Analytics': '1' },
    tags: { name: 'search' },
  });

  check(res, {
    'status is 200': (r) => r.status === 200,
    'body has products': (r) => {
      try {
        const body = r.json();
        return body !== null && typeof body === 'object' && 'products' in body;
      } catch (_) {
        return false;
      }
    },
  });

  sleep(0.1);
}

export function handleSummary(data) {
  const duration = data.metrics.http_req_duration;
  const reqs = data.metrics.http_reqs;
  const failed = data.metrics.http_req_failed;

  const rps = reqs?.values?.rate ?? 0;
  const p95 = duration?.values?.['p(95)'] ?? 0;
  const p99 = duration?.values?.['p(99)'] ?? 0;
  const failRate = (failed?.values?.rate ?? 0) * 100;

  const highlight = [
    '',
    '========== search_service load summary ==========',
    `RPS (http_reqs rate):  ${rps.toFixed(2)} req/s`,
    `p95 latency:           ${p95.toFixed(2)} ms`,
    `p99 latency:           ${p99.toFixed(2)} ms`,
    `error rate:            ${failRate.toFixed(2)} %`,
    '=================================================',
    '',
  ].join('\n');

  return {
    stdout: highlight + textSummary(data, { indent: ' ', enableColors: true }),
  };
}
