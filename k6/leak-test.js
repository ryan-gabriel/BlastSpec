import http from 'k6/http';
import { check } from 'k6';

export const options = {
  duration: '30s',
  vus: 20,
  thresholds: {
    http_req_duration: ['p(95)<100'],
  },
};

export default function () {
  const res = http.get('http://172.20.0.6:8000/api/v1/health');
  check(res, { 'status 200': (r) => r.status === 200 });
}
