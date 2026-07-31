/* global process */

import { createServer } from 'node:http';

const server = createServer((request, response) => {
  if (request.url === '/api/v1/health/live') {
    response.writeHead(200, {
      'content-type': 'application/json',
      'x-step-01-synthetic-proxy': 'true',
    });
    response.end(JSON.stringify({ status: 'ok' }));
    return;
  }

  response.writeHead(404, { 'content-type': 'application/json' });
  response.end(JSON.stringify({ error: { code: 'synthetic_not_found' } }));
});

server.listen(3334, '127.0.0.1');

const close = () => server.close();
process.on('SIGINT', close);
process.on('SIGTERM', close);
