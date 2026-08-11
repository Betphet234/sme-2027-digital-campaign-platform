const FIELD_OPTION_LISTS = {
  QUALIFICATIONS: [
    'No formal education',
    'Primary School Leaving Certificate',
    'Junior Secondary School Certificate',
    'SSCE / WAEC',
    'SSCE / NECO',
    'GCE',
    'Trade Test / Vocational Certificate',
    'NABTEB',
    'NCE',
    'OND',
    'HND',
    'Bachelor\'s Degree',
    'Postgraduate Diploma',
    'Master\'s Degree',
    'PhD / Doctorate',
    'Professional Qualification',
    'Other'
  ],

  NIGERIAN_UNIVERSITIES: [
    'Ambrose Alli University, Ekpoma',
    'University of Benin',
    'University of Ibadan',
    'University of Lagos',
    'University of Nigeria, Nsukka',
    'University of Port Harcourt',
    'University of Abuja, Gwagwalada',
    'Ahmadu Bello University, Zaria',
    'Obafemi Awolowo University, Ile-Ife',
    'University of Ilorin',
    'University of Calabar',
    'University of Jos',
    'University of Maiduguri',
    'University of Uyo',
    'Nnamdi Azikiwe University, Awka',
    'Bayero University, Kano',
    'Federal University of Technology, Akure',
    'Federal University of Technology, Minna',
    'Federal University of Technology, Owerri',
    'National Open University of Nigeria, Abuja',
    'Benson Idahosa University, Benin City',
    'Glorious Vision University, Ogwa, Edo State',
    'Mudiame University, Irrua, Edo State',
    'Lighthouse University, Evbuobanosa, Edo State',
    'Other Nigerian University / Institution not listed'
  ]
};

const FORM_DEFINITIONS = {
  job: {
    label: 'Job Seekers',
    fields: [
      ['Full Name', 'text', 'required'],
      ['Phone Number', 'tel', 'required'],
      ['WhatsApp Number', 'tel', ''],
      ['Email Address', 'email', ''],
      ['Community', 'select:Irrua|Ewu|Opoji|Ugbegun', 'required'],
      ['Ward', 'text', 'required'],
      ['Residential Address', 'text', 'required'],
      ['Age or Date of Birth', 'text', 'required'],
      ['Highest Qualification', 'selectList:QUALIFICATIONS', 'required'],
      ['Course of Study', 'text', ''],
      ['Institution Attended', 'selectList:NIGERIAN_UNIVERSITIES', ''],
      ['Professional Qualifications', 'text', ''],
      ['Skills', 'textarea', 'required'],
      ['Work Experience', 'textarea', ''],
      ['Current Employment Status', 'select:Unemployed|Self-employed|Employed part-time|Employed full-time|Student|Apprentice', 'required'],
      ['Preferred Job Sector', 'select:Education|Healthcare|ICT|Administration|Agriculture|Engineering|Construction|Security|Driving|Logistics|Sales|Legal services|Hospitality|Skilled trades|Other', 'required'],
      ['Preferred Job Type', 'text', ''],
      ['CV Upload', 'file', ''],
      ['Certificate Upload', 'file', ''],
      ['Photograph', 'file:image/*', ''],
      ['Brief Personal Statement', 'textarea', 'required']
    ]
  },

  startup: {
    label: 'Business Startup Applicants',
    fields: [
      ['Applicant Name', 'text', 'required'],
      ['Phone Number', 'tel', 'required'],
      ['WhatsApp Number', 'tel', ''],
      ['Email Address', 'email', ''],
      ['Community', 'select:Irrua|Ewu|Opoji|Ugbegun', 'required'],
      ['Ward', 'text', 'required'],
      ['Proposed Business', 'text', 'required'],
      ['Business Sector', 'text', 'required'],
      ['Proposed Location', 'text', ''],
      ['Business Idea Description', 'textarea', 'required'],
      ['Previous Experience', 'textarea', ''],
      ['Estimated Startup Cost', 'number', ''],
      ['Amount Requested', 'number', ''],
      ['Equipment Required', 'textarea', ''],
      ['Training Required', 'textarea', ''],
      ['Expected Jobs Created', 'number', ''],
      ['Business Plan Upload', 'file', '']
    ]
  },

  expansion: {
    label: 'Existing Businesses Seeking Expansion',
    fields: [
      ['Owner Name', 'text', 'required'],
      ['Phone Number', 'tel', 'required'],
      ['Email Address', 'email', ''],
      ['Business Name', 'text', 'required'],
      ['Business Address', 'text', 'required'],
      ['Community', 'select:Irrua|Ewu|Opoji|Ugbegun', 'required'],
      ['Ward', 'text', 'required'],
      ['Type of Business', 'text', 'required'],
      ['Year Established', 'number', ''],
      ['Number of Employees', 'number', ''],
      ['Products or Services', 'textarea', ''],
      ['Current Challenges', 'textarea', 'required'],
      ['Capital Required', 'number', ''],
      ['Equipment Required', 'textarea', ''],
      ['Purpose of Expansion', 'textarea', ''],
      ['Expected Additional Jobs', 'number', ''],
      ['CAC Details', 'text', ''],
      ['Business Photographs', 'file:image/*', ''],
      ['Proposal or Business Plan Upload', 'file', '']
    ]
  },

  apprentice: {
    label: 'Newly Freed Apprentices',
    fields: [
      ['Full Name', 'text', 'required'],
      ['Phone Number', 'tel', 'required'],
      ['WhatsApp Number', 'tel', ''],
      ['Email Address', 'email', ''],
      ['Community', 'select:Irrua|Ewu|Opoji|Ugbegun', 'required'],
      ['Ward', 'text', 'required'],
      ['Trade Learned', 'select:Tailoring|Fashion design|Hairdressing|Barbing|Welding|Carpentry|Electrical work|Plumbing|Automobile repairs|Catering|Baking|Phone repairs|Computer repairs|Photography|Shoemaking|Tiling|Painting|Agriculture|Food processing|Other', 'required'],
      ['Name of Apprenticeship Master', 'text', 'required'],
      ['Location of Apprenticeship', 'text', 'required'],
      ['Duration of Training', 'text', ''],
      ['Date of Completion or Freedom', 'date', ''],
      ['Evidence of Completion', 'file', ''],
      ['Tools and Equipment Required', 'textarea', 'required'],
      ['Estimated Cost', 'number', ''],
      ['Workspace Requirement', 'textarea', ''],
      ['Applicant Photograph', 'file:image/*', ''],
      ['Photos or Samples of Previous Work', 'file:image/*', ''],
      ['Brief Explanation of Support Required', 'textarea', 'required']
    ]
  }
};

const API_BASE = location.pathname.startsWith('/SME/') ? '/SME' : '';

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, function (char) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char];
  });
}

function escapeAttr(value) {
  return escapeHtml(value);
}

function normalisePhone(phone) {
  return String(phone || '').replace(/\D/g, '').replace(/^234/, '0');
}

function acknowledgementUrl(reference, phone, download = false) {
  const params = new URLSearchParams({
    reference: String(reference || '').toUpperCase(),
    phone: normalisePhone(phone || '')
  });
  if (download) params.set('download', '1');
  return `${API_BASE}/api/acknowledgement.php?${params.toString()}`;
}

function formMessage(form, text, ok = true) {
  let box = form.querySelector('.form-response');

  if (!box) {
    box = document.createElement('div');
    box.className = 'form-response';
    form.appendChild(box);
  }

  box.className = `form-response ${ok ? 'success' : 'error'}`;
  box.innerHTML = text;
  box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function fieldTemplate(field) {
  const [label, type, required] = field;
  const req = required === 'required' ? 'required' : '';
  const name = label;
  const full = type === 'textarea' || type.startsWith('file') ? 'full' : '';

  if (type.startsWith('selectList:')) {
    const listName = type.replace('selectList:', '');
    const options = (FIELD_OPTION_LISTS[listName] || [])
      .map(option => `<option value="${escapeAttr(option)}">${escapeHtml(option)}</option>`)
      .join('');

    return `
      <label>
        ${escapeHtml(label)}
        <select name="${escapeAttr(name)}" ${req}>
          <option value="">Select</option>
          ${options}
        </select>
      </label>
    `;
  }

  if (type.startsWith('select:')) {
    const options = type.replace('select:', '')
      .split('|')
      .map(option => `<option value="${escapeAttr(option)}">${escapeHtml(option)}</option>`)
      .join('');

    return `
      <label>
        ${escapeHtml(label)}
        <select name="${escapeAttr(name)}" ${req}>
          <option value="">Select</option>
          ${options}
        </select>
      </label>
    `;
  }

  if (type === 'textarea') {
    return `
      <label class="${full}">
        ${escapeHtml(label)}
        <textarea name="${escapeAttr(name)}" rows="4" ${req}></textarea>
      </label>
    `;
  }

  if (type.startsWith('file')) {
    const accept = type.includes(':') ? ` accept="${escapeAttr(type.split(':')[1])}"` : '';

    return `
      <label class="${full}">
        ${escapeHtml(label)}
        <input name="${escapeAttr(name)}" type="file"${accept}>
      </label>
    `;
  }

  return `
    <label class="${full}">
      ${escapeHtml(label)}
      <input name="${escapeAttr(name)}" type="${escapeAttr(type)}" ${req}>
    </label>
  `;
}

function setOpportunityForm(key) {
  const definition = FORM_DEFINITIONS[key];

  if (!definition) return;

  const categoryInput = document.getElementById('applicationCategory');
  const dynamicFields = document.getElementById('dynamicFields');

  if (categoryInput) {
    categoryInput.value = definition.label;
  }

  if (dynamicFields) {
    dynamicFields.innerHTML = definition.fields.map(fieldTemplate).join('');
  }

  document.querySelectorAll('.tab-button').forEach(button => {
    button.classList.toggle('active', button.dataset.form === key);
  });
}

async function submitToBackend(form) {
  const body = new FormData(form);

  body.append('submission_type', form.dataset.type || 'message');
  body.append('store', form.dataset.store || 'messages');

  const response = await fetch(`${API_BASE}/api/submit.php`, {
    method: 'POST',
    body,
    credentials: 'same-origin'
  });

  const text = await response.text();

  let json = null;

  try {
    json = JSON.parse(text);
  } catch (error) {
    console.error('Raw submit.php response:', text);
    throw new Error('The server returned an invalid response. Check api/submit.php and database connection.');
  }

  if (!response.ok || !json.success) {
    throw new Error(json.message || 'Submission failed.');
  }

  return json;
}

async function submitNewsletter(form) {
  const email = form.querySelector('input[type="email"]')?.value || '';

  const response = await fetch(`${API_BASE}/api/newsletter.php`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ email }),
    credentials: 'same-origin'
  });

  const text = await response.text();

  let json = null;

  try {
    json = JSON.parse(text);
  } catch (error) {
    console.error('Raw newsletter.php response:', text);
    throw new Error('Newsletter server response was invalid.');
  }

  if (!response.ok || !json.success) {
    throw new Error(json.message || 'Newsletter subscription failed.');
  }

  return json;
}

function wireOpportunityTabs() {
  document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      setOpportunityForm(button.dataset.form || 'job');
    });
  });

  if (document.getElementById('dynamicFields')) {
    const activeType = document.querySelector('.tab-button.active')?.dataset.form || 'job';
    setOpportunityForm(activeType);
  }
}

function wireSmartForms() {
  document.querySelectorAll('form.smart-form:not(.no-save):not(#newsletterForm)').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault();

      if (!form.reportValidity()) return;

      const submitButton = form.querySelector('button[type="submit"]');
      const originalButtonText = submitButton ? submitButton.textContent : '';

      try {
        if (submitButton) {
          submitButton.disabled = true;
          submitButton.textContent = 'Submitting...';
        }

        const result = await submitToBackend(form);
        const submission = result.submission || {};

        sessionStorage.setItem('lastSubmission', JSON.stringify(submission));

        const slipUrl = acknowledgementUrl(submission.reference, submission.phone, false);
        const slipDownloadUrl = acknowledgementUrl(submission.reference, submission.phone, true);

        formMessage(
          form,
          `
          <strong>Thank you. Your submission has been received.</strong><br>
          <strong>Reference:</strong> ${escapeHtml(submission.reference || '')}<br>
          Use this reference number with your phone number on the status page.<br><br>
          <a class="btn" href="${escapeAttr(slipUrl)}" target="_blank">View / Print Acknowledgement Slip</a>
          <a class="btn btn-secondary" href="${escapeAttr(slipDownloadUrl)}">Download Slip</a>
          `,
          true
        );

        form.reset();

        if (document.getElementById('dynamicFields')) {
          const activeType = document.querySelector('.tab-button.active')?.dataset.form || 'job';
          setOpportunityForm(activeType);
        }

      } catch (error) {
        formMessage(
          form,
          `
          <strong>Submission not completed.</strong><br>
          ${escapeHtml(error.message)}<br>
          <small>If this continues, check api/submit.php, database details, and table setup.</small>
          `,
          false
        );
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalButtonText;
        }
      }
    });
  });
}

function wireNewsletterForm() {
  const form = document.getElementById('newsletterForm');

  if (!form) return;

  form.addEventListener('submit', async event => {
    event.preventDefault();

    if (!form.reportValidity()) return;

    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton ? submitButton.textContent : '';

    try {
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Subscribing...';
      }

      const result = await submitNewsletter(form);

      formMessage(
        form,
        escapeHtml(result.message || 'Subscription received.'),
        true
      );

      form.reset();

    } catch (error) {
      formMessage(
        form,
        escapeHtml(error.message || 'Could not save newsletter subscription.'),
        false
      );
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
      }
    }
  });
}

function wireThankYouPage() {
  const thankYouBox = document.getElementById('thankYouDetails');

  if (!thankYouBox) return;

  const record = JSON.parse(sessionStorage.getItem('lastSubmission') || 'null');

  if (!record) {
    thankYouBox.innerHTML = '<p>No recent submission found in this browser.</p>';
    return;
  }

  thankYouBox.innerHTML = `
    <div class="ack-slip">
      <p class="ref-badge">Reference: ${escapeHtml(record.reference || '')}</p>
      <h2>Submission received</h2>
      <p>Status: <strong>${escapeHtml(record.status || 'Application Received')}</strong></p>
      <p>Date: ${record.createdAt ? escapeHtml(new Date(record.createdAt).toLocaleString()) : ''}</p>
      <p>Keep this reference number. You can check your status using your reference number and phone number.</p>
      <a class="btn" href="${escapeAttr(acknowledgementUrl(record.reference, record.phone, false))}" target="_blank">View / Print Acknowledgement Slip</a>
      <a class="btn btn-secondary" href="${escapeAttr(acknowledgementUrl(record.reference, record.phone, true))}">Download Slip</a>
      <a class="btn btn-secondary" href="application-status.html">Check Status</a>
    </div>
  `;
}

function wireStatusForm() {
  const statusForm = document.getElementById('statusForm');

  if (!statusForm) return;

  statusForm.addEventListener('submit', async event => {
    event.preventDefault();

    const reference = document.getElementById('statusReference')?.value.trim().toUpperCase() || '';
    const phone = normalisePhone(document.getElementById('statusPhone')?.value || '');
    const resultBox = document.getElementById('statusResult');

    if (!resultBox) return;

    resultBox.innerHTML = '<p>Checking...</p>';

    try {
      const params = new URLSearchParams({
        reference,
        phone
      });

      const response = await fetch(`${API_BASE}/api/status.php?${params.toString()}`, {
        credentials: 'same-origin'
      });

      const text = await response.text();

      let json = null;

      try {
        json = JSON.parse(text);
      } catch (error) {
        console.error('Raw status.php response:', text);
        throw new Error('The status server returned an invalid response.');
      }

      if (!response.ok || !json.success) {
        throw new Error(json.message || 'No matching record found.');
      }

      const found = json.submission || {};

      resultBox.innerHTML = `
        <div class="ack-slip">
          <p class="ref-badge">${escapeHtml(found.reference || '')}</p>
          <h2>${escapeHtml(found.name || 'Applicant')}</h2>
          <p><strong>Status:</strong> ${escapeHtml(found.status || 'Application Received')}</p>
          <p><strong>Category:</strong> ${escapeHtml(found.category || found.type || 'Submission')}</p>
          <p><strong>Submitted:</strong> ${found.createdAt ? escapeHtml(new Date(found.createdAt).toLocaleString()) : ''}</p>
          <p>Keep your reference number safe. The campaign team may contact you if additional information is required.</p>
          <a class="btn" href="${escapeAttr(acknowledgementUrl(found.reference, found.phone, false))}" target="_blank">View / Print Acknowledgement Slip</a>
          <a class="btn btn-secondary" href="${escapeAttr(acknowledgementUrl(found.reference, found.phone, true))}">Download Slip</a>
        </div>
      `;

    } catch (error) {
      resultBox.innerHTML = `<p>${escapeHtml(error.message)}</p>`;
    }
  });
}

function initForms() {
  wireOpportunityTabs();
  wireSmartForms();
  wireNewsletterForm();
  wireThankYouPage();
  wireStatusForm();
}

document.addEventListener('DOMContentLoaded', initForms);