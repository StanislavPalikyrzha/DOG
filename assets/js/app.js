const state = {
  user: null,
  templates: [],
  documents: [],
  imports: [],
};

const byId = (id) => document.getElementById(id);

async function api(action, options = {}) {
  const response = await fetch(`api.php?action=${action}`, {
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });

  const contentType = response.headers.get('content-type') || '';
  const data = contentType.includes('application/json') ? await response.json() : {};

  if (!response.ok || data.ok === false) {
    throw new Error(data.error || `Request failed for ${action}.`);
  }

  return data;
}

function setFeedback(id, message, isError = false) {
  const node = byId(id);
  node.textContent = message;
  node.style.color = isError ? '#a13a2f' : '#6d6256';
}

function toggleAppSections(showApp) {
  ['dashboard', 'documents', 'admin'].forEach((id) => byId(id).classList.toggle('hidden', !showApp));
  const canGenerate = Boolean(state.user && ['admin', 'editor'].includes(state.user.role));
  document.querySelector('.two-column').classList.toggle('hidden', !showApp || !canGenerate);
  byId('logout-button').hidden = !showApp;
  byId('login-panel').classList.toggle('hidden', showApp);
}

function renderStats(stats) {
  byId('stats-grid').innerHTML = `
    <article class="stat-card"><strong>${stats.templates}</strong><span>Templates</span></article>
    <article class="stat-card"><strong>${stats.documents}</strong><span>Generated documents</span></article>
    <article class="stat-card"><strong>${stats.imports}</strong><span>Import jobs</span></article>
    <article class="stat-card"><strong>${stats.users}</strong><span>Registered users</span></article>
  `;
}

function renderTemplates(templates) {
  state.templates = templates;
  const select = byId('template-select');
  select.innerHTML = templates.map((template) => `<option value="${template.id}">${template.name} (${template.category})</option>`).join('');
}

function renderDocuments(documents) {
  state.documents = documents;
  const list = byId('documents-list');
  if (!documents.length) {
    list.innerHTML = '<p class="placeholder">No generated documents yet.</p>';
    return;
  }

  list.innerHTML = documents.map((doc) => `
    <article class="document-card">
