var currentUser = null;
var authToken = localStorage.getItem('dog_jwt') || '';

function byId(id) {
  return document.getElementById(id);
}

function withAuthToken(url) {
  if (!authToken) {
    return url;
  }

  if (url.indexOf('token=') !== -1) {
    return url;
  }

  if (url.indexOf('?') === -1) {
    return url + '?token=' + encodeURIComponent(authToken);
  }

  return url + '&token=' + encodeURIComponent(authToken);
}

async function api(action, options) {
  var requestOptions = options || {};
  var headers = { 'Content-Type': 'application/json' };

  if (authToken) {
    headers.Authorization = 'Bearer ' + authToken;
  }

  var response = await fetch('api.php?action=' + action, {
    credentials: 'same-origin',
    headers: headers,
    method: requestOptions.method || 'GET',
    body: requestOptions.body || null,
  });
  var contentType = response.headers.get('content-type') || '';
  var data = {};

  if (contentType.indexOf('application/json') !== -1) {
    data = await response.json();
  }

  if (!response.ok || data.ok === false) {
    throw new Error(data.error || 'Request failed for ' + action + '.');
  }

  return data;
}

function setFeedback(id, message, isError) {
  var node = byId(id);
  node.textContent = message;
  node.style.color = isError ? '#a13a2f' : '#6d6256';
}

function toggleAppSections(showApp) {
  var dashboard = byId('dashboard');
  var documents = byId('documents');
  var admin = byId('admin');
  var workArea = document.querySelector('.two-column');
  var canGenerate = currentUser && (currentUser.role === 'admin' || currentUser.role === 'editor');

  dashboard.classList.toggle('hidden', !showApp);
  documents.classList.toggle('hidden', !showApp);
  admin.classList.toggle('hidden', !showApp);
  workArea.classList.toggle('hidden', !showApp || !canGenerate);
  byId('logout-button').hidden = !showApp;
  byId('login-panel').classList.toggle('hidden', showApp);
}

function renderStats(stats) {
  var html = '';
  html += '<article class="stat-card"><strong>' + stats.templates + '</strong><span>Templates</span></article>';
  html += '<article class="stat-card"><strong>' + stats.documents + '</strong><span>Generated documents</span></article>';
  html += '<article class="stat-card"><strong>' + stats.imports + '</strong><span>Import jobs</span></article>';
  html += '<article class="stat-card"><strong>' + stats.users + '</strong><span>Registered users</span></article>';
  byId('stats-grid').innerHTML = html;
}

function renderTemplates(templates) {
  var select = byId('template-select');
  var html = '';
  var i;

  for (i = 0; i < templates.length; i += 1) {
    html += '<option value="' + templates[i].id + '">' + templates[i].name + ' (' + templates[i].category + ')</option>';
  }

  select.innerHTML = html;
}

function renderDocuments(documents) {
  var list = byId('documents-list');
  var html = '';
  var i;
  var doc;

  if (!documents.length) {
    list.innerHTML = '<p class="placeholder">No generated documents yet.</p>';
    return;
  }

  for (i = 0; i < documents.length; i += 1) {
    doc = documents[i];
    html += '<article class="document-card">';
    html += '<h4>' + doc.title + '</h4>';
    html += '<div class="document-meta">';
    html += '<span>Template: ' + doc.template_name + '</span>';
    html += '<span>Source: ' + doc.source_type + '</span>';
    html += '<span>Author: ' + doc.author + '</span>';
    html += '<span>' + new Date(doc.created_at).toLocaleString() + '</span>';
    html += '</div>';
    html += '<div class="link-row">';
    html += '<a class="button secondary" href="' + withAuthToken('api.php?action=download_json&id=' + doc.id) + '" target="_blank" rel="noreferrer">Open JSON</a>';
    html += '<a class="button secondary" href="' + withAuthToken('api.php?action=download_html&id=' + doc.id) + '" target="_blank" rel="noreferrer">Open HTML</a>';
    html += '<a class="button secondary" href="' + withAuthToken('api.php?action=download_pdf&id=' + doc.id) + '" target="_blank" rel="noreferrer">Open PDF</a>';
    html += '</div>';
    html += '</article>';
  }

  list.innerHTML = html;
}

function attachRoleButtons() {
  var buttons = document.querySelectorAll('[data-save-user]');
  var i;

  for (i = 0; i < buttons.length; i += 1) {
    buttons[i].addEventListener('click', async function () {
      var userId = this.getAttribute('data-save-user');
      var roleField = document.querySelector('[data-user-role="' + userId + '"]');

      await api('user_role', {
        method: 'POST',
        body: JSON.stringify({
          id: Number(userId),
          role: roleField.value,
        }),
      });

      await loadAdmin();
    });
  }
}

function renderAdmin(users, audit, imports) {
  var adminPanel = byId('admin');
  var roles = ['admin', 'editor', 'viewer'];
  var userHtml = '';
  var auditHtml = '';
  var importHtml = '';
  var i;
  var j;

  if (!currentUser || currentUser.role !== 'admin') {
    adminPanel.classList.add('hidden');
    return;
  }

  adminPanel.classList.remove('hidden');
  userHtml += '<table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr></thead><tbody>';

  for (i = 0; i < users.length; i += 1) {
    userHtml += '<tr>';
    userHtml += '<td>' + users[i].display_name + '</td>';
    userHtml += '<td>' + users[i].email + '</td>';
    userHtml += '<td><select data-user-role="' + users[i].id + '">';

    for (j = 0; j < roles.length; j += 1) {
      userHtml += '<option value="' + roles[j] + '"';
      if (roles[j] === users[i].role) {
        userHtml += ' selected';
      }
      userHtml += '>' + roles[j] + '</option>';
    }

    userHtml += '</select></td>';
    userHtml += '<td><button type="button" class="button secondary" data-save-user="' + users[i].id + '">Save</button></td>';
    userHtml += '</tr>';
  }

  userHtml += '</tbody></table>';
  byId('users-table').innerHTML = userHtml;

  if (!audit.length) {
    auditHtml = '<p class="placeholder">No audit entries yet.</p>';
  } else {
    for (i = 0; i < audit.length; i += 1) {
      auditHtml += '<article>';
      auditHtml += '<h5>' + audit[i].action + '</h5>';
      auditHtml += '<p>' + audit[i].actor_email + '</p>';
      auditHtml += '<small>' + new Date(audit[i].created_at).toLocaleString() + ' - ' + audit[i].details + '</small>';
      auditHtml += '</article>';
    }
  }

  if (!imports.length) {
    importHtml = '<p class="placeholder">No imports yet.</p>';
  } else {
    for (i = 0; i < imports.length; i += 1) {
      importHtml += '<article>';
      importHtml += '<h5>' + imports[i].file_name + '</h5>';
      importHtml += '<p>' + imports[i].row_count + ' rows, status: ' + imports[i].status + '</p>';
      importHtml += '<small>' + new Date(imports[i].created_at).toLocaleString() + ' - ' + imports[i].notes + '</small>';
      importHtml += '</article>';
    }
  }

  byId('audit-list').innerHTML = auditHtml;
  byId('imports-list').innerHTML = importHtml;
  attachRoleButtons();
}

function setPreview(html, links) {
  var frame = byId('preview-frame');
  var jsonLink = byId('open-json-link');
  var htmlLink = byId('open-html-link');
  var pdfLink = byId('open-pdf-link');

  frame.innerHTML = html || '<p class="placeholder">No preview available.</p>';

  if (links) {
    jsonLink.href = withAuthToken(links.json);
    htmlLink.href = withAuthToken(links.html);
    pdfLink.href = withAuthToken(links.pdf);
    jsonLink.classList.remove('disabled');
    htmlLink.classList.remove('disabled');
    pdfLink.classList.remove('disabled');
    return;
  }

  jsonLink.href = '#';
  htmlLink.href = '#';
  pdfLink.href = '#';
  jsonLink.classList.add('disabled');
  htmlLink.classList.add('disabled');
  pdfLink.classList.add('disabled');
}

async function loadAdmin() {
  var data = await api('users');
  renderAdmin(data.users, data.audit, data.imports);
}

async function refreshBootstrap() {
  var data = await api('bootstrap');

  currentUser = data.user;
  renderStats(data.stats);
  renderTemplates(data.templates);
  renderDocuments(data.documents);

  if (currentUser) {
    byId('session-chip').textContent = currentUser.display_name + ' (' + currentUser.role + ')';
  } else {
    byId('session-chip').textContent = '';
  }

  toggleAppSections(Boolean(currentUser));

  if (currentUser && currentUser.role === 'admin') {
    await loadAdmin();
  }
}

async function handleLogin(event) {
  var formData;
  var data;

  event.preventDefault();
  formData = new FormData(event.currentTarget);

  try {
    data = await api('login', {
      method: 'POST',
      body: JSON.stringify({
        email: formData.get('email'),
        password: formData.get('password'),
      }),
    });
    authToken = data.token || '';
    localStorage.setItem('dog_jwt', authToken);
    currentUser = data.user;
    setFeedback('login-feedback', 'Logged in as ' + data.user.display_name + '.', false);
    await refreshBootstrap();
  } catch (error) {
    setFeedback('login-feedback', error.message, true);
  }
}

async function handleGenerate(event) {
  var formData;
  var mode;
  var data = {};
  var payload;
  var response;

  event.preventDefault();
  formData = new FormData(event.currentTarget);
  mode = formData.get('mode');

  if (mode === 'manual') {
    try {
      data = JSON.parse(String(formData.get('json_payload') || '{}'));
    } catch (error) {
      setFeedback('generate-feedback', 'JSON payload is invalid.', true);
      return;
    }
  }

  payload = {
    title: formData.get('title'),
    template_id: Number(formData.get('template_id')),
    mode: mode,
    data: data,
    csv: formData.get('csv_payload'),
    file_name: byId('csv-file').files.length ? byId('csv-file').files[0].name : 'inline.csv',
  };

  try {
    response = await api('generate', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    setPreview(response.preview_html, response.links);
    setFeedback('generate-feedback', 'Generated ' + response.created_count + ' document(s).', false);
    await refreshBootstrap();
  } catch (error) {
    setFeedback('generate-feedback', error.message, true);
  }
}

async function handleTemplateCreate(event) {
  var formData;
  var payload = {};
  var entries;
  var i;

  event.preventDefault();
  formData = new FormData(event.currentTarget);
  entries = Array.from(formData.entries());

  for (i = 0; i < entries.length; i += 1) {
    payload[entries[i][0]] = entries[i][1];
  }

  try {
    await api('template_create', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    setFeedback('template-feedback', 'Template saved successfully.', false);
    await refreshBootstrap();
  } catch (error) {
    setFeedback('template-feedback', error.message, true);
  }
}

function handleModeChange() {
  var isCsv = byId('mode-select').value === 'csv';
  byId('csv-field').classList.toggle('hidden', !isCsv);
  byId('csv-upload-field').classList.toggle('hidden', !isCsv);
}

function handleCsvUpload(event) {
  var file = event.target.files[0];
  var reader;

  if (!file) {
    return;
  }

  reader = new FileReader();
  reader.onload = function () {
    byId('csv-payload').value = String(reader.result || '');
  };
  reader.readAsText(file);
}

async function handleLogout() {
  await api('logout', { method: 'POST', body: JSON.stringify({}) });
  authToken = '';
  localStorage.removeItem('dog_jwt');
  currentUser = null;
  setPreview('', null);
  await refreshBootstrap();
}

document.addEventListener('DOMContentLoaded', async function () {
  byId('login-form').addEventListener('submit', handleLogin);
  byId('generate-form').addEventListener('submit', handleGenerate);
  byId('template-form').addEventListener('submit', handleTemplateCreate);
  byId('mode-select').addEventListener('change', handleModeChange);
  byId('csv-file').addEventListener('change', handleCsvUpload);
  byId('logout-button').addEventListener('click', handleLogout);

  handleModeChange();
  await refreshBootstrap();
});
