const crypto = require('node:crypto');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { URL } = require('node:url');

const port = Number(process.env.PORT || 10000);
const publicDir = path.join(__dirname, 'public');
const sessions = new Map();

const services = [
  {
    key: 'hris',
    name: 'HRIS',
    description: 'People, attendance, leave, overtime, and payroll',
    url: process.env.CENTRAL_ADMIN_HRIS_URL || 'http://127.0.0.1:30080',
    healthUrl: process.env.CENTRAL_ADMIN_HRIS_HEALTH_URL || 'http://hris:10000',
    icon: 'HR',
    accent: 'red',
  },
  {
    key: 'production',
    name: 'Production',
    description: 'Orders, production work, and shop operations',
    url: process.env.CENTRAL_ADMIN_PRODUCTION_URL || 'http://127.0.0.1:30081',
    healthUrl: process.env.CENTRAL_ADMIN_PRODUCTION_HEALTH_URL || 'http://production:10000',
    icon: 'PR',
    accent: 'blue',
  },
  {
    key: 'marketing',
    name: 'Marketing',
    description: 'Campaigns, customers, and marketing work',
    url: process.env.CENTRAL_ADMIN_MARKETING_URL || 'http://127.0.0.1:30082',
    healthUrl: process.env.CENTRAL_ADMIN_MARKETING_HEALTH_URL || 'http://marketing:10000',
    icon: 'MK',
    accent: 'gold',
  },
];

const adminUsername = process.env.CENTRAL_ADMIN_USERNAME || 'admin';
const adminPassword = process.env.CENTRAL_ADMIN_PASSWORD || 'imprint123';

function parseCookies(request) {
  return Object.fromEntries((request.headers.cookie || '')
    .split(';')
    .map((part) => part.trim().split('='))
    .filter(([key, value]) => key && value)
    .map(([key, ...value]) => [key, decodeURIComponent(value.join('='))]));
}

function isAuthenticated(request) {
  const token = parseCookies(request).central_admin_session;
  return Boolean(token && sessions.has(token));
}

function send(response, status, body, headers = {}) {
  response.writeHead(status, { 'Cache-Control': 'no-store', ...headers });
  response.end(body);
}

function redirect(response, location, headers = {}) {
  send(response, 302, '', { Location: location, ...headers });
}

function servePage(response, filename) {
  const file = path.join(publicDir, filename);
  send(response, 200, fs.readFileSync(file), {
    'Content-Type': 'text/html; charset=utf-8',
  });
}

function serveAsset(response, filename, contentType) {
  const file = path.join(publicDir, filename);
  send(response, 200, fs.readFileSync(file), { 'Content-Type': contentType });
}

function readBody(request) {
  return new Promise((resolve, reject) => {
    let body = '';
    request.on('data', (chunk) => {
      body += chunk;
      if (body.length > 10000) {
        reject(new Error('Request body too large'));
        request.destroy();
      }
    });
    request.on('end', () => resolve(new URLSearchParams(body)));
    request.on('error', reject);
  });
}

function safeEqual(left, right) {
  const a = Buffer.from(String(left));
  const b = Buffer.from(String(right));
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

async function checkService(service) {
  const started = Date.now();
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 3000);

  try {
    const result = await fetch(service.healthUrl, {
      signal: controller.signal,
      redirect: 'manual',
    });

    return {
      key: service.key,
      name: service.name,
      description: service.description,
      icon: service.icon,
      accent: service.accent,
      url: service.url,
      status: result.status < 500 ? 'online' : 'error',
      httpStatus: result.status,
      latencyMs: Date.now() - started,
    };
  } catch (error) {
    return {
      key: service.key,
      name: service.name,
      description: service.description,
      icon: service.icon,
      accent: service.accent,
      url: service.url,
      status: 'offline',
      httpStatus: null,
      latencyMs: Date.now() - started,
      error: error.name === 'AbortError' ? 'Timed out' : 'Unavailable',
    };
  } finally {
    clearTimeout(timeout);
  }
}

async function handle(request, response) {
  const requestUrl = new URL(request.url, `http://${request.headers.host || 'localhost'}`);
  const pathname = requestUrl.pathname;

  if (request.method === 'GET' && pathname === '/healthz') {
    return send(response, 200, 'ok', { 'Content-Type': 'text/plain; charset=utf-8' });
  }

  if (request.method === 'POST' && pathname === '/login') {
    try {
      const form = await readBody(request);
      if (!safeEqual(form.get('username'), adminUsername) || !safeEqual(form.get('password'), adminPassword)) {
        return redirect(response, '/?error=1');
      }

      const token = crypto.randomBytes(32).toString('hex');
      sessions.set(token, Date.now() + 8 * 60 * 60 * 1000);
      return redirect(response, '/', {
        'Set-Cookie': `central_admin_session=${token}; HttpOnly; SameSite=Lax; Path=/; Max-Age=28800`,
      });
    } catch {
      return redirect(response, '/?error=1');
    }
  }

  if (request.method === 'POST' && pathname === '/logout') {
    const token = parseCookies(request).central_admin_session;
    if (token) sessions.delete(token);
    return redirect(response, '/', {
      'Set-Cookie': 'central_admin_session=; HttpOnly; SameSite=Lax; Path=/; Max-Age=0',
    });
  }

  if (request.method === 'GET' && pathname === '/api/status') {
    if (!isAuthenticated(request)) return send(response, 401, JSON.stringify({ error: 'Unauthenticated' }), { 'Content-Type': 'application/json' });
    const checked = await Promise.all(services.map(checkService));
    return send(response, 200, JSON.stringify({
      checkedAt: new Date().toISOString(),
      online: checked.filter((service) => service.status === 'online').length,
      total: checked.length,
      services: checked,
    }), { 'Content-Type': 'application/json' });
  }

  if (request.method === 'GET' && pathname === '/') {
    return servePage(response, isAuthenticated(request) ? 'dashboard.html' : 'login.html');
  }

  if (request.method === 'GET' && pathname === '/styles.css') {
    return serveAsset(response, 'styles.css', 'text/css; charset=utf-8');
  }

  if (request.method === 'GET' && pathname === '/dashboard.js') {
    return serveAsset(response, 'dashboard.js', 'application/javascript; charset=utf-8');
  }

  return send(response, 404, 'Not found', { 'Content-Type': 'text/plain; charset=utf-8' });
}

setInterval(() => {
  const now = Date.now();
  for (const [token, expiresAt] of sessions) {
    if (expiresAt <= now) sessions.delete(token);
  }
}, 60_000).unref();

http.createServer((request, response) => {
  handle(request, response).catch(() => send(response, 500, 'Internal server error'));
}).listen(port, '0.0.0.0', () => {
  console.log(`Central admin listening on port ${port}`);
});
