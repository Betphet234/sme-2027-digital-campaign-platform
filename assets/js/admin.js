const ADMIN_API_BASE = location.pathname.startsWith('/SME/') ? '/SME' : '';
let currentAdminUser = null;
let currentContentPosts = [];

function el(id) {
  return document.getElementById(id);
}

function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, ch => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[ch]));
}

function hasPermission(permission) {
  return Boolean(currentAdminUser?.permissions?.includes(permission));
}

function canSeeRecords() {
  return hasPermission('view_all_datasets') || hasPermission('view_applications') || hasPermission('view_community');
}

function canManageContent() {
  return hasPermission('manage_content');
}

async function api(path, options = {}) {
  const res = await fetch(`${ADMIN_API_BASE}${path}`, {
    credentials: 'same-origin',
    ...options
  });

  if (options.download) return res;

  const text = await res.text();
  let json = null;

  try {
    json = JSON.parse(text);
  } catch (e) {
    console.error('Raw server response:', text);
    alert('Backend did not return JSON. Raw response:\n\n' + text.substring(0, 800));
    throw new Error('Server returned an invalid response.');
  }

  if (!res.ok || json.success === false) {
    throw new Error(json.message || 'Request failed.');
  }

  return json;
}

function applyRoleControls() {
  if (el('userRoleBadge')) {
    el('userRoleBadge').textContent = currentAdminUser ? `${currentAdminUser.name} · ${currentAdminUser.roleLabel}` : '';
  }

  const recordsSection = el('recordsSection');
  const contentSection = el('contentSection');

  if (recordsSection) recordsSection.classList.toggle('hidden', !canSeeRecords());
  if (contentSection) contentSection.classList.toggle('hidden', !canManageContent());

  const datasetSelect = el('datasetSelect');
  if (datasetSelect) {
    [...datasetSelect.options].forEach(option => {
      const v = option.value;
      let allowed = false;
      if (hasPermission('view_all_datasets')) allowed = true;
      if (v === 'applications' && hasPermission('view_applications')) allowed = true;
      if (['needs','volunteers','supporters','messages'].includes(v) && hasPermission('view_community')) allowed = true;
      option.hidden = !allowed;
      option.disabled = !allowed;
    });
    const firstAllowed = [...datasetSelect.options].find(o => !o.disabled);
    if (firstAllowed) datasetSelect.value = firstAllowed.value;
  }
}

async function updateStats() {
  const grid = el('statsGrid');
  if (!grid) return;

  const json = await api('/api/admin/stats.php');
  if (json.user) currentAdminUser = json.user;

  grid.innerHTML = Object.entries(json.stats)
    .map(([name, count]) => `
      <article>
        <span>${escapeHtml(name.replace('_', ' '))}</span>
        <strong>${escapeHtml(count)}</strong>
      </article>
    `)
    .join('');
}

function duplicateText(record) {
  if (!record.duplicate_count || record.duplicate_count < 1) return '';
  const reasons = Array.isArray(record.duplicate_reasons) ? record.duplicate_reasons.join(', ') : '';
  return `Possible duplicate${reasons ? ': ' + reasons : ''}`;
}

async function renderTable() {
  const table = el('adminTable');
  if (!table || !canSeeRecords()) return;

  const dataset = el('datasetSelect').value;
  const params = new URLSearchParams({
    dataset,
    q: el('adminSearch').value,
    status: el('statusFilter').value
  });

  const json = await api(`/api/admin/list.php?${params.toString()}`);
  if (json.user) currentAdminUser = json.user;
  const records = json.records || [];
  const canUpdate = Boolean(json.canUpdate);

  table.querySelector('thead').innerHTML = `
    <tr>
      <th>Reference</th>
      <th>Name / Category</th>
      <th>Community / Ward</th>
      <th>Phone</th>
      <th>Status</th>
      <th>Notes</th>
      <th>Duplicate</th>
      <th>Date</th>
    </tr>
  `;

  table.querySelector('tbody').innerHTML = records.map(r => {
    const statusOpts = [
      'Application Received',
      'Received',
      'Under Review',
      'Additional Information Required',
      'Shortlisted',
      'Verification Stage',
      'Referred to Partner',
      'Approved',
      'Not Selected',
      'Closed'
    ].map(s => `<option ${r.status === s ? 'selected' : ''}>${escapeHtml(s)}</option>`).join('');

    return `
      <tr>
        <td><strong>${escapeHtml(r.reference)}</strong></td>
        <td>
          ${escapeHtml(r.name || 'Record')}
          <br>
          <small>${escapeHtml(r.category || r.type || '')}</small>
        </td>
        <td>
          ${escapeHtml(r.community || '')}
          <br>
          <small>${escapeHtml(r.ward || '')}</small>
        </td>
        <td>${escapeHtml(r.phone || '')}</td>
        <td>
          <select data-ref="${escapeHtml(r.reference)}" class="status-select" ${canUpdate ? '' : 'disabled'}>
            ${statusOpts}
          </select>
        </td>
        <td>
          <textarea data-ref="${escapeHtml(r.reference)}" class="note-input" rows="2" ${canUpdate ? '' : 'disabled'}>${escapeHtml(r.internal_notes || '')}</textarea>
        </td>
        <td>${escapeHtml(duplicateText(r))}</td>
        <td>${r.createdAt ? escapeHtml(new Date(r.createdAt).toLocaleDateString()) : ''}</td>
      </tr>
    `;
  }).join('') || '<tr><td colspan="8">No records yet.</td></tr>';

  if (!canUpdate) return;

  document.querySelectorAll('.status-select').forEach(sel => {
    sel.addEventListener('change', async e => {
      await api('/api/admin/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          dataset,
          reference: e.target.dataset.ref,
          status: e.target.value
        })
      });

      await renderTable();
      await updateStats();
    });
  });

  document.querySelectorAll('.note-input').forEach(input => {
    input.addEventListener('change', async e => {
      await api('/api/admin/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          dataset,
          reference: e.target.dataset.ref,
          internal_notes: e.target.value
        })
      });
    });
  });
}

function exportReport(format) {
  const dataset = el('datasetSelect').value;
  const params = new URLSearchParams({ dataset, format });
  window.location.href = `${ADMIN_API_BASE}/api/admin/export.php?${params.toString()}`;
}


function splitEventDateTime(value) {
  if (!value) return { date: '', time: '' };
  const safe = String(value).replace(' ', 'T');
  const d = new Date(safe);
  if (Number.isNaN(d.getTime())) {
    const parts = String(value).split(/[ T]/);
    return { date: parts[0] || '', time: (parts[1] || '').slice(0, 5) };
  }
  const pad = n => String(n).padStart(2, '0');
  return {
    date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
    time: `${pad(d.getHours())}:${pad(d.getMinutes())}`
  };
}

function toggleEventFields() {
  const fields = el('eventFields');
  const type = el('contentType')?.value;
  if (!fields) return;
  fields.classList.toggle('hidden', type !== 'event');
}

function resetContentForm() {
  const form = el('contentForm');
  if (!form) return;
  form.reset();
  el('contentId').value = '';
  el('existingFeaturedImage').value = '';
  if (el('contentEventDate')) el('contentEventDate').value = '';
  if (el('contentEventTime')) el('contentEventTime').value = '';
  if (el('contentEventLocation')) el('contentEventLocation').value = '';
  toggleEventFields();
  el('contentSaveBtn').textContent = 'Publish / Save Content';
}

async function loadContent() {
  if (!canManageContent()) return;
  const json = await api('/api/admin/content_list.php');
  currentContentPosts = json.posts || [];
  const table = el('contentTable');
  if (!table) return;

  table.querySelector('thead').innerHTML = `
    <tr>
      <th>Title</th>
      <th>Type</th>
      <th>Status</th>
      <th>Date</th>
      <th>Action</th>
    </tr>
  `;

  table.querySelector('tbody').innerHTML = currentContentPosts.map(post => `
    <tr>
      <td><strong>${escapeHtml(post.title)}</strong><br><small>${escapeHtml(post.slug)}</small></td>
      <td>${escapeHtml(post.type)}</td>
      <td>${post.is_published == 1 ? 'Published' : 'Draft'}</td>
      <td>${post.type === 'event' && post.event_date ? escapeHtml(new Date(String(post.event_date).replace(' ', 'T')).toLocaleString()) : (post.created_at ? escapeHtml(new Date(post.created_at).toLocaleDateString()) : '')}</td>
      <td>
        <button class="btn btn-secondary content-edit" data-id="${post.id}" type="button">Edit</button>
        <button class="btn btn-danger content-delete" data-id="${post.id}" type="button">Delete</button>
      </td>
    </tr>
  `).join('') || '<tr><td colspan="5">No news/media content yet.</td></tr>';

  document.querySelectorAll('.content-edit').forEach(btn => {
    btn.addEventListener('click', () => editContent(Number(btn.dataset.id)));
  });

  document.querySelectorAll('.content-delete').forEach(btn => {
    btn.addEventListener('click', () => deleteContent(Number(btn.dataset.id)));
  });
}

function editContent(id) {
  const post = currentContentPosts.find(p => Number(p.id) === id);
  if (!post) return;
  el('contentId').value = post.id;
  el('contentTitle').value = post.title || '';
  el('contentType').value = post.type || 'news';
  el('contentExcerpt').value = post.excerpt || '';
  el('contentBody').value = post.body || '';
  el('contentPublished').checked = Number(post.is_published) === 1;
  el('existingFeaturedImage').value = post.featured_image || '';
  const eventParts = splitEventDateTime(post.event_date);
  if (el('contentEventDate')) el('contentEventDate').value = eventParts.date;
  if (el('contentEventTime')) el('contentEventTime').value = eventParts.time;
  if (el('contentEventLocation')) el('contentEventLocation').value = post.event_location || '';
  toggleEventFields();
  el('contentSaveBtn').textContent = 'Update Content';
  el('contentForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function deleteContent(id) {
  if (!confirm('Delete this content item?')) return;
  await api('/api/admin/content_delete.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });
  await loadContent();
}

function wireContentForm() {
  const form = el('contentForm');
  if (!form) return;

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const btn = el('contentSaveBtn');
    const original = btn.textContent;
    try {
      btn.disabled = true;
      btn.textContent = 'Saving...';
      const body = new FormData(form);
      await api('/api/admin/content_save.php', {
        method: 'POST',
        body
      });
      resetContentForm();
      await loadContent();
      alert('Content saved.');
    } catch (err) {
      alert(err.message || 'Could not save content.');
    } finally {
      btn.disabled = false;
      btn.textContent = original === 'Saving...' ? 'Publish / Save Content' : original;
    }
  });

  el('contentType')?.addEventListener('change', toggleEventFields);
  toggleEventFields();
  el('contentResetBtn')?.addEventListener('click', resetContentForm);
}

async function adminInit() {
  const btn = el('adminLoginBtn');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    try {
      btn.disabled = true;
      btn.textContent = 'Signing in...';

      const login = await api('/api/admin/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username: el('adminUser').value,
          password: el('adminPass').value
        })
      });

      currentAdminUser = login.user;
      el('adminLogin').classList.add('hidden');
      el('adminPanel').classList.remove('hidden');
      applyRoleControls();

      await updateStats();
      applyRoleControls();
      if (canSeeRecords()) await renderTable();
      if (canManageContent()) await loadContent();

    } catch (err) {
      alert(err.message || 'Login failed.');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Enter Dashboard';
    }
  });

  ['datasetSelect', 'adminSearch', 'statusFilter'].forEach(id => {
    el(id)?.addEventListener('input', () => renderTable().catch(err => alert(err.message)));
  });

  el('exportCsv')?.addEventListener('click', () => exportReport('csv'));
  el('exportExcel')?.addEventListener('click', () => exportReport('xlsx'));
  wireContentForm();
}

document.addEventListener('DOMContentLoaded', adminInit);
