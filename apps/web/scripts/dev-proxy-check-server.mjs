// Minimal ad-hoc backend for apps/web/vite.config.ts dev-proxy sanity check only.
// NOT shipped. Started by a reviewer running the done-check steps in the PR body when
// they don't have the real Laravel stack up.
//
// Answers the three routes the Vite proxy forwards:
//   GET /sanctum/csrf-cookie  → 204, Set-Cookie: XSRF-TOKEN=stand-in; Path=/
//   POST /api/login           → 204, Set-Cookie: navuuna_session=stand-in; Path=/
//   GET /api/v1/flags         → 200, [] when the request carries navuuna_session; 401 without
// Any other path returns 404 so a route typo in the proxy is loud.
//
// Run:  node apps/web/scripts/dev-proxy-check-server.mjs

import http from 'node:http';

const PORT = 8000;

function hasSession(req) {
  const cookie = req.headers.cookie ?? '';
  return cookie.includes('navuuna_session=');
}

const server = http.createServer((req, res) => {
  const url = req.url ?? '';
  const method = req.method ?? 'GET';

  if (method === 'GET' && url.startsWith('/sanctum/csrf-cookie')) {
    res.setHeader('Set-Cookie', 'XSRF-TOKEN=stand-in; Path=/; HttpOnly=false');
    res.statusCode = 204;
    res.end();
    return;
  }
  if (method === 'POST' && url.startsWith('/api/login')) {
    res.setHeader('Set-Cookie', 'navuuna_session=stand-in; Path=/; HttpOnly');
    res.statusCode = 204;
    res.end();
    return;
  }
  if (method === 'GET' && url.startsWith('/api/v1/flags')) {
    if (!hasSession(req)) {
      res.statusCode = 401;
      res.setHeader('Content-Type', 'application/json');
      res.end(JSON.stringify({ title: 'Unauthenticated', status: 401 }));
      return;
    }
    res.statusCode = 200;
    res.setHeader('Content-Type', 'application/json');
    res.end('[]');
    return;
  }

  res.statusCode = 404;
  res.end('stand-in: route not configured');
});

server.listen(PORT, () => {
  console.log(`stand-in backend ready at http://localhost:${PORT}`);
});
