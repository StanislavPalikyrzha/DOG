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
