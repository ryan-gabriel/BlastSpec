import http from 'k6/http';
import { check } from 'k6';

export const options = {
  vus: 1,
  duration: '5s',
};

export default function () {
  const res = http.get('http://172.20.0.6:8000/api/v1/health');
  check(res, { 'status 200': (r) => r.status === 200 });
}
