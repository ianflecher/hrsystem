const grid = document.querySelector('#service-grid');
const onlineCount = document.querySelector('#online-count');
const checkedAt = document.querySelector('#checked-at');
const loadError = document.querySelector('#load-error');

function render(health) {
  onlineCount.textContent = `${health.online} / ${health.total} online`;
  checkedAt.textContent = `Last checked ${new Date(health.checkedAt).toLocaleTimeString()}`;
  grid.innerHTML = health.services.map((service) => `
    <article class="service-card accent-${service.accent}">
      <div class="service-head">
        <span class="service-icon">${service.icon}</span>
        <span class="status status-${service.status}"><span class="status-dot"></span>${service.status}</span>
      </div>
      <h2>${service.name}</h2>
      <p>${service.description}</p>
      <div class="service-meta">${service.httpStatus ? `HTTP ${service.httpStatus}` : service.error || 'No response'} · ${service.latencyMs} ms</div>
      <a class="button button-open" href="${service.url}" target="_blank" rel="noreferrer">Open ${service.name}</a>
    </article>
  `).join('');
}

async function loadStatus() {
  try {
    const response = await fetch('/api/status', { cache: 'no-store' });
    if (response.status === 401) {
      window.location.href = '/';
      return;
    }
    if (!response.ok) throw new Error('Status request failed');
    render(await response.json());
    loadError.hidden = true;
  } catch {
    loadError.hidden = false;
    onlineCount.textContent = 'Status unavailable';
    checkedAt.textContent = 'Retrying automatically';
  }
}

loadStatus();
setInterval(loadStatus, 30_000);
