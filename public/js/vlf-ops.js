/*
 * Firm operations for public/vlf-fixed.html, backed by the Laravel API:
 * matter intake, matters lists, clients, court diary, deadlines, task status,
 * invoice lifecycle, document review and uploads, staff, settings, notifications.
 *
 * Loaded after vlf-api.js (which provides window.VLF). Screens that were static
 * HTML in the prototype are re-rendered here from the saved data.
 */
(function () {
  'use strict';
  if (!window.VLF) return;

  const api = (...args) => VLF.api(...args);
  const saveFailed = err => VLF.saveFailed(err);
  const EQUITY = 'KSC-2026-0891';
  const BUILTIN_DOCS = new Set(['witness-statement', 'plaint']);
  const S = { clients: [], events: [], deadlines: [], staff: [], settings: {}, notifications: {} };

  /* ── helpers ── */

  const ic = (name, size) => (window.vlfIcon ? window.vlfIcon(name, size) : '');
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  // A value for an inline onclick argument: JSON-quoted, then HTML-escaped for the attribute.
  const arg = v => esc(JSON.stringify(String(v)));
  const val = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };
  const money = n => 'UGX ' + Number(n || 0).toLocaleString();
  const today = () => new Date().toISOString().slice(0, 10);
  const matters = () => Object.values(VLF.matters || {});
  const matter = ref => (VLF.matters || {})[ref];
  const firmName = () => S.settings.firm_name || 'GAVEL.CO';

  // The signed-in account, injected by the server when it serves the page.
  const ME = window.VLF_USER || {};
  const me = () => ME.name || VLF.persona().name;
  const myStaff = () => S.staff.find(s => s.userId === ME.id);

  const staffMember = name => S.staff.find(s => s.name === name);
  // Partner status comes from the signed-in account's role (the server checks it again).
  const isPartner = name => name === me() ? ME.role === 'partner' : /Partner/.test((staffMember(name) || {}).role || '');
  const advocates = () => S.staff.filter(s => s.active && !/Administrator/.test(s.role));
  const partners = () => S.staff.filter(s => s.active && /Partner/.test(s.role));

  function modal(title, sub, body) {
    const wrap = document.getElementById('ob-workspace-wrap');
    if (!wrap) return;
    wrap.innerHTML = `
      <div style="background:var(--ink);padding:16px 20px;border-radius:20px 20px 0 0;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
        <div><div style="font-family:var(--serif);font-size:18px;font-weight:400;color:var(--white);">${esc(title)}</div>
        ${sub ? `<div style="font-size:11px;color:var(--slate);margin-top:2px;">${esc(sub)}</div>` : ''}</div>
        <button style="color:var(--slate);opacity:.5;font-size:16px;cursor:pointer;border:none;background:none;" onclick="closeM('m-ob-workspace')">✕</button>
      </div>
      <div style="padding:14px 16px;">${body}</div>`;
    openM('m-ob-workspace');
  }

  const field = (label, control) => `<div class="ctf-field"><label class="ctf-label">${esc(label)}</label>${control}</div>`;
  const input = (id, value, placeholder, type) => `<input class="ctf-input" id="${id}" type="${type || 'text'}" value="${esc(value || '')}" placeholder="${esc(placeholder || '')}">`;
  const textarea = (id, value, placeholder, rows) => `<textarea class="ctf-input" id="${id}" rows="${rows || 3}" placeholder="${esc(placeholder || '')}" style="resize:vertical;">${esc(value || '')}</textarea>`;
  function select(id, options, selected) {
    return `<select class="ctf-select" id="${id}">${options.map(o => {
      const [v, label] = Array.isArray(o) ? o : [o, o];
      return `<option value="${esc(v)}"${String(v) === String(selected) ? ' selected' : ''}>${esc(label)}</option>`;
    }).join('')}</select>`;
  }
  const formError = msg => `<div id="vlf-form-error" style="display:none;margin-top:8px;padding:8px 10px;border-radius:var(--r-sm);background:var(--ember-p);color:var(--ember);font-size:11px;">${esc(msg || '')}</div>`;
  function showFormError(msg) {
    const el = document.getElementById('vlf-form-error');
    if (el) { el.textContent = msg; el.style.display = 'block'; } else t('Check the form', msg, 'r');
  }
  const matterOptions = () => matters().sort((a, b) => a.ref.localeCompare(b.ref)).map(m => [m.ref, m.ref + ' · ' + m.title]);
  const staffOptions = list => list.map(s => [s.name, s.name + ' — ' + s.role]);

  function wrap(name, fn) {
    const original = window[name];
    if (typeof original !== 'function') return;
    window[name] = function (...args) { return fn.call(this, original, args); };
  }

  /* ══ MATTERS ══ */

  const LEVEL = { urgent: ['urgent', 'urgent'], warn: ['warn', 'warn'], ok: ['active', 'ok'] };

  function matterRow(m, showAdvocate) {
    const [rowCls, pillCls] = LEVEL[m.statusLevel] || LEVEL.ok;
    const unassigned = !m.advocate;
    return `
      <div class="matter-row ${rowCls}" ${unassigned ? 'style="border-left:3px dashed rgba(184,92,42,.3);background:var(--ember-p);"' : ''} onclick="VLFOPS.openMatter(${arg(m.ref)})">
        <div style="flex:1;">
          <div class="mr-id">${esc(m.ref)}${showAdvocate ? ' · ' + esc(m.advocate || 'Unassigned') : ''}</div>
          <div class="mr-title">${esc(m.title)}</div>
          <div class="mr-meta">
            ${m.statusLabel ? `<span class="pill ${unassigned ? 'ruby' : pillCls}">${esc(m.statusLabel)}</span>` : ''}
            <span class="mr-court">${esc(m.court || '')}</span>
            ${m.practiceArea ? `<span class="pill ink">${esc(m.practiceArea)}</span>` : ''}
          </div>
        </div>
        <div class="mr-right">${unassigned
          ? `<button class="btn btn-ember btn-sm" onclick="event.stopPropagation();VLFOPS.assignMatter(${arg(m.ref)})">Assign</button>`
          : `<div class="mr-stage">${esc(m.stage || '')}</div>`}</div>
      </div>`;
  }

  function renderMatters() {
    const all = matters().sort((a, b) => b.ref.localeCompare(a.ref));
    const user = me();
    const mine = all.filter(m => m.advocate === user || m.supervisor === user);

    const advList = document.querySelector('#pg-adv-matters .matter-list');
    if (advList) {
      advList.innerHTML = mine.map(m => matterRow(m, m.advocate !== user)).join('') ||
        emptyBlock('briefcase', 'No matters yet', 'Matters you are responsible for or supervise will appear here.', `<button class="btn btn-v btn-sm" onclick="openIntake()">+ Open new matter</button>`);
      const outside = document.getElementById('matters-empty-state');
      if (outside) outside.style.display = 'none';
      const hs = document.querySelector('#pg-adv-matters .hs');
      if (hs) hs.textContent = `${user} · ${mine.length} active matter${mine.length === 1 ? '' : 's'} · ${firmName()}`;
      const badge = document.querySelector('#sbi-adv-matters .sb-b');
      if (badge) badge.textContent = mine.length;
    }

    const admList = document.querySelector('#pg-adm-matters .matter-list');
    if (admList) {
      admList.innerHTML = all.map(m => matterRow(m, true)).join('');
      const hs = document.querySelector('#pg-adm-matters .hs');
      if (hs) hs.textContent = `${all.length} active · ${firmName()} · All advocates`;
      const counts = [
        all.filter(m => m.advocate && m.statusLevel === 'urgent').length,
        all.filter(m => m.advocate && m.statusLevel === 'warn').length,
        all.filter(m => m.advocate && (m.statusLevel === 'ok' || !m.statusLevel)).length,
        all.filter(m => !m.advocate).length
      ];
      document.querySelectorAll('#pg-adm-matters .pgbody > div:first-child > .card').forEach((card, i) => {
        const n = card.firstElementChild;
        if (n && counts[i] !== undefined) n.textContent = counts[i];
      });
      const badge = document.querySelector('#sbi-adm-matters .sb-b');
      if (badge) badge.textContent = all.length;
    }
  }

  function openMatter(ref) {
    if (ref === EQUITY) {
      if (currentShell !== 'adv') setShell('adv');
      showMatter(EQUITY);
      return;
    }
    openMatterDrawer(ref);
  }

  function openMatterDrawer(ref) {
    const m = matter(ref);
    if (!m) return;
    const events = S.events.filter(e => e.matter === ref);
    const deadlines = S.deadlines.filter(d => d.matter === ref && !d.done);
    const tasks = Object.values(TASKS).filter(tk => tk.matter === ref);
    const unbilled = TIME_ENTRIES.filter(te => te.matter === ref && te.billable && !te.invoice);
    const invoices = INVOICES.filter(i => i.matter === ref);
    const detail = (label, value) => `<div style="background:var(--parch);border-radius:var(--r-sm);padding:8px 10px;"><div class="ov-stat-label" style="margin-bottom:3px;">${esc(label)}</div><div style="font-size:12px;font-weight:500;color:var(--ink);">${esc(value || '—')}</div></div>`;
    const list = (title, rows, empty) => `
      <div><div class="section-label" style="opacity:.7;">${esc(title)}</div>
      ${rows.length ? rows.join('') : `<div style="font-size:11px;color:var(--slate);padding:4px 0 8px;">${esc(empty)}</div>`}</div>`;
    const row = (main, meta, onclick) => `<div style="padding:8px 10px;background:var(--white);border:1px solid rgba(28,43,43,.07);border-radius:var(--r-sm);margin-bottom:5px;${onclick ? 'cursor:pointer;' : ''}" ${onclick ? `onclick="${onclick}"` : ''}><div style="font-size:12px;font-weight:500;color:var(--ink);">${main}</div><div style="font-family:var(--mono);font-size:9px;color:var(--slate);margin-top:2px;">${meta}</div></div>`;

    const body = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
        ${detail('Client', m.client)}${detail('Opposing party', m.opposingParty)}
        ${detail('Practice area', m.practiceArea)}${detail('Stage', m.stage)}
        ${detail('Responsible advocate', m.advocate || 'Unassigned')}${detail('Supervising partner', m.supervisor)}
        ${detail('Court / forum', m.court)}${detail('Fee arrangement', m.feeArrangement)}
      </div>
      ${m.description ? `<div class="box ink"><div class="box-text">${esc(m.description)}</div></div>` : ''}
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        ${!m.advocate ? `<button class="btn btn-ember btn-sm" onclick="closeD();VLFOPS.assignMatter(${arg(ref)})">Assign advocate</button>` : ''}
        <button class="btn btn-v btn-sm" onclick="closeD();openCreateTask(${arg(ref)},'')">+ Task</button>
        <button class="btn btn-ghost btn-sm" onclick="closeD();VLFOPS.openEventForm(${arg(ref)})">+ Hearing</button>
        <button class="btn btn-ghost btn-sm" onclick="closeD();VLFOPS.openDeadlineForm(${arg(ref)})">+ Deadline</button>
        <button class="btn btn-ghost btn-sm" onclick="closeD();VLFOPS.logTimeFor(${arg(ref)})">Log time</button>
        <button class="btn btn-ghost btn-sm" onclick="closeD();openNewInvoice(${arg(ref)})">Draft invoice</button>
      </div>
      ${list('Court diary', events.map(e => row(esc(e.title), esc(e.dateLabel + (e.time ? ' · ' + e.time : '') + ' · ' + (e.court || '')), `closeD();openDiaryEvent(${arg(e.id)})`)), 'No hearings scheduled.')}
      ${list('Open deadlines', deadlines.map(d => row(esc(d.title), esc(d.dueLabel + (d.dueTime ? ' · ' + d.dueTime : '') + ' · ' + (d.owner || '')))), 'No open deadlines.')}
      ${list('Tasks', tasks.map(tk => row(esc(tk.title), esc(tk.status.replace('_', ' ') + ' · ' + tk.assignedTo + (tk.deadline ? ' · due ' + tk.deadline : '')), `closeD();openTaskWorkspace(${arg(tk.id)})`)), 'No tasks yet.')}
      ${list('Billing', [
        row(money(unbilled.reduce((s, te) => s + te.amount, 0)) + ' unbilled', esc(unbilled.length + ' billable time entr' + (unbilled.length === 1 ? 'y' : 'ies'))),
        ...invoices.map(i => row(esc(i.id) + ' · ' + money(i.total), esc(i.status), `closeD();openInvoiceWorkspace(${arg(i.id)})`))
      ], '')}`;

    openD(m.ref + (m.practiceArea ? ' · ' + m.practiceArea : ''), m.title, [m.client, m.court].filter(Boolean).join(' · '), body);
  }

  function assignMatter(ref) {
    const m = matter(ref);
    if (!m) return;
    modal('Assign matter', m.ref + ' · ' + m.title, `
      <div class="create-task-form">
        ${field('Responsible advocate', select('vlf-assign-advocate', staffOptions(advocates()), m.advocate || ''))}
        ${field('Supervising partner', select('vlf-assign-partner', staffOptions(partners()), m.supervisor || ''))}
        <button class="btn btn-v btn-full" onclick="VLFOPS.saveAssignment(${arg(ref)})">Assign &amp; notify</button>
      </div>`);
  }

  function saveAssignment(ref) {
    const advocate = val('vlf-assign-advocate');
    const supervisor = val('vlf-assign-partner');
    api('PUT', 'matters/' + encodeURIComponent(ref), { advocate, supervisor, statusLabel: 'Assigned', statusLevel: 'ok', stage: 'Investigation' })
      .then(saved => {
        VLF.matters[ref] = saved;
        closeM('m-ob-workspace');
        renderMatters();
        t('Matter assigned', `${ref} → ${advocate}`, 'g');
        return api('POST', 'notifications', { recipient: advocate, type: 'task', label: 'New matter assigned', text: `You are now responsible advocate on ${ref} — ${saved.title}.`, link: { matter: ref } });
      })
      .then(pollSoon)
      .catch(saveFailed);
  }

  function logTimeFor(ref) {
    NAV.ctx.matterId = ref;
    openTimeLogger('');
  }

  // The create-task form lists two hard-coded matters and three people; offer every matter and staff member.
  wrap('openCreateTask', function (original, args) {
    original.apply(this, args);
    const matterSel = document.getElementById('ctf-matter');
    if (matterSel && matters().length) {
      const current = args[0] || matterSel.value;
      matterSel.innerHTML = matterOptions().map(([v, l]) => `<option value="${esc(v)}"${v === current ? ' selected' : ''}>${esc(l)}</option>`).join('');
    }
    const assignee = document.getElementById('ctf-assignee');
    if (assignee && S.staff.length) {
      assignee.innerHTML = advocates().map(s => `<option>${esc(s.name + ' — ' + s.role)}</option>`).join('');
    }
  });

  /* ══ INTAKE ══ */

  let intake = {};
  let lastIntakeStep = 1;

  function intakeDefaults() {
    const user = me();
    return {
      clientId: '', clientName: '', clientType: 'Company', clientTin: '', contactName: '', contactEmail: '',
      opposingParty: '', conflictCheckedFor: null, conflict: null,
      description: '', practiceArea: 'Commercial Litigation', court: 'High Court — Commercial Division', instructionDate: today(),
      advocate: advocates().some(s => s.name === user) ? user : ((advocates()[0] || {}).name || ''),
      supervisor: (partners()[0] || {}).name || '',
      feeArrangement: 'Hourly — UGX 450,000/hr', created: null, submitting: false
    };
  }

  const ik = (key, control) => control.replace('<input ', `<input data-ik="${key}" `).replace('<select ', `<select data-ik="${key}" `).replace('<textarea ', `<textarea data-ik="${key}" `);

  function intakeContent(step) {
    switch (step) {
      case 1: return `
        ${field('Existing client', ik('clientId', select('ik-clientId', [['', '— New client —'], ...S.clients.map(c => [c.id, c.name])], intake.clientId)))}
        <div id="ik-new-client" style="display:${intake.clientId ? 'none' : 'block'};">
          ${field('Client name *', ik('clientName', input('ik-clientName', intake.clientName, 'e.g. Umeme Ltd')))}
          <div class="ctf-row">
            ${field('Type', ik('clientType', select('ik-clientType', ['Company', 'Individual', 'Estate', 'Government', 'NGO'], intake.clientType)))}
            ${field('TIN', ik('clientTin', input('ik-clientTin', intake.clientTin, 'Optional')))}
          </div>
          <div class="ctf-row">
            ${field('Contact person', ik('contactName', input('ik-contactName', intake.contactName, 'Name')))}
            ${field('Contact email', ik('contactEmail', input('ik-contactEmail', intake.contactEmail, 'name@company.com', 'email')))}
          </div>
        </div>
        ${formError()}`;
      case 2: return `
        ${field('Opposing party *', ik('opposingParty', input('ik-opposingParty', intake.opposingParty, 'e.g. Ssekandi Enterprises Ltd')))}
        <button class="btn btn-v btn-full" onclick="VLFOPS.runConflictCheck()">Run conflict check</button>
        <div id="ik-conflict">${conflictHtml()}</div>
        ${formError()}`;
      case 3: return `
        ${field('Matter description *', ik('description', input('ik-description', intake.description, 'e.g. Recovery of UGX 120M — supply contract')))}
        <div class="ctf-row">
          ${field('Practice area', ik('practiceArea', select('ik-practiceArea', ['Commercial Litigation', 'Litigation', 'Conveyancing', 'Corporate', 'Probate', 'Employment', 'Criminal', 'Arbitration', 'General Advisory'], intake.practiceArea)))}
          ${field('Court / forum', ik('court', select('ik-court', ['High Court — Commercial Division', 'High Court — Land Division', 'High Court — Family Division', 'High Court — Criminal Division', 'Court of Appeal', 'Supreme Court', 'Magistrates Court · Kampala', 'Labour Tribunal', 'Arbitration · Kampala', 'Registry · Kampala'], intake.court)))}
        </div>
        ${field('Instruction date', ik('instructionDate', input('ik-instructionDate', intake.instructionDate, '', 'date')))}
        ${formError()}`;
      case 4: return `
        ${field('Responsible advocate', ik('advocate', select('ik-advocate', staffOptions(advocates()), intake.advocate)))}
        ${field('Supervising partner', ik('supervisor', select('ik-supervisor', staffOptions(partners()), intake.supervisor)))}
        ${field('Fee arrangement', ik('feeArrangement', select('ik-fee', ['Hourly — UGX 450,000/hr', 'Hourly — UGX 600,000/hr', 'Fixed fee', 'Retainer', 'Contingency'], intake.feeArrangement)))}
        ${formError()}`;
      default: return `<div id="ik-result">${intake.created ? intakeResultHtml(intake.created) : '<div style="padding:20px;text-align:center;font-size:12px;color:var(--slate);">Opening matter…</div>'}</div>`;
    }
  }

  function conflictHtml() {
    const c = intake.conflict;
    if (!c || intake.conflictCheckedFor !== intake.opposingParty) return '';
    return c.conflict
      ? `<div class="conflict-result conflict-found"><div style="font-size:11px;font-weight:600;color:var(--ember);margin-bottom:5px;">${ic('alert', 14)} Conflict found — this matter cannot be opened</div>${c.matches.map(x => `<div style="font-size:11px;color:var(--ember);">• ${esc(x)}</div>`).join('')}<div style="font-size:10px;color:var(--slate);margin-top:6px;">Refer to the supervising partner before taking instructions.</div></div>`
      : `<div class="conflict-result conflict-clear"><div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;"><div class="iris-dot"></div><div style="font-size:11px;font-weight:500;color:var(--vd);">✓ Conflict check clear</div></div><div style="font-size:11px;color:var(--vd);opacity:.8;">${esc(intake.opposingParty)} is not a client of the firm, and the client has not been an opposing party in any firm matter.</div></div>`;
  }

  function intakeResultHtml(m) {
    return `
      <div class="matter-id-badge"><div class="matter-id-val">${esc(m.ref)}</div><div class="matter-id-label">Matter Reference · ${esc(firmName())}</div></div>
      <div class="m-info">✓ ${esc(m.title)}<br>✓ Client: ${esc(m.client)} · ✓ Conflict check cleared<br>✓ ${esc(m.advocate)} and ${esc(m.supervisor)} notified</div>
      <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:8px;">
        <button class="btn btn-v btn-sm" onclick="closeM('m-intake');VLFOPS.openMatter(${arg(m.ref)})">Open matter</button>
        <button class="btn btn-ghost btn-sm" onclick="closeM('m-intake');VLFOPS.openEventForm(${arg(m.ref)})">+ First hearing</button>
        <button class="btn btn-ghost btn-sm" onclick="closeM('m-intake');VLFOPS.openDeadlineForm(${arg(m.ref)})">+ Deadline</button>
      </div>`;
  }

  function validateIntake(step) {
    if (step === 1 && !intake.clientId && !intake.clientName.trim()) return 'Choose an existing client or enter the new client’s name.';
    if (step === 1 && intake.contactEmail && !/^\S+@\S+\.\S+$/.test(intake.contactEmail)) return 'The contact email doesn’t look right.';
    if (step === 2) {
      if (!intake.opposingParty.trim()) return 'Enter the opposing party.';
      if (intake.conflictCheckedFor !== intake.opposingParty) return 'Run the conflict check before continuing.';
      if (intake.conflict && intake.conflict.conflict) return 'A conflict was found — this matter cannot be opened.';
    }
    if (step === 3 && !intake.description.trim()) return 'Describe the matter.';
    return '';
  }

  function runConflictCheck() {
    if (!intake.opposingParty.trim()) { showFormError('Enter the opposing party first.'); return; }
    const clientName = intake.clientId ? ((S.clients.find(c => String(c.id) === String(intake.clientId)) || {}).name || '') : intake.clientName;
    const party = intake.opposingParty;
    api('GET', 'clients/conflict?client=' + encodeURIComponent(clientName) + '&opposing=' + encodeURIComponent(party))
      .then(result => {
        intake.conflict = result;
        intake.conflictCheckedFor = party;
        const box = document.getElementById('ik-conflict');
        if (box) box.innerHTML = conflictHtml();
      })
      .catch(saveFailed);
  }

  function submitIntake() {
    if (intake.created || intake.submitting) return;
    intake.submitting = true;
    const body = {
      opposingParty: intake.opposingParty, description: intake.description, practiceArea: intake.practiceArea,
      court: intake.court, instructionDate: intake.instructionDate, advocate: intake.advocate,
      supervisor: intake.supervisor, feeArrangement: intake.feeArrangement, openedBy: me()
    };
    if (intake.clientId) body.clientId = Number(intake.clientId);
    else Object.assign(body, { clientName: intake.clientName, clientType: intake.clientType, clientTin: intake.clientTin || null, contactName: intake.contactName || null, contactEmail: intake.contactEmail || null });

    api('POST', 'intake', body)
      .then(saved => {
        intake.created = saved;
        VLF.matters[saved.ref] = saved;
        const box = document.getElementById('ik-result');
        if (box) box.innerHTML = intakeResultHtml(saved);
        t('Matter opened — ' + saved.ref, saved.title, 'g');
        return VLF.reload();
      })
      .then(pollSoon)
      .catch(err => {
        intake.submitting = false;
        const box = document.getElementById('ik-result');
        if (box) box.innerHTML = `<div class="m-warn">${esc(err.message)}</div>`;
        saveFailed(err);
      });
  }

  wrap('openIntake', function (original, args) {
    intake = intakeDefaults();
    lastIntakeStep = 1;
    return original.apply(this, args);
  });

  wrap('renderIntake', function (original, args) {
    if (intakeStep > lastIntakeStep) {
      const err = validateIntake(lastIntakeStep);
      if (err) {
        intakeStep = lastIntakeStep;
        original.apply(this, args);
        showFormError(err);
        return;
      }
    }
    lastIntakeStep = intakeStep;
    original.apply(this, args);
    if (intakeStep === 5) submitIntake();
  });

  function initIntake() {
    if (typeof INTAKE_STEPS === 'undefined') return;
    INTAKE_STEPS.forEach((step, i) => Object.defineProperty(step, 'content', { get: () => intakeContent(i + 1), configurable: true }));
    const body = document.getElementById('intake-body');
    if (!body) return;
    const capture = e => {
      const key = e.target.dataset && e.target.dataset.ik;
      if (!key) return;
      intake[key] = e.target.value;
      if (key === 'clientId') {
        const block = document.getElementById('ik-new-client');
        if (block) block.style.display = e.target.value ? 'none' : 'block';
      }
    };
    body.addEventListener('input', capture);
    body.addEventListener('change', capture);
  }

  /* ══ CLIENTS ══ */

  function renderClients() {
    const page = document.getElementById('pg-adv-clients');
    const body = page && page.querySelector(':scope > .pgbody');
    if (!body) return;

    const heroI = page.querySelector(':scope > .hero .hero-i');
    if (heroI && !heroI.dataset.vlf) {
      heroI.dataset.vlf = '1';
      heroI.innerHTML = `<div class="hero-row"><div>${heroI.innerHTML}</div><div class="hero-acts"><button class="btn btn-v btn-sm" onclick="VLFOPS.openClientForm()">+ New client</button></div></div>`;
    }
    const hs = page.querySelector(':scope > .hero .hs');
    if (hs) hs.textContent = `${firmName()} · ${S.clients.length} client${S.clients.length === 1 ? '' : 's'} on record`;

    const outside = document.getElementById('clients-empty-state');
    if (outside) outside.style.display = 'none';

    const colours = { Company: 'var(--gold)', Estate: 'var(--plum)', Individual: 'var(--sky)', Government: 'var(--vd)', NGO: 'var(--ruby)' };
    body.innerHTML = `<div style="display:flex;flex-direction:column;gap:7px;">${S.clients.map(c => {
      const initials = c.name.split(/\s+/).filter(Boolean).map(p => p[0]).slice(0, 2).join('').toUpperCase();
      const contact = [c.contactName, c.contactEmail, c.contactPhone].filter(Boolean).join(' · ');
      return `
        <div class="card">
          <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:9px;">
            <div class="av client" style="width:36px;height:36px;font-size:12px;flex-shrink:0;background:${colours[c.type] || 'var(--gold)'};">${esc(initials)}</div>
            <div style="flex:1;">
              <div style="font-size:13px;font-weight:500;color:var(--ink);">${esc(c.name)}</div>
              <div style="font-size:11px;color:var(--slate);margin-top:2px;">${esc(c.type)}${c.tin ? ' · TIN ' + esc(c.tin) : ''} · ${c.matters.length} matter${c.matters.length === 1 ? '' : 's'}${contact ? ' · ' + esc(contact) : ''}</div>
            </div>
            <span class="pill ${c.verified ? 'ok' : 'info'}">${c.verified ? 'Verified' : 'Unverified'}</span>
          </div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;border-top:1px solid rgba(28,43,43,.06);padding-top:9px;">
            ${c.matters.map(ref => `<button class="btn btn-ghost btn-sm" onclick="VLFOPS.openMatter(${arg(ref)})">${esc(ref)}</button>`).join('')}
            <button class="btn btn-ghost btn-sm" onclick="VLFOPS.newMatterForClient(${arg(c.id)})">+ New matter</button>
            <button class="btn btn-ghost btn-sm" onclick="VLFOPS.openClientForm(${arg(c.id)})">Edit</button>
            ${c.contactEmail ? `<button class="btn btn-ghost btn-sm" onclick="VLFOPS.inviteClient(${arg(c.id)})">${(c.portalUsers || []).length ? 'Resend portal invite' : 'Invite to portal'}</button>` : ''}
          </div>
          <div style="font-size:10px;color:var(--slate);margin-top:6px;">${(c.portalUsers || []).length ? 'Portal access: ' + esc(c.portalUsers.join(', ')) : c.contactEmail ? 'No portal access yet' : 'Add a contact email to invite them to the client portal'}
          </div>
        </div>`;
    }).join('') || emptyBlock('users', 'No clients on record', 'Clients are added here or created when a new matter is opened through intake.', `<button class="btn btn-v btn-sm" onclick="VLFOPS.openClientForm()">+ New client</button>`)}</div><div style="height:20px;"></div>`;
  }

  function openClientForm(id) {
    const c = S.clients.find(x => String(x.id) === String(id)) || {};
    modal(c.id ? 'Edit client' : 'New client', c.id ? c.name : 'Adds the client to the firm register', `
      <div class="create-task-form">
        ${field('Client name *', input('vc-name', c.name))}
        <div class="ctf-row">${field('Type', select('vc-type', ['Company', 'Individual', 'Estate', 'Government', 'NGO'], c.type || 'Company'))}${field('TIN', input('vc-tin', c.tin))}</div>
        <div class="ctf-row">${field('Contact person', input('vc-contact', c.contactName))}${field('Contact email', input('vc-email', c.contactEmail, '', 'email'))}</div>
        <div class="ctf-row">${field('Phone', input('vc-phone', c.contactPhone))}${field('Address', input('vc-address', c.address))}</div>
        ${field('Notes', textarea('vc-notes', c.notes, 'Internal notes', 2))}
        <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--ink);"><input type="checkbox" id="vc-verified" ${c.verified ? 'checked' : ''}> Identity verified (IRIS)</label>
        ${formError()}
        <button class="btn btn-v btn-full" onclick="VLFOPS.saveClient(${arg(c.id || '')})">${c.id ? 'Save changes' : 'Add client'}</button>
      </div>`);
  }

  function saveClient(id) {
    const body = {
      name: val('vc-name'), type: val('vc-type'), tin: val('vc-tin') || null, contactName: val('vc-contact') || null,
      contactEmail: val('vc-email') || null, contactPhone: val('vc-phone') || null, address: val('vc-address') || null,
      notes: val('vc-notes') || null, verified: document.getElementById('vc-verified').checked
    };
    if (!body.name) { showFormError('Enter the client’s name.'); return; }
    (id ? api('PUT', 'clients/' + id, body) : api('POST', 'clients', body))
      .then(saved => {
        const i = S.clients.findIndex(c => c.id === saved.id);
        if (i >= 0) S.clients[i] = saved; else S.clients.push(saved);
        S.clients.sort((a, b) => a.name.localeCompare(b.name));
        closeM('m-ob-workspace');
        renderClients();
        t(id ? 'Client updated' : 'Client added', saved.name, 'g');
      })
      .catch(err => err.fromServer ? showFormError(err.message) : saveFailed(err));
  }

  function inviteClient(id) {
    const c = S.clients.find(x => String(x.id) === String(id));
    api('POST', 'clients/' + id + '/invite').then(saved => {
      const i = S.clients.findIndex(x => x.id === saved.id);
      if (i >= 0) S.clients[i] = saved;
      renderClients();
      t('Portal invite sent', `${c ? c.contactName : 'The contact'} will get an email to set their password`, 'g');
    }).catch(saveFailed);
  }

  function inviteStaff(id) {
    api('POST', 'staff/' + id + '/invite').then(saved => {
      const i = S.staff.findIndex(x => x.id === saved.id);
      if (i >= 0) S.staff[i] = saved;
      closeM('m-ob-workspace');
      renderPeople();
      t('Invite sent', `${saved.name} will get an email to set their password`, 'g');
    }).catch(saveFailed);
  }

  function newMatterForClient(id) {
    openIntake();
    intake.clientId = String(id);
    renderIntake();
  }

  /* ══ COURT DIARY ══ */

  const LEVEL_PILL = { critical: ['urgent', 'Critical'], scheduled: ['sky', 'Scheduled'], complete: ['ok', 'Done'] };

  function renderDiary() {
    const body = document.querySelector('#pg-adv-diary .pgbody');
    if (!body) return;
    const addBtn = document.querySelector('#pg-adv-diary .hero .btn');
    if (addBtn) addBtn.setAttribute('onclick', 'VLFOPS.openEventForm()');
    const hs = document.querySelector('#pg-adv-diary .hs');
    if (hs) hs.textContent = `All matters · ${S.events.filter(e => e.level !== 'complete').length} upcoming court events · ${firmName()}`;

    const days = {};
    S.events.forEach(e => { (days[e.date] = days[e.date] || []).push(e); });
    const html = Object.keys(days).sort().map(date => `
      <div class="diary-day">
        <div class="diary-date">${esc(days[date][0].dateLabel)}</div>
        ${days[date].map(e => {
          const pending = (e.checklist || []).filter(c => !c.done).length;
          const [pillCls, pillText] = LEVEL_PILL[e.level] || LEVEL_PILL.scheduled;
          const m = matter(e.matter);
          return `
            <div class="diary-event ${esc(e.level)}" onclick="openDiaryEvent(${arg(e.id)})">
              <div class="de-time">${esc(e.time || '—')}</div>
              <div class="de-body">
                <div class="de-matter">${esc(e.matter)}${m ? ' · ' + esc(m.client || m.title) : ''}</div>
                <div class="de-title">${esc(e.title)}</div>
                <div class="de-court">${esc([e.court, e.judge].filter(Boolean).join(' · '))}</div>
                <div class="de-advocate">${esc([e.advocate, e.notes].filter(Boolean).join(' · '))}</div>
                ${pending ? `<div style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;padding:4px 9px;background:rgba(184,92,42,.12);border-radius:var(--r-sm);font-size:10px;color:var(--ember);">${ic('alert', 13)} ${pending} preparation item${pending === 1 ? '' : 's'} outstanding</div>` : ''}
              </div>
              <div class="de-right"><span class="pill ${pillCls}">${pillText}</span><button class="btn btn-ghost btn-sm" onclick="event.stopPropagation();openDiaryEvent(${arg(e.id)})">Prepare</button></div>
            </div>`;
        }).join('')}
      </div>`).join('');
    body.innerHTML = (html || '<div class="empty-state"><div class="empty-state-icon">' + ic('calendar', 34) + '</div><div class="empty-state-title">No court events</div><div class="empty-state-desc">Add a hearing to start the diary.</div></div>') + '<div style="height:20px;"></div>';

    const badge = document.querySelector('#sbi-adv-diary .sb-b');
    if (badge) badge.textContent = S.events.filter(e => e.level === 'critical').length;
  }

  function findEvent(id) {
    return S.events.find(e => String(e.id) === String(id) || e.key === id);
  }

  function eventDrawerBody(e) {
    const items = (e.checklist || []).map((c, i) => `
      <label style="display:flex;align-items:flex-start;gap:9px;padding:7px 0;border-bottom:1px solid rgba(28,43,43,.05);font-size:12px;cursor:pointer;color:${c.done ? 'var(--vd)' : 'var(--ember)'};">
        <input type="checkbox" ${c.done ? 'checked' : ''} onchange="VLFOPS.toggleChecklist(${arg(e.id)},${i},this.checked)" style="margin-top:2px;">
        <span style="${c.done ? 'text-decoration:line-through;opacity:.7;' : ''}">${esc(c.text)}</span>
      </label>`).join('');
    return `
      <div>
        <div class="section-label" style="opacity:.7;">Hearing preparation checklist</div>
        ${items || '<div style="font-size:11px;color:var(--slate);padding:4px 0;">No checklist items yet.</div>'}
        <div style="display:flex;gap:6px;margin-top:8px;">
          <input class="ctf-input" id="vlf-check-new" placeholder="Add a preparation item…" onkeydown="if(event.key==='Enter')VLFOPS.addChecklistItem(${arg(e.id)})">
          <button class="btn btn-ghost btn-sm" onclick="VLFOPS.addChecklistItem(${arg(e.id)})">Add</button>
        </div>
      </div>
      ${e.notes ? `<div class="box ink"><div class="box-text">${esc(e.notes)}</div></div>` : ''}
      <div style="display:flex;gap:7px;flex-wrap:wrap;">
        ${e.level !== 'complete' ? `<button class="btn btn-v btn-sm" onclick="VLFOPS.setEventLevel(${arg(e.id)},'complete')">Mark hearing done</button>` : `<button class="btn btn-ghost btn-sm" onclick="VLFOPS.setEventLevel(${arg(e.id)},'scheduled')">Reopen</button>`}
        ${e.level === 'scheduled' ? `<button class="btn btn-ember btn-sm" onclick="VLFOPS.setEventLevel(${arg(e.id)},'critical')">Flag critical</button>` : ''}
        <button class="btn btn-ghost btn-sm" onclick="closeD();VLFOPS.openMatter(${arg(e.matter)})">Open matter</button>
      </div>`;
  }

  function openEventDrawer(e) {
    openD(e.matter + ' · ' + ((matter(e.matter) || {}).client || ''), e.title,
      [e.court, e.judge, e.dateLabel + (e.time ? ' · ' + e.time : '')].filter(Boolean).join(' · '), eventDrawerBody(e));
  }

  wrap('openDiaryEvent', function (original, args) {
    const e = findEvent(args[0]);
    if (!e) return original.apply(this, args);
    openEventDrawer(e);
  });

  function patchEvent(id, changes, message) {
    return api('PATCH', 'events/' + id, changes).then(saved => {
      const i = S.events.findIndex(e => e.id === saved.id);
      if (i >= 0) S.events[i] = saved;
      renderDiary();
      renderAdminDeadlines();
      const drawer = document.getElementById('drawer');
      if (drawer && drawer.classList.contains('on')) document.getElementById('dr-body').innerHTML = eventDrawerBody(saved);
      if (message) t(message, saved.title, 'g');
    }).catch(saveFailed);
  }

  function toggleChecklist(id, index, done) {
    const e = findEvent(id);
    if (!e) return;
    const checklist = e.checklist.map((c, i) => i === index ? { text: c.text, done } : c);
    patchEvent(e.id, { checklist }, done ? 'Preparation item done' : null);
  }

  function addChecklistItem(id) {
    const e = findEvent(id);
    const text = val('vlf-check-new');
    if (!e || !text) return;
    patchEvent(e.id, { checklist: [...(e.checklist || []), { text, done: false }] }, 'Checklist item added');
  }

  function setEventLevel(id, level) {
    patchEvent(id, { level }, level === 'complete' ? 'Hearing marked done' : 'Court event updated');
  }

  function openEventForm(ref) {
    const m = matter(ref) || matter(EQUITY) || {};
    modal('Add court event', 'Hearing, mention, conference or filing date', `
      <div class="create-task-form">
        ${field('Matter *', select('ve-matter', matterOptions(), ref || m.ref))}
        ${field('Event *', input('ve-title', '', 'e.g. Mention — directions on witness statements'))}
        <div class="ctf-row">${field('Date *', input('ve-date', today(), '', 'date'))}${field('Time', input('ve-time', '09:30', 'e.g. 09:30 or All day'))}</div>
        <div class="ctf-row">${field('Court / courtroom', input('ve-court', ref ? m.court : '', 'e.g. High Court — Commercial Division · Courtroom 7'))}${field('Judge / arbitrator', input('ve-judge', ref ? m.judge : '', 'e.g. Justice Tibatemwa'))}</div>
        <div class="ctf-row">${field('Appearing advocate', select('ve-advocate', staffOptions(advocates()), (ref && m.advocate) || me()))}${field('Priority', select('ve-level', [['scheduled', 'Scheduled'], ['critical', 'Critical']], 'scheduled'))}</div>
        ${field('Preparation checklist (one item per line)', textarea('ve-checklist', '', 'Hearing bundle prepared\nClient instructions confirmed', 3))}
        ${formError()}
        <button class="btn btn-v btn-full" onclick="VLFOPS.saveEvent()">Add to court diary</button>
      </div>`);
  }

  function saveEvent() {
    const body = {
      matter: val('ve-matter'), title: val('ve-title'), date: val('ve-date'), time: val('ve-time') || null,
      court: val('ve-court') || null, judge: val('ve-judge') || null, advocate: val('ve-advocate'), level: val('ve-level'),
      checklist: val('ve-checklist').split('\n').map(s => s.trim()).filter(Boolean)
    };
    if (!body.title || !body.date) { showFormError('Enter the event and its date.'); return; }
    api('POST', 'events', body).then(saved => {
      S.events.push(saved);
      S.events.sort((a, b) => (a.date + (a.time || '')).localeCompare(b.date + (b.time || '')));
      closeM('m-ob-workspace');
      renderDiary();
      renderAdminDeadlines();
      t('Added to court diary', `${saved.title} · ${saved.dateLabel}`, 'g');
      pollSoon();
    }).catch(err => err.fromServer ? showFormError(err.message) : saveFailed(err));
  }

  /* ══ DEADLINES ══ */

  const SEVERITY_COLOUR = { critical: 'var(--ember)', warn: 'var(--gold-d)', normal: 'var(--sky)' };

  function deadlineRow(d, withMatter) {
    const m = matter(d.matter);
    return `
      <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid rgba(28,43,43,.06);${d.done ? 'opacity:.55;' : ''}">
        <input type="checkbox" title="Mark done" ${d.done ? 'checked' : ''} onchange="VLFOPS.toggleDeadline(${arg(d.id)},this.checked)" style="margin-top:3px;cursor:pointer;">
        <div style="flex:1;">
          <div style="font-size:13px;font-weight:500;color:var(--ink);${d.done ? 'text-decoration:line-through;' : ''}">${esc(d.title)}</div>
          <div style="font-family:var(--mono);font-size:9px;color:var(--slate);margin-top:2px;">${esc(d.matter)}${withMatter && m ? ' · ' + esc(m.client || '') : ''} · ${esc(d.dueLabel)}${d.dueTime ? ' · ' + esc(d.dueTime) : ''}${d.owner ? ' · ' + esc(d.owner) : ''}</div>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="VLFOPS.openMatter(${arg(d.matter)})">Open</button>
      </div>`;
  }

  function deadlineSection(title, colour, rows, empty) {
    return `
      <div class="card" style="border-left:3px solid ${colour};">
        <div class="section-label" style="color:${colour};opacity:1;">${esc(title)}</div>
        ${rows.length ? rows.join('') : `<div style="font-size:11px;color:var(--slate);padding:4px 0;">${esc(empty)}</div>`}
      </div>`;
  }

  function renderDeadlines() {
    const body = document.querySelector('#pg-adv-deadlines .pgbody');
    if (body) {
      const user = me();
      const mine = S.deadlines.filter(d => {
        const m = matter(d.matter) || {};
        return d.owner === user || m.advocate === user || m.supervisor === user;
      });
      const open = mine.filter(d => !d.done);
      const hs = document.querySelector('#pg-adv-deadlines .hs');
      if (hs) hs.textContent = `${user} · ${open.length} open deadline${open.length === 1 ? '' : 's'}`;
      body.innerHTML = `
        <div style="display:flex;justify-content:flex-end;"><button class="btn btn-v btn-sm" onclick="VLFOPS.openDeadlineForm()">+ Add deadline</button></div>
        ${deadlineSection('Critical', SEVERITY_COLOUR.critical, open.filter(d => d.severity === 'critical').map(d => deadlineRow(d)), 'No critical deadlines.')}
        ${deadlineSection('Upcoming', SEVERITY_COLOUR.warn, open.filter(d => d.severity !== 'critical').map(d => deadlineRow(d)), 'Nothing else due.')}
        ${mine.some(d => d.done) ? deadlineSection('Completed', 'var(--vd)', mine.filter(d => d.done).map(d => deadlineRow(d)), '') : ''}
        <div style="height:20px;"></div>`;
      const badge = document.querySelector('#sbi-adv-deadlines .sb-b');
      if (badge) badge.textContent = open.length;
    }
    renderAdminDeadlines();
  }

  function renderAdminDeadlines() {
    const body = document.querySelector('#pg-adm-deadlines .pgbody');
    if (!body) return;
    const open = S.deadlines.filter(d => !d.done);
    const upcoming = S.events.filter(e => e.level !== 'complete');
    body.innerHTML = `
      ${deadlineSection('Filing deadlines — all advocates', SEVERITY_COLOUR.critical, open.map(d => deadlineRow(d, true)), 'No open deadlines.')}
      ${deadlineSection('Court events', SEVERITY_COLOUR.warn, upcoming.map(e => `
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid rgba(28,43,43,.06);">
          <div class="cd ${e.level === 'critical' ? 'urg pulse' : 'warn'}"></div>
          <div style="flex:1;"><div style="font-size:12px;font-weight:500;color:var(--ink);">${esc(e.title)}</div><div style="font-family:var(--mono);font-size:9px;color:var(--slate);">${esc(e.matter)} · ${esc(e.dateLabel)}${e.time ? ' · ' + esc(e.time) : ''} · ${esc(e.advocate || '')}</div></div>
          <button class="btn btn-ghost btn-sm" onclick="openDiaryEvent(${arg(e.id)})">Monitor</button>
        </div>`), 'No upcoming court events.')}
      <div style="height:20px;"></div>`;
    const badge = document.querySelector('#sbi-adm-deadlines .sb-b');
    if (badge) badge.textContent = open.filter(d => d.severity === 'critical').length;
  }

  function toggleDeadline(id, done) {
    api('PATCH', 'deadlines/' + id, { done }).then(saved => {
      const i = S.deadlines.findIndex(d => d.id === saved.id);
      if (i >= 0) S.deadlines[i] = saved;
      renderDeadlines();
      t(done ? 'Deadline met' : 'Deadline reopened', saved.title, 'g');
    }).catch(saveFailed);
  }

  function openDeadlineForm(ref) {
    const m = matter(ref) || {};
    modal('Add deadline', 'Filing, service or compliance deadline', `
      <div class="create-task-form">
        ${field('Matter *', select('vd-matter', matterOptions(), ref || EQUITY))}
        ${field('Deadline *', input('vd-title', '', 'e.g. File reply to counterclaim'))}
        <div class="ctf-row">${field('Due date *', input('vd-date', today(), '', 'date'))}${field('Time', input('vd-time', '5:00 PM', 'e.g. 5:00 PM'))}</div>
        <div class="ctf-row">${field('Responsible', select('vd-owner', staffOptions(advocates()), m.advocate || me()))}${field('Severity', select('vd-severity', [['normal', 'Normal'], ['warn', 'Important'], ['critical', 'Critical — court-ordered']], 'warn'))}</div>
        ${formError()}
        <button class="btn btn-v btn-full" onclick="VLFOPS.saveDeadline()">Add deadline</button>
      </div>`);
  }

  function saveDeadline() {
    const body = { matter: val('vd-matter'), title: val('vd-title'), dueDate: val('vd-date'), dueTime: val('vd-time') || null, owner: val('vd-owner'), severity: val('vd-severity') };
    if (!body.title || !body.dueDate) { showFormError('Enter the deadline and its due date.'); return; }
    api('POST', 'deadlines', body).then(saved => {
      S.deadlines.push(saved);
      S.deadlines.sort((a, b) => a.dueDate.localeCompare(b.dueDate));
      closeM('m-ob-workspace');
      renderDeadlines();
      t('Deadline added', `${saved.title} · due ${saved.dueLabel}`, 'g');
      pollSoon();
    }).catch(err => err.fromServer ? showFormError(err.message) : saveFailed(err));
  }

  /* ══ TASK STATUS ══ */

  const TASK_PILL = { PENDING: 'info', IN_PROGRESS: 'sky', BLOCKED: 'urgent', DONE: 'ok' };

  function refreshQueue(force) {
    const q = document.getElementById('pg-adv-queue');
    if (!q) return;
    if (q.dataset.v3 || force) {
      delete q.dataset.v3;
      renderWorkQueueV3();
    }
  }

  wrap('openTaskWorkspace', function (original, args) {
    original.apply(this, args);
    const task = Object.values(TASKS).find(tk => tk.id === args[0]);
    const wrapEl = document.getElementById('ob-workspace-wrap');
    const body = wrapEl && wrapEl.children[1];
    if (!task || !body) return;
    const s = task.status;
    const btn = (status, label, cls) => `<button class="btn ${cls} btn-sm" onclick="VLFOPS.setTaskStatus(${arg(task.id)},'${status}')">${label}</button>`;
    body.insertAdjacentHTML('afterbegin', `
      <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;padding:9px 11px;background:var(--parch);border-radius:var(--r-sm);margin-bottom:10px;">
        <span style="font-family:var(--mono);font-size:9px;color:var(--slate);text-transform:uppercase;letter-spacing:.06em;">Status</span>
        <span class="pill ${TASK_PILL[s] || 'info'}">${esc(s.replace('_', ' '))}</span>
        <span style="flex:1;"></span>
        ${s !== 'IN_PROGRESS' && s !== 'DONE' ? btn('IN_PROGRESS', 'Start', 'btn-ghost') : ''}
        ${s !== 'BLOCKED' && s !== 'DONE' ? `<button class="btn btn-ghost btn-sm" onclick="document.getElementById('vlf-block-row').style.display='flex'">Blocked…</button>` : ''}
        ${s !== 'DONE' ? btn('DONE', 'Mark done', 'btn-v') : ''}
        ${s === 'DONE' || s === 'BLOCKED' ? btn('IN_PROGRESS', 'Reopen', 'btn-ghost') : ''}
      </div>
      <div id="vlf-block-row" style="display:none;gap:6px;margin-bottom:10px;">
        <input class="ctf-input" id="vlf-block-reason" placeholder="What is blocking this task?">
        <button class="btn btn-ember btn-sm" onclick="VLFOPS.setTaskStatus(${arg(task.id)},'BLOCKED')">Mark blocked</button>
      </div>`);
  });

  function setTaskStatus(id, status, reopen) {
    const task = Object.values(TASKS).find(tk => tk.id === id);
    if (!task) return Promise.resolve();
    const body = { status, actor: me() };
    if (status === 'BLOCKED') {
      body.blockedBy = val('vlf-block-reason');
      if (!body.blockedBy) { t('Add a reason', 'Say what is blocking the task', 'r'); return Promise.resolve(); }
    }
    return api('PATCH', 'tasks/' + encodeURIComponent(id), body).then(saved => {
      Object.assign(task, saved);
      refreshQueue();
      if (reopen !== false) openTaskWorkspace(id);
      t('Task ' + status.replace('_', ' ').toLowerCase(), saved.title, 'g');
      pollSoon();
    }).catch(saveFailed);
  }

  // "Mark complete & log time" — record the completion, then open the time logger as before.
  wrap('completeTask', function (original, args) {
    setTaskStatus(args[0], 'DONE', false);
    return original.apply(this, args);
  });

  /* ══ INVOICES ══ */

  const INV_STEPS = ['DRAFT', 'APPROVED', 'ISSUED', 'PAID'];

  window.renderBillingV3 = function () {
    const bp = document.getElementById('mw-billing');
    if (!bp) return;
    bp.dataset.v3 = '1';
    const ref = EQUITY; // the billing tab belongs to the Equity Bank workspace
    const unbilled = TIME_ENTRIES.filter(te => te.matter === ref && te.billable && !te.invoice);
    const invs = INVOICES.filter(i => i.matter === ref);
    const outstanding = invs.filter(i => i.status === 'ISSUED' || i.status === 'OVERDUE').reduce((s, i) => s + i.total - i.paid, 0);
    const paid = invs.reduce((s, i) => s + i.paid, 0);
    const stat = (label, value, colour) => `<div style="background:var(--parch);border-radius:var(--r-sm);padding:10px 12px;text-align:center;"><div style="font-family:var(--mono);font-size:9px;color:var(--slate);opacity:.4;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">${label}</div><div style="font-family:var(--serif);font-size:20px;font-weight:300;color:${colour};">${money(value)}</div></div>`;

    bp.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <div style="font-family:var(--serif);font-size:16px;font-weight:600;color:var(--ink);">Billing — ${ref}</div>
        <button class="btn btn-v btn-sm" onclick="openNewInvoice(${arg(ref)})">+ Draft invoice</button>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:7px;margin-bottom:12px;">
        ${stat('Unbilled time', unbilled.reduce((s, te) => s + te.amount, 0), 'var(--ink)')}${stat('Outstanding', outstanding, 'var(--ember)')}${stat('Paid to date', paid, 'var(--vd)')}
      </div>
      <div style="font-size:11px;font-weight:500;color:var(--ink);margin-bottom:7px;">Unbilled time entries</div>
      ${unbilled.length ? unbilled.map(te => `
        <div class="time-entry-row">
          <div style="flex:1;"><div class="ter-matter">${esc(te.matter)} · ${esc(te.task || 'General')}</div><div class="ter-desc">${esc(te.desc)}</div><div class="ter-meta">${esc(te.advocate)} · ${esc(te.date)}</div></div>
          <div class="ter-duration">${esc(te.duration)}</div>
          <div style="font-family:var(--mono);font-size:10px;color:var(--vd);">${money(te.amount)}</div>
        </div>`).join('') : '<div style="font-size:11px;color:var(--slate);padding:4px 0 8px;">All billable time has been invoiced.</div>'}
      <div style="font-size:11px;font-weight:500;color:var(--ink);margin:12px 0 7px;">Invoices</div>
      ${invs.map(inv => `
        <div class="invoice-card" data-edit-wired="1" style="margin-bottom:8px;cursor:pointer;" onclick="openInvoiceWorkspace(${arg(inv.id)})">
          <div class="inv-head">
            <div class="inv-id">${esc(inv.id)} · ${esc(inv.client)}</div>
            <div style="display:flex;align-items:center;gap:8px;"><div class="inv-title">${money(inv.total)}</div><div class="inv-status ${inv.status.toLowerCase()}">${esc(inv.status)}</div></div>
            <div class="inv-meta">${inv.issueDate ? 'Issued ' + esc(inv.issueDate) + ' · Due ' + esc(inv.dueDate) : 'Not yet issued'}${inv.paidDate ? ' · Paid ' + esc(inv.paidDate) : ''}</div>
          </div>
        </div>`).join('')}`;
  };

  document.addEventListener('vlf:invoices', () => {
    const bp = document.getElementById('mw-billing');
    if (bp && bp.classList.contains('on')) renderBillingV3();
  });

  function rerenderBilling() {
    const bp = document.getElementById('mw-billing');
    if (bp) { delete bp.dataset.v3; if (bp.classList.contains('on')) renderBillingV3(); }
  }

  window.openNewInvoice = function (ref) {
    ref = (typeof ref === 'string' && ref) || EQUITY;
    const m = matter(ref) || {};
    const unbilled = TIME_ENTRIES.filter(te => te.matter === ref && te.billable && !te.invoice);
    const subtotal = unbilled.reduce((s, te) => s + te.amount, 0);
    modal('Draft invoice', `${ref} · ${m.client || ''}`, `
      <div style="font-size:11px;font-weight:500;color:var(--ink);margin-bottom:8px;">Unbilled time to include</div>
      ${unbilled.length ? unbilled.map(te => `<div class="time-entry-row"><div style="flex:1;"><div class="ter-desc">${esc(te.desc)}</div><div class="ter-meta">${esc(te.advocate)} · ${esc(te.date)} · ${esc(te.duration)}</div></div><div style="font-family:var(--mono);font-size:11px;font-weight:500;color:var(--ink);">${money(te.amount)}</div></div>`).join('') : '<div style="font-size:11px;color:var(--slate);padding:4px 0;">No unbilled time on this matter.</div>'}
      <div style="font-size:11px;font-weight:500;color:var(--ink);margin:10px 0 6px;">Disbursements and other lines</div>
      <div id="vlf-inv-extra"></div>
      <button class="btn btn-ghost btn-sm" onclick="VLFOPS.addExtraInvoiceLine()">+ Add line</button>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid rgba(28,43,43,.08);margin-top:8px;font-size:13px;font-weight:600;"><span>Time subtotal</span><span style="font-family:var(--mono);color:var(--vd);">${money(subtotal)}</span></div>
      ${formError()}
      <button class="btn btn-v btn-full" onclick="VLFOPS.createInvoice(${arg(ref)})">Create draft invoice</button>
      <div style="margin-top:6px;font-size:10px;color:var(--slate);">Draft → partner approval → issued to client → paid. Included time entries are marked as billed.</div>`);
  };

  function addExtraInvoiceLine() {
    const box = document.getElementById('vlf-inv-extra');
    if (!box) return;
    box.insertAdjacentHTML('beforeend', `<div class="vlf-extra-line" style="display:flex;gap:6px;margin-bottom:6px;"><input class="ctf-input" placeholder="e.g. Court filing fees" style="flex:1;"><input class="ctf-input" placeholder="Amount (UGX)" style="width:130px;" inputmode="numeric"></div>`);
  }

  function createInvoice(ref) {
    const extraLines = [...document.querySelectorAll('.vlf-extra-line')].map(row => {
      const [d, a] = row.querySelectorAll('input');
      return { desc: d.value.trim(), amount: parseInt(a.value.replace(/[^0-9]/g, '') || '0', 10) };
    }).filter(l => l.desc);
    api('POST', 'invoices', { matter: ref, extraLines }).then(saved => {
      INVOICES.push(saved);
      TIME_ENTRIES.forEach(te => { if (te.matter === ref && te.billable && !te.invoice) te.invoice = saved.id; });
      rerenderBilling();
      openInvoiceWorkspace(saved.id);
      t('Draft invoice created — ' + saved.id, money(saved.total) + ' · awaiting partner approval', 'g');
      pollSoon();
    }).catch(err => err.fromServer ? showFormError(err.message) : saveFailed(err));
  }

  window.openInvoiceWorkspace = function (id) {
    const inv = INVOICES.find(i => i.id === id);
    if (!inv) return;
    const at = INV_STEPS.indexOf(inv.status);
    const chain = INV_STEPS.map((s, i) => `<span class="nap-state-item ${i < at ? 'done' : i === at ? 'current' : 'next'}">${i < at ? '✓ ' : ''}${s.charAt(0) + s.slice(1).toLowerCase()}</span>`).join('<span class="nap-sep">→</span>');
    const user = me();
    let actions = '';
    if (inv.status === 'DRAFT') {
      actions = `<button class="btn btn-ghost btn-sm" onclick="openEditableInvoice(${arg(inv.id)})">Edit lines</button>` +
        (isPartner(user)
          ? `<button class="btn btn-v btn-sm" onclick="VLFOPS.invoiceAction(${arg(inv.id)},'approve')">Approve for issue</button>`
          : `<span style="font-size:11px;color:var(--slate);">Waiting for a partner to approve it for issue.</span>`);
    } else if (inv.status === 'APPROVED') {
      actions = `<button class="btn btn-v btn-sm" onclick="VLFOPS.invoiceAction(${arg(inv.id)},'issue')">Issue to client</button><button class="btn btn-ghost btn-sm" onclick="openEditableInvoice(${arg(inv.id)})">Edit lines</button>`;
    } else if (inv.status === 'ISSUED' || inv.status === 'OVERDUE') {
      actions = `<input class="ctf-input" id="vlf-pay-amount" value="${inv.total - inv.paid}" style="width:150px;" inputmode="numeric"><button class="btn btn-v btn-sm" onclick="VLFOPS.invoiceAction(${arg(inv.id)},'pay')">Record payment</button>`;
    } else {
      actions = `<span style="font-size:11px;color:var(--vd);">✓ Paid in full${inv.paidDate ? ' on ' + esc(inv.paidDate) : ''}.</span>`;
    }
    modal(inv.client, `${inv.id} · ${inv.matter}${inv.issueDate ? ' · Issued ' + inv.issueDate : ''}`, `
      <div class="nap-state-chain">${chain}</div>
      <div class="invoice-card"><div style="padding:12px 14px;">
        ${inv.lines.map(l => `<div class="inv-line"><div><div style="font-size:12px;font-weight:500;color:var(--ink);">${esc(l.desc)}</div>${l.hours ? `<div style="font-family:var(--mono);font-size:9px;color:var(--slate);opacity:.6;">${esc(l.hours)}</div>` : ''}</div><div class="inv-amount">${money(l.amount)}</div></div>`).join('')}
        <div class="inv-line total"><span>Total</span><span class="inv-amount">${money(inv.total)}</span></div>
        ${inv.paid ? `<div class="inv-line" style="color:var(--vd);"><span>Paid</span><span class="inv-amount">${money(inv.paid)}</span></div>` : ''}
        ${inv.total - inv.paid > 0 && inv.status !== 'DRAFT' && inv.status !== 'APPROVED' ? `<div class="inv-line" style="color:var(--ember);"><span>Outstanding</span><span class="inv-amount">${money(inv.total - inv.paid)}</span></div>` : ''}
      </div></div>
      <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-top:12px;">${actions}</div>`);
  };

  function invoiceAction(id, action) {
    const inv = INVOICES.find(i => i.id === id);
    const body = { action, actor: me() };
    if (action === 'pay') {
      body.amount = parseInt(val('vlf-pay-amount').replace(/[^0-9]/g, '') || '0', 10);
      if (!body.amount) { t('Enter an amount', 'How much was paid?', 'r'); return; }
    }
    api('POST', 'invoices/' + encodeURIComponent(id) + '/transition', body).then(saved => {
      Object.assign(inv, saved);
      rerenderBilling();
      openInvoiceWorkspace(id);
      t({ approve: 'Invoice approved', issue: 'Invoice issued', pay: 'Payment recorded' }[action], `${saved.id} · ${saved.status}`, 'g');
      pollSoon();
    }).catch(saveFailed);
  }

  // Issued invoices are amended with a credit note, so only drafts open in the line editor.
  wrap('openEditableInvoice', function (original, args) {
    const inv = INVOICES.find(i => i.id === args[0]);
    if (inv && inv.status !== 'DRAFT' && inv.status !== 'APPROVED') {
      openInvoiceWorkspace(inv.id);
      t('Invoice already issued', 'Issued invoices can’t be edited — raise a credit note instead', 'y');
      return;
    }
    original.apply(this, args);
    document.querySelectorAll('#ob-workspace-wrap button').forEach(b => { if (/Issue to client/.test(b.textContent)) b.remove(); });
  });

  /* ══ DOCUMENTS: drafts, review and uploads ══ */

  const DOC_STATUS = {
    DRAFT: ['draft', 'Draft'], UNDER_REVIEW: ['review', 'Under review'], PENDING_PARTNER_APPROVAL: ['review', 'Awaiting partner approval'],
    APPROVED: ['approved', 'Approved'], REJECTED: ['draft', 'Returned for revision'], FILED: ['filed', 'Filed']
  };
  const docStatusLabel = s => (DOC_STATUS[s] || [null, s])[1];

  function workingDocs(ref) {
    return Object.entries(DOCUMENTS).filter(([key, d]) => !BUILTIN_DOCS.has(key) && d && d.matterId === ref);
  }

  function renderWorkingDocs() {
    const panel = document.getElementById('mw-documents');
    if (!panel || panel.dataset.origHtml) return; // a document or the editor is open
    const old = document.getElementById('vlf-working-docs');
    if (old) old.remove();
    const docs = workingDocs(EQUITY);
    const html = `
      <div class="doc-folder" id="vlf-working-docs">
        <div class="doc-folder-head"><span style="display:flex;color:var(--slate);">${ic('folder', 16)}</span><div class="doc-folder-name">Working documents</div><div class="doc-folder-count">${docs.length} document${docs.length === 1 ? '' : 's'} · drafts and uploads</div></div>
        <div class="doc-folder-body">
          ${docs.length ? docs.map(([key, d]) => {
            const [cls, label] = DOC_STATUS[d.currentStatus] || ['draft', d.currentStatus];
            const size = d.file ? ' · ' + Math.max(1, Math.round(d.file.size / 1024)) + ' KB' : '';
            return `<div class="doc-item" onclick="VLFOPS.openDoc(${arg(key)})"><div class="doc-item-icon">${ic(d.file ? 'paperclip' : 'file', 16)}</div><div style="flex:1;"><div class="doc-item-name">${esc(d.title)}</div><div class="doc-item-meta">${esc(d.author || '')}${esc(size)} · ${esc(d.folder || '')}</div></div><span class="doc-status ${cls}">${esc(label)}</span></div>`;
          }).join('') : '<div style="padding:10px 13px;font-size:11px;color:var(--slate);">No drafts or uploads yet — use New document or Upload.</div>'}
        </div>
      </div>`;
    const chips = panel.querySelector('.de-new-doc-btn');
    if (chips) chips.insertAdjacentHTML('afterend', html);
    else panel.insertAdjacentHTML('afterbegin', html);
  }

  function openDoc(key) {
    NAV.openDocument(key, 'content');
    setTimeout(() => enhanceDocWorkspace(key), 150);
  }

  function docContentHtml(d) {
    if (d.file) {
      const url = esc(d.file.url);
      const preview = /pdf/.test(d.file.mime) ? `<iframe src="${url}?inline=1" style="width:100%;height:380px;border:0;border-radius:var(--r-sm);" title="${esc(d.file.name)}"></iframe>`
        : /^image\//.test(d.file.mime) ? `<img src="${url}?inline=1" alt="${esc(d.file.name)}" style="max-width:100%;border-radius:var(--r-sm);">`
        : `<div style="padding:20px;text-align:center;color:var(--slate);font-family:var(--sans);">No preview for this file type.</div>`;
      return `<div style="font-family:var(--sans);font-size:11px;color:var(--slate);margin-bottom:8px;">${ic('paperclip', 13)} ${esc(d.file.name)} · ${Math.max(1, Math.round(d.file.size / 1024))} KB · SHA-256 ${esc(d.file.sha256.slice(0, 16))}… · <a href="${url}" style="color:var(--vd);">Download</a></div>${preview}`;
    }
    if (d.content) return `<div style="white-space:pre-wrap;">${esc(d.content)}</div>`;
    return '<div style="color:var(--slate);font-family:var(--sans);">This draft is empty.</div>';
  }

  function lifecycleHtml(key, d) {
    const user = me();
    const s = d.currentStatus;
    const isAuthor = d.author === user;
    const act = (action, label, cls) => `<button class="btn ${cls} btn-sm" onclick="VLFOPS.docAction(${arg(key)},'${action}')">${label}</button>`;
    let text = '';
    let buttons = '';
    if (s === 'DRAFT' || s === 'REJECTED') {
      text = s === 'REJECTED'
        ? `<strong>Returned for revision</strong>${d.returnReason ? ': ' + esc(d.returnReason) : ''}`
        : 'Draft — not yet submitted for review.';
      buttons = (!d.author || isAuthor) ? act('submit', s === 'REJECTED' ? 'Resubmit for review' : 'Submit for review', 'btn-v')
        : `<span style="font-size:11px;color:var(--slate);">Waiting for ${esc(d.author)} to ${s === 'REJECTED' ? 'revise and resubmit' : 'submit'}.</span>`;
    } else if (s === 'UNDER_REVIEW' || s === 'PENDING_PARTNER_APPROVAL') {
      text = `With ${esc(d.reviewer || 'the supervising partner')} for review.`;
      buttons = isAuthor
        ? '<span style="font-size:11px;color:var(--slate);">You can’t review your own document.</span>'
        : act('approve', 'Approve', 'btn-v') + `<button class="btn btn-ghost btn-sm" onclick="VLFOPS.openReturnFor(${arg(key)})">Return for revision</button>`;
    } else if (s === 'APPROVED') {
      text = 'Approved — ready to seal and file.';
      // Filing is a Class A act: partners only (enforced on the server too).
      buttons = ME.role === 'partner' ? act('file', 'Mark filed', 'btn-v') : '<span style="font-size:11px;color:var(--slate);">A partner files approved documents (Class A).</span>';
    } else if (s === 'FILED') {
      text = 'Filed — part of the permanent matter record.';
    }
    return `<div class="doc-next-action"><div class="dna-label">Review status · ${esc(docStatusLabel(s))}</div><div style="font-size:12px;color:var(--ink);line-height:1.55;margin-bottom:8px;">${text}</div><div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">${buttons}</div></div>`;
  }

  function enhanceDocWorkspace(key) {
    const d = DOCUMENTS[key];
    const panel = document.getElementById('mw-documents');
    if (!d || !panel || !panel.dataset.origHtml) return;
    const builtin = BUILTIN_DOCS.has(key);
    // The witness statement keeps its own screens until it enters the review workflow.
    if (builtin && !['REJECTED', 'UNDER_REVIEW', 'FILED'].includes(d.currentStatus)) return;

    const badge = panel.querySelector('.dw-status-badge');
    if (badge) {
      const [cls, label] = DOC_STATUS[d.currentStatus] || ['draft', d.currentStatus];
      badge.textContent = label;
      badge.className = 'dw-status-badge ' + (cls === 'review' ? 'review' : cls === 'filed' ? 'filed' : cls === 'approved' ? 'approved' : d.currentStatus === 'REJECTED' ? 'rejected' : 'draft');
    }
    if (!builtin) {
      const body = document.getElementById('doc-body-content');
      if (body) { body.removeAttribute('contenteditable'); body.innerHTML = docContentHtml(d); }
    }
    const block = lifecycleHtml(key, d);
    const action = document.getElementById('dw-panel-action');
    if (action) action.innerHTML = block;
    const content = document.getElementById('dw-panel-content');
    if (content) {
      const old = document.getElementById('vlf-doc-life');
      if (old) old.remove();
      content.insertAdjacentHTML('afterbegin', `<div id="vlf-doc-life" style="margin-bottom:10px;">${block}</div>`);
    }
  }

  function applyDocResult(key, data) {
    if (DOCUMENTS[key]) { Object.keys(DOCUMENTS[key]).forEach(k => delete DOCUMENTS[key][k]); Object.assign(DOCUMENTS[key], data); }
    else DOCUMENTS[key] = data;
  }

  function docAction(key, action, reason) {
    const d = DOCUMENTS[key];
    if (!d) return Promise.resolve();
    // The witness statement only lives in the page until its first review action.
    const ensure = BUILTIN_DOCS.has(key) ? api('PUT', 'documents/' + key, { data: d }) : Promise.resolve();
    return ensure
      .then(() => api('POST', 'documents/' + encodeURIComponent(key) + '/transition', { action, actor: me(), reason: reason || null }))
      .then(res => {
        applyDocResult(key, res.data);
        closeM('m-ob-workspace');
        openDocWorkspace(key);
        setTimeout(() => enhanceDocWorkspace(key), 150);
        const msg = { submit: 'Submitted for review', approve: 'Document approved', return: 'Returned for revision', file: 'Document filed' }[action];
        t(msg, res.data.title + (action === 'submit' ? ' · sent to ' + res.data.reviewer : ''), 'g');
        pollSoon();
      })
      .catch(saveFailed);
  }

  function openReturnFor(key) {
    const d = DOCUMENTS[key];
    if (!d) return;
    modal('Return for revision', d.title, `
      <div class="create-task-form">
        ${field('What needs to change? *', textarea('vlf-return-reason', '', 'e.g. Paragraph 7 needs the full interest computation', 4))}
        ${formError()}
        <button class="btn btn-ember btn-full" onclick="VLFOPS.confirmReturn(${arg(key)})">Return to ${esc(d.author || 'author')}</button>
      </div>`);
  }

  function confirmReturn(key) {
    const reason = val('vlf-return-reason');
    if (!reason) { showFormError('Say what needs to change so the author can fix it.'); return; }
    docAction(key, 'return', reason);
  }

  // The review toolbar's "Return for revision" opened an unrelated modal; open the real form.
  window.openReturnModal = function () {
    openReturnFor((NAV.ctx && NAV.ctx.documentId) || 'witness-statement');
  };

  /* Editor drafts: one document per editor session, saved with its text. */
  let editorDocKey = null;

  wrap('openDocEditor', function (original, args) {
    editorDocKey = null;
    return original.apply(this, args);
  });

  function saveEditorDoc() {
    const title = val('de-title-input') || 'Untitled document';
    const contentEl = document.getElementById('de-content-area');
    const content = contentEl ? contentEl.innerText : '';
    const words = content.trim().split(/\s+/).filter(Boolean).length;
    const matterRef = (typeof editorMatter !== 'undefined' && editorMatter) || EQUITY;
    const key = editorDocKey || 'draft-' + Date.now();
    const author = me();
    const ts = 'Today · ' + new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    const d = DOCUMENTS[key] || {
      id: 'GVL-' + matterRef + '-DOC-' + String(Object.keys(DOCUMENTS).length + 1).padStart(3, '0'),
      matterId: matterRef, matterTitle: (matter(matterRef) || {}).title || '', folder: 'Working Documents', class: 'B',
      currentStatus: 'DRAFT', currentVersion: 1, visibility: 'PRIVILEGED', author,
      versions: [{ v: 1, summary: '', author, date: ts, status: 'CURRENT', sha: Math.random().toString(16).slice(2, 18), iris: 'DRAFT-' + Date.now(), notes: 'Saved from the document editor.' }],
      approvalChain: [{ step: 1, who: author, role: 'Author', action: 'Draft saved', status: 'complete', date: ts, iris: null, class: 'B' }],
      history: [{ type: 'draft', ts, title: 'Draft created by ' + author, desc: matterRef, iris: 'DRAFT-' + Date.now() }],
      lifecycleStates: ['Draft', 'Review', 'Approved', 'IRIS Sealed', 'Filed', 'Archived'], currentLifecycleStep: 0
    };
    d.title = title;
    d.content = content;
    d.pages = Math.max(1, Math.ceil(words / 250));
    d.versions[0].summary = 'Draft · ' + words + ' words';
    DOCUMENTS[key] = d;
    editorDocKey = key;
    return api('PUT', 'documents/' + key, { data: d }).then(() => key);
  }

  window.saveDocDraft = function () {
    return saveEditorDoc().then(key => {
      const ss = document.getElementById('de-save-status');
      if (ss) { ss.textContent = 'Saved · Draft'; ss.style.color = 'var(--vd)'; }
      t('Draft saved', DOCUMENTS[key].title + ' · ' + DOCUMENTS[key].versions[0].summary, 'g');
      return key;
    }).catch(saveFailed);
  };

  window.submitDocForReview = function () {
    saveEditorDoc()
      .then(key => api('POST', 'documents/' + key + '/transition', { action: 'submit', actor: me() }).then(res => ({ key, res })))
      .then(({ key, res }) => {
        applyDocResult(key, res.data);
        editorDocKey = null;
        closeDocEditor();
        t('Submitted for review', `"${res.data.title}" · sent to ${res.data.reviewer}`, 'g');
        pollSoon();
      })
      .catch(saveFailed);
  };

  wrap('closeDocEditor', function (original, args) {
    original.apply(this, args);
    setTimeout(renderWorkingDocs, 50);
  });
  wrap('closeDocWorkspace', function (original, args) {
    original.apply(this, args);
    setTimeout(renderWorkingDocs, 50);
  });

  /* Uploads: real files, stored on the server with their SHA-256. */
  const VISIBILITY = { 'Privileged — Firm only': 'PRIVILEGED', 'Internal — All firm members': 'INTERNAL', 'Client approved — Visible to client': 'CLIENT_APPROVED' };
  const MAX_UPLOAD = 20 * 1024 * 1024;

  wrap('openDocUpload', function (original, args) {
    original.apply(this, args);
    const panel = document.getElementById('mw-documents');
    const zone = panel && panel.querySelector('.upload-zone');
    if (!zone) return;
    zone.insertAdjacentHTML('afterend', '<input type="file" id="vlf-upload-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt" style="display:none;">');
    const fileInput = document.getElementById('vlf-upload-file');
    const textInputs = panel.querySelectorAll('input:not([type="file"])');
    const selects = panel.querySelectorAll('select');
    if (textInputs[0]) textInputs[0].id = 'vlf-up-title';
    if (selects[0]) selects[0].id = 'vlf-up-folder';
    if (selects[1]) selects[1].id = 'vlf-up-visibility';
    fileInput.addEventListener('change', () => {
      const f = fileInput.files[0];
      if (!f) return;
      zone.querySelector('.upload-zone-title').textContent = f.name;
      zone.querySelector('.upload-zone-sub').textContent = Math.max(1, Math.round(f.size / 1024)) + ' KB · tap to choose a different file';
      const title = document.getElementById('vlf-up-title');
      if (title && !title.value.trim()) title.value = f.name.replace(/\.[^.]+$/, '');
    });
  });

  window.simulateUpload = function () {
    const fileInput = document.getElementById('vlf-upload-file');
    if (!fileInput) return;
    const f = fileInput.files[0];
    if (!f) { fileInput.click(); return; }
    if (f.size > MAX_UPLOAD) { t('File too large', 'The limit is 20 MB', 'r'); return; }
    const form = new FormData();
    form.append('file', f);
    form.append('title', val('vlf-up-title') || f.name);
    form.append('matter', EQUITY);
    form.append('folder', val('vlf-up-folder') || 'Correspondence');
    form.append('visibility', VISIBILITY[val('vlf-up-visibility')] || 'PRIVILEGED');
    form.append('author', me());
    t('Uploading…', f.name, 'b');
    api('POST', 'documents/upload', form).then(res => {
      DOCUMENTS[res.key] = res.data;
      closeDocWorkspace();
      t('Uploaded and sealed', `${res.data.title} · SHA-256 ${res.data.file.sha256.slice(0, 12)}…`, 'g');
    }).catch(saveFailed);
  };

  /* ══ STAFF & SETTINGS ══ */

  const AV_CLASS = role => /Partner/.test(role) ? 'partner' : /Junior|Pupil/.test(role) ? 'junior' : /Administrator|Assistant/.test(role) ? 'admin' : 'assoc';
  const STATUS_PILL = { Available: 'ok', 'In court': 'warn', 'On leave': 'info', 'Light load': 'info' };

  function workloadRows() {
    return advocates().slice().sort((a, b) => b.monthHours - a.monthHours).map(s => {
      const pct = Math.min(100, Math.round(s.monthHours / 80 * 100));
      const colour = s.monthHours >= 45 ? 'var(--vu)' : s.monthHours >= 30 ? 'var(--gold-d)' : 'var(--slate)';
      return `<div class="workload-row"><div class="wl-name">${esc(s.name)}</div><div class="wl-bar"><div class="wl-fill" style="width:${pct}%;background:${colour};"></div></div><div class="wl-hrs">${s.monthHours.toFixed(1)} hrs</div></div>`;
    }).join('');
  }

  function renderPeople() {
    document.querySelectorAll('#pg-adm-home .workload-row, #pg-adm-people .workload-row').forEach(row => {
      const card = row.parentElement;
      if (card && !card.dataset.vlfWorkload) card.dataset.vlfWorkload = '1';
    });
    document.querySelectorAll('[data-vlf-workload]').forEach(card => {
      card.querySelectorAll('.workload-row').forEach(r => r.remove());
      card.insertAdjacentHTML('beforeend', workloadRows());
    });

    const cards = document.querySelectorAll('#pg-adm-people .pgbody > .card:not(#vlf-signups)');
    const team = cards[1];
    if (!team) return;
    renderSignups(team);
    team.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
        <div class="section-label" style="margin:0;">Team — ${esc(firmName())}</div>
        <button class="btn btn-v btn-sm" onclick="VLFOPS.openStaffForm()">+ Add staff member</button>
      </div>
      <div style="display:flex;flex-direction:column;">
        ${S.staff.map(s => `
          <div style="display:flex;align-items:center;gap:11px;padding:8px 0;border-bottom:1px solid rgba(28,43,43,.06);cursor:pointer;${s.active ? '' : 'opacity:.5;'}" onclick="VLFOPS.openStaffForm(${arg(s.id)})">
            <div class="av ${AV_CLASS(s.role)}" style="width:34px;height:34px;font-size:11px;">${esc(s.initials)}</div>
            <div style="flex:1;"><div style="font-size:13px;font-weight:500;color:var(--ink);">${esc(s.name)}</div><div style="font-size:11px;color:var(--slate);">${esc(s.role)} · ${s.activeMatters} matter${s.activeMatters === 1 ? '' : 's'}${s.rate ? ' · ' + money(s.rate) + '/hr' : ''}${s.email ? ' · ' + esc(s.email) : ''}</div></div>
            <span class="pill ${s.active ? (STATUS_PILL[s.status] || 'info') : 'ruby'}">${esc(s.active ? s.status : 'Inactive')}</span>
          </div>`).join('')}
      </div>`;
  }

  /* Account requests from the sign-in page wait here for a partner or the administrator. */
  function renderSignups(team) {
    let card = document.getElementById('vlf-signups');
    const list = S.signupRequests || [];
    if (!list.length) { if (card) card.remove(); return; }
    if (!card) {
      card = document.createElement('div');
      card.className = 'card';
      card.id = 'vlf-signups';
      team.parentElement.insertBefore(card, team);
    }
    card.innerHTML = `
      <div class="section-label" style="margin:0 0 6px;">Account requests · ${list.length}</div>
      ${list.map(r => `
        <div style="display:flex;align-items:center;gap:11px;padding:8px 0;border-bottom:1px solid rgba(28,43,43,.06);flex-wrap:wrap;">
          <div class="av ${r.as === 'staff' ? 'assoc' : 'client'}" style="width:34px;height:34px;font-size:11px;">${esc(r.name.split(/\s+/).map(p => p[0] || '').slice(0, 2).join('').toUpperCase())}</div>
          <div style="flex:1;min-width:180px;"><div style="font-size:13px;font-weight:500;color:var(--ink);">${esc(r.name)}</div>
            <div style="font-size:11px;color:var(--slate);">${r.as === 'staff' ? 'Staff' : 'Client'}${r.organisation ? ' · ' + esc(r.organisation) : ''} · ${esc(r.email)}${r.phone ? ' · ' + esc(r.phone) : ''}</div></div>
          <button class="btn btn-v btn-sm" onclick="VLFOPS.openSignup(${arg(r.id)})">Review</button>
        </div>`).join('')}`;
  }

  function openSignup(id) {
    const r = (S.signupRequests || []).find(x => String(x.id) === String(id));
    if (!r) return;
    const roles = ['Senior Partner', 'Partner', 'Associate', 'Junior Associate', 'Pupil Advocate', 'Legal Assistant', 'Firm Administrator'];
    const clients = [['', 'New client: ' + (r.organisation || r.name)]].concat(S.clients.map(c => [c.id, c.name]));
    modal('Account request', r.name + ' · ' + r.email, `
      <div class="create-task-form">
        <div style="font-size:12px;color:var(--slate);line-height:1.5;">Asked for ${r.as === 'staff' ? 'a staff account' : 'a client account'}${r.organisation ? ' for ' + esc(r.organisation) : ''}. Choose what they can open, or decline.</div>
        ${field('Give access as', select('vsu-as', [['client', 'Client (portal: their own matters only)'], ['staff', 'Staff member']], r.as))}
        <div id="vsu-staff" class="ctf-row">${field('Role', select('vsu-title', roles, 'Associate'))}${field('Hourly rate (UGX)', input('vsu-rate', '', 'e.g. 450000'))}</div>
        <div id="vsu-client">${field('Client record', select('vsu-client-id', clients, ''))}</div>
        ${formError()}
        <div style="display:flex;gap:8px;">
          <button class="btn btn-out btn-full" style="flex:1;" onclick="VLFOPS.declineSignup(${arg(r.id)})">Decline</button>
          <button class="btn btn-v btn-full" style="flex:1;" onclick="VLFOPS.approveSignup(${arg(r.id)})">Approve</button>
        </div>
      </div>`);
    const sync = () => {
      const staff = val('vsu-as') === 'staff';
      document.getElementById('vsu-staff').style.display = staff ? '' : 'none';
      document.getElementById('vsu-client').style.display = staff ? 'none' : '';
    };
    document.getElementById('vsu-as').addEventListener('change', sync);
    sync();
  }

  function approveSignup(id) {
    const r = (S.signupRequests || []).find(x => String(x.id) === String(id));
    const as = val('vsu-as');
    const body = as === 'staff'
      ? { as, title: val('vsu-title'), rate: parseInt(val('vsu-rate').replace(/[^0-9]/g, '') || '0', 10) }
      : { as, clientId: val('vsu-client-id') ? parseInt(val('vsu-client-id'), 10) : null, clientName: r ? (r.organisation || r.name) : '' };
    api('POST', 'signups/' + id + '/approve', body).then(res => {
      S.signupRequests = (S.signupRequests || []).filter(x => String(x.id) !== String(id));
      if (res.staff) S.staff.push(res.staff);
      if (res.client) {
        const i = S.clients.findIndex(c => c.id === res.client.id);
        if (i >= 0) S.clients[i] = res.client; else S.clients.push(res.client);
      }
      closeM('m-ob-workspace');
      renderAll();
      t('Account approved', `${r ? r.name : 'They'} can sign in now`, 'g');
    }).catch(err => err.fromServer ? showFormError(err.message) : saveFailed(err));
  }

  function declineSignup(id) {
    const r = (S.signupRequests || []).find(x => String(x.id) === String(id));
    if (!confirm(`Decline ${r ? r.name : 'this request'}? Their request is deleted and they get an email saying so.`)) return;
    api('DELETE', 'signups/' + id).then(() => {
      S.signupRequests = (S.signupRequests || []).filter(x => String(x.id) !== String(id));
      closeM('m-ob-workspace');
      renderPeople();
      t('Request declined', r ? r.email : '', 'r');
    }).catch(err => err.fromServer ? showFormError(err.message) : saveFailed(err));
  }

  function openStaffForm(id) {
    const s = S.staff.find(x => String(x.id) === String(id)) || {};
    const roles = ['Senior Partner', 'Partner', 'Associate', 'Junior Associate', 'Pupil Advocate', 'Legal Assistant', 'Firm Administrator'];
    modal(s.id ? 'Edit staff member' : 'Add staff member', s.id ? s.name : firmName(), `
      <div class="create-task-form">
        ${s.id ? '' : field('Full name *', input('vs-name', '', 'e.g. Rita Nakato'))}
        <div class="ctf-row">${field('Role', select('vs-role', roles, s.role || 'Associate'))}${field('Status', select('vs-status', ['Available', 'In court', 'On leave', 'Light load'], s.status || 'Available'))}</div>
        <div class="ctf-row">${field('Email (sign-in and notifications)', input('vs-email', s.email, 'name@firm.com', 'email'))}${field('Hourly rate (UGX)', input('vs-rate', s.rate != null ? s.rate : '', 'e.g. 450000'))}</div>
        <div style="font-size:11px;color:var(--slate);line-height:1.5;">${s.id
          ? (s.hasLogin ? `✓ Has a sign-in account. <a href="#" onclick="event.preventDefault();VLFOPS.inviteStaff(${arg(s.id)})" style="color:var(--vd);">Resend set-password email</a>` : 'No sign-in account yet — add an email and save to send them an invite.')
          : 'With an email, they get an account and an email to set their password. Partners and the administrator can manage the firm; everyone else works on matters.'}</div>
        ${s.id ? `<label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--ink);"><input type="checkbox" id="vs-active" ${s.active ? 'checked' : ''}> Active — can sign in and be assigned work</label>` : ''}
        ${formError()}
        <button class="btn btn-v btn-full" onclick="VLFOPS.saveStaff(${arg(s.id || '')})">${s.id ? 'Save changes' : 'Add to firm'}</button>
      </div>`);
  }

  function saveStaff(id) {
    const body = { role: val('vs-role'), status: val('vs-status'), email: val('vs-email') || null, rate: parseInt(val('vs-rate').replace(/[^0-9]/g, '') || '0', 10) };
    if (id) body.active = document.getElementById('vs-active').checked;
    else {
      body.name = val('vs-name');
      if (!body.name) { showFormError('Enter the full name.'); return; }
    }
    (id ? api('PATCH', 'staff/' + id, body) : api('POST', 'staff', body)).then(saved => {
      const i = S.staff.findIndex(x => x.id === saved.id);
      if (i >= 0) S.staff[i] = saved; else S.staff.push(saved);
      closeM('m-ob-workspace');
      renderPeople();
      t(id ? 'Staff record updated' : 'Staff member added', `${saved.name} · ${saved.role}`, 'g');
    }).catch(err => err.fromServer ? showFormError(err.message) : saveFailed(err));
  }

  const TOGGLE_KEYS = ['notify_deadlines', 'notify_hearings', 'notify_invoices', 'notify_unassigned'];

  function renderSettings() {
    const page = document.getElementById('pg-adm-settings');
    if (!page) return;
    const rows = page.querySelectorAll('.settings-block')[0];
    if (rows) {
      const r = rows.querySelectorAll('.settings-row');
      if (r[0]) {
        const sub = r[0].querySelector('.settings-sub');
        if (sub) sub.textContent = firmName();
        const btn = r[0].querySelector('button');
        if (btn) btn.setAttribute('onclick', 'VLFOPS.openFirmForm()');
      }
      if (r[1]) { const sub = r[1].querySelector('.settings-sub'); if (sub) sub.textContent = S.settings.firm_address || ''; }
    }
    page.querySelectorAll('.toggle').forEach((btn, i) => {
      const key = TOGGLE_KEYS[i];
      if (!key) return;
      btn.classList.toggle('on', S.settings[key] !== false);
      btn.setAttribute('onclick', `VLFOPS.toggleSetting('${key}', this)`);
    });
    const hs = page.querySelector('.hs');
    if (hs) hs.textContent = [firmName(), (S.settings.firm_address || '').split(',').pop().trim()].filter(Boolean).join(' · ');
  }

  function toggleSetting(key, btn) {
    const value = !btn.classList.contains('on');
    btn.classList.toggle('on', value);
    api('PUT', 'settings/' + key, { value }).then(() => {
      S.settings[key] = value;
      t('Setting saved', (value ? 'On · ' : 'Off · ') + btn.closest('.settings-row').querySelector('.settings-lbl').textContent, 'g');
    }).catch(err => { btn.classList.toggle('on', !value); saveFailed(err); });
  }

  function openFirmForm() {
    modal('Firm details', 'Shown across the firm workspace', `
      <div class="create-task-form">
        ${field('Firm name', input('vf-name', firmName()))}
        ${field('Address', input('vf-address', S.settings.firm_address))}
        ${formError()}
        <button class="btn btn-v btn-full" onclick="VLFOPS.saveFirm()">Save</button>
      </div>`);
  }

  function saveFirm() {
    const name = val('vf-name');
    const address = val('vf-address');
    if (!name) { showFormError('Enter the firm name.'); return; }
    Promise.all([api('PUT', 'settings/firm_name', { value: name }), api('PUT', 'settings/firm_address', { value: address || '—' })])
      .then(() => {
        S.settings.firm_name = name;
        S.settings.firm_address = address || '—';
        closeM('m-ob-workspace');
        renderSettings();
        t('Firm details saved', name, 'g');
      })
      .catch(err => err.fromServer ? showFormError(err.message) : saveFailed(err));
  }

  /* ══ NOTIFICATIONS ══ */

  const NOTIF_ICON = { critical: 'stop', action: 'bell', warn: 'alert', info: 'info' };
  const myNotifications = () => S.notifications[me()] || [];

  window.renderNotifPanel = function () {
    const list = document.getElementById('notif-list');
    const items = myNotifications();
    const unread = items.filter(n => n.unread).length;
    const badge = document.getElementById('notif-badge');
    if (badge) { badge.textContent = unread || ''; badge.style.display = unread ? 'flex' : 'none'; }
    // Keep the page's own NOTIFICATIONS array in step for any code that still reads it.
    if (typeof NOTIFICATIONS !== 'undefined') {
      NOTIFICATIONS.splice(0, NOTIFICATIONS.length, ...items.map(n => ({ ...n, action: () => openNotification(n.id) })));
    }
    if (!list) return;
    list.innerHTML = items.length ? items.map(n => `
      <div class="notif-item ${n.unread ? 'unread' : 'read'}" onclick="VLFOPS.openNotification(${arg(n.id)})">
        <div class="notif-icon">${ic(NOTIF_ICON[n.type] || 'info', 18)}</div>
        <div class="notif-body">
          <div class="notif-type ${esc(n.type)}">${esc(n.typeLabel)}</div>
          <div class="notif-text">${esc(n.text)}</div>
          <div class="notif-ts">${esc(n.ts)}${n.emailed ? ' · emailed' : ''}</div>
          ${n.link ? '<div class="notif-cta">Open</div>' : ''}
        </div>
      </div>`).join('') : `<div class="notif-empty">No notifications for ${esc(me())}</div>`;
  };

  window.clearAllNotifs = function () {
    const user = me();
    myNotifications().forEach(n => { n.unread = false; });
    renderNotifPanel();
    api('POST', 'notifications/read-all', { recipient: user }).catch(saveFailed);
  };

  function openNotification(id) {
    const n = myNotifications().find(x => String(x.id) === String(id));
    if (!n) return;
    if (n.unread) {
      n.unread = false;
      api('POST', 'notifications/' + n.id + '/read').catch(saveFailed);
    }
    renderNotifPanel();
    closeNotifPanel();
    goTo(n.link);
  }

  function goTo(link) {
    if (!link) return;
    if (link.page) {
      const shell = link.page.split('-')[0];
      if (['adv', 'cli', 'adm'].includes(shell) && shell !== currentShell) setShell(shell);
      showPg(link.page);
      return;
    }
    if (!link.matter) return;
    if (link.matter !== EQUITY) { openMatterDrawer(link.matter); return; }
    if (currentShell !== 'adv') setShell('adv');
    if (link.doc) {
      NAV.openMatter(EQUITY, 'documents');
      setTimeout(() => openDoc(link.doc), 200);
    } else {
      NAV.openMatter(EQUITY, link.tab || 'overview');
    }
  }

  let lastSeen = new Set();
  let pollTimer = null;

  function pollNotifications() {
    const user = me();
    return api('GET', 'notifications?recipient=' + encodeURIComponent(user)).then(list => {
      const fresh = list.filter(n => n.unread && !lastSeen.has(n.id));
      S.notifications[user] = list;
      list.forEach(n => lastSeen.add(n.id));
      renderNotifPanel();
      if (fresh.length && user === me()) t(fresh[0].typeLabel, fresh[0].text.slice(0, 90), fresh[0].type === 'critical' ? 'r' : 'b');
    }).catch(err => console.error('[vlf-ops] notifications', err));
  }

  function pollSoon() { setTimeout(pollNotifications, 400); }

  function resetSeen() {
    lastSeen = new Set(myNotifications().map(n => n.id));
  }

  /* ══ DASHBOARDS — built from the saved data, empty when there is none ══ */

  const DAY = 86400000;
  const startOfToday = () => { const d = new Date(); d.setHours(0, 0, 0, 0); return d; };
  const longDate = d => d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  const greeting = () => { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; };
  const firstName = name => String(name || '').split(' ')[0];
  const byDate = (a, b) => (a || '').localeCompare(b || '');

  function emptyBlock(icon, title, desc, buttons) {
    return `<div class="empty-state"><div class="empty-state-icon">${ic(icon, 34)}</div><div class="empty-state-title">${esc(title)}</div><div class="empty-state-desc">${esc(desc)}</div>${buttons ? `<div style="display:flex;gap:7px;flex-wrap:wrap;justify-content:center;">${buttons}</div>` : ''}</div>`;
  }

  function section(title, more, content) {
    return `<div><div class="sh"><div class="st"><div class="r-rule"><div class="a"></div><div class="b"></div><div class="c"></div></div><div class="stitle">${esc(title)}</div></div>${more || ''}</div>${content}</div>`;
  }
  const quiet = text => `<div class="card" style="font-size:12px;color:var(--slate);">${esc(text)}</div>`;
  const moreBtn = (label, onclick) => `<button class="smore" onclick="${onclick}">${esc(label)}</button>`;

  function heroHtml(kicker, title, sub, figure, figureLabel, figureColour) {
    return `<div class="hero-i"><div class="hero-row">
      <div><div class="vlf-kicker">${esc(kicker)}</div>
      <div class="hg" style="font-size:22px;">${title}</div><div class="hs">${esc(sub)}</div></div>
      ${figure === undefined ? '' : `<div style="text-align:right;"><div style="font-family:var(--serif);font-size:40px;font-weight:300;line-height:1;color:${figureColour || 'var(--white)'};">${esc(figure)}</div><div class="vlf-figure-label">${esc(figureLabel)}</div></div>`}
    </div></div>`;
  }

  function eventCard(e) {
    const m = matter(e.matter);
    const [pillCls, pillText] = LEVEL_PILL[e.level] || LEVEL_PILL.scheduled;
    return `<div class="diary-event ${esc(e.level)}" onclick="openDiaryEvent(${arg(e.id)})">
      <div class="de-time">${esc(e.time || '—')}</div>
      <div class="de-body"><div class="de-matter">${esc(e.matter)}${m ? ' · ' + esc(m.client || '') : ''}</div><div class="de-title">${esc(e.title)}</div>
      <div class="de-court">${esc(e.dateLabel)}${e.court ? ' · ' + esc(e.court) : ''}</div></div>
      <div class="de-right"><span class="pill ${pillCls}">${pillText}</span></div></div>`;
  }

  function taskCard(tk) {
    const cls = tk.status === 'BLOCKED' ? 'urgent' : tk.status === 'IN_PROGRESS' ? 'pending' : '';
    return `<div class="task-item ${cls}" onclick="openTaskWorkspace(${arg(tk.id)})">
      <div class="task-cb"></div>
      <div class="task-info"><div class="task-title">${esc(tk.title)}</div><div class="task-matter">${esc(tk.matter)} · ${esc(tk.matterTitle || '')}</div>
      <div class="task-meta">${esc(tk.status.replace('_', ' '))}${tk.blockedBy ? ' · ' + esc(tk.blockedBy) : ''}</div></div>
      <div class="task-due ${tk.status === 'BLOCKED' ? 'urg' : 'warn'}">${esc(tk.deadline || '')}</div></div>`;
  }

  function attentionRow(icon, bg, border, title, meta, onclick, cta) {
    return `<div style="display:flex;align-items:flex-start;gap:9px;padding:10px 12px;background:${bg};border-radius:var(--r-sm);border-left:3px solid ${border};cursor:pointer;" onclick="${onclick}">
      <span style="flex-shrink:0;display:flex;color:${border};padding-top:1px;">${ic(icon, 18)}</span>
      <div style="flex:1;"><div style="font-size:12px;font-weight:500;color:var(--ink);">${esc(title)}</div><div style="font-size:11px;color:var(--slate);">${esc(meta)}</div></div>
      <span style="font-family:var(--mono);font-size:9px;color:${border};">${esc(cta)}</span></div>`;
  }

  function reviewsFor(user) {
    return Object.entries(DOCUMENTS).filter(([, d]) => d && d.reviewer === user && ['UNDER_REVIEW', 'PENDING_PARTNER_APPROVAL'].includes(d.currentStatus));
  }

  function commandData() {
    const user = me();
    const mine = matters().filter(m => m.advocate === user || m.supervisor === user);
    const refs = new Set(mine.map(m => m.ref));
    const deadlines = S.deadlines.filter(d => !d.done && (d.owner === user || refs.has(d.matter))).sort((a, b) => byDate(a.dueDate, b.dueDate));
    const reviews = reviewsFor(user);
    const tasks = Object.values(TASKS).filter(tk => tk.assignedTo === user && tk.status !== 'DONE');
    const blockedForMe = Object.values(TASKS).filter(tk => tk.assignedBy === user && tk.status === 'BLOCKED');
    const events = S.events.filter(e => e.level !== 'complete' && (e.advocate === user || refs.has(e.matter))).sort((a, b) => byDate(a.date + (a.time || ''), b.date + (b.time || '')));
    const attention = deadlines.filter(d => d.severity === 'critical').length + reviews.length + blockedForMe.length;
    return { user, mine, deadlines, reviews, tasks, blockedForMe, events, attention };
  }

  function renderCommand() {
    const page = document.getElementById('pg-adv-command');
    if (!page) return;
    const d = commandData();
    const critical = d.deadlines.filter(x => x.severity === 'critical');
    const hero = page.querySelector(':scope > .hero');
    if (hero) hero.innerHTML = heroHtml(longDate(new Date()) + ' · ' + d.user, 'Command Centre',
      `${firmName()} · ${d.mine.length} active matter${d.mine.length === 1 ? '' : 's'}`, critical.length, 'Critical deadlines', critical.length ? 'var(--ember)' : 'var(--white)');

    const body = page.querySelector(':scope > .pgbody');
    if (!body) return;
    if (!matters().length) {
      body.innerHTML = `<div class="mb-greeting" style="padding:6px 0 0;">${greeting()}, ${esc(firstName(d.user))}.</div>` +
        emptyBlock('briefcase', 'No matters yet', 'The firm has no matters on record. Open a new matter through intake to get started — deadlines, hearings, tasks and notifications will appear here.',
          `<button class="btn btn-v btn-sm" onclick="openIntake()">+ Open new matter</button><button class="btn btn-ghost btn-sm" onclick="VLFOPS.openClientForm()">+ Add client</button>`);
      return;
    }

    const attention = [
      ...critical.map(x => attentionRow('stop', 'var(--ember-p)', 'var(--ember)', x.title, `${x.matter} · due ${x.dueLabel}${x.dueTime ? ' ' + x.dueTime : ''}`, `showPg('adv-deadlines')`, 'Deadline')),
      ...d.reviews.map(([key, doc]) => attentionRow('pen', 'rgba(37,99,168,.06)', 'var(--sky)', `Review: ${doc.title}`, `${doc.matterId} · submitted by ${doc.author || '—'}`, `VLFOPS.goTo({matter:${arg(doc.matterId)},doc:${arg(key)}})`, 'Review')),
      ...d.blockedForMe.map(tk => attentionRow('alert', 'rgba(139,115,53,.07)', 'var(--gold-d)', `Blocked: ${tk.title}`, `${tk.assignedTo} · ${tk.blockedBy || ''}`, `openTaskWorkspace(${arg(tk.id)})`, 'Unblock'))
    ];

    body.innerHTML = `
      <div class="monday-brief" style="padding:0;">
        <div class="mb-greeting">${greeting()}, ${esc(firstName(d.user))}.</div>
        <div class="mb-date">${esc(longDate(new Date()))} · ${esc(firmName())}</div>
        <div class="mb-attention">${attention.length ? `${attention.length} thing${attention.length === 1 ? '' : 's'} need${attention.length === 1 ? 's' : ''} your attention:` : 'Nothing needs your attention right now.'}</div>
        <div style="display:flex;flex-direction:column;gap:6px;">${attention.join('')}</div>
      </div>
      ${section('Upcoming in court', moreBtn('Full diary', "showPg('adv-diary')"), d.events.length ? `<div style="display:flex;flex-direction:column;gap:7px;">${d.events.slice(0, 4).map(eventCard).join('')}</div>` : quiet('No hearings scheduled.'))}
      ${section('My tasks', moreBtn('Full queue', "showPg('adv-queue')"), d.tasks.length ? d.tasks.slice(0, 5).map(taskCard).join('') : quiet('No open tasks assigned to you.'))}
      ${section('Upcoming deadlines', moreBtn('All deadlines', "showPg('adv-deadlines')"), d.deadlines.length ? `<div class="card">${d.deadlines.slice(0, 5).map(x => deadlineRow(x, true)).join('')}</div>` : quiet('No open deadlines.'))}
      ${section('My matters', moreBtn('All matters', "showPg('adv-matters')"), d.mine.length ? `<div class="matter-list">${d.mine.slice(0, 5).map(m => matterRow(m, false)).join('')}</div>` : quiet('You have no matters assigned.'))}
      <div style="height:20px;"></div>`;
  }

  function adminStat(value, label, cls, big) {
    return `<div class="admin-stat" style="border-radius:var(--r);"><div class="admin-n ${cls || ''}" ${big ? 'style="font-size:22px;"' : ''}>${esc(value)}</div><div class="admin-l">${esc(label)}</div></div>`;
  }

  function invoiceTotals() {
    const now = new Date();
    const thisMonth = i => { if (!i.issueDate) return false; const d = new Date(i.issueDate); return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear(); };
    const outstanding = INVOICES.filter(i => i.status === 'ISSUED' || i.status === 'OVERDUE');
    return {
      outstanding,
      outstandingSum: outstanding.reduce((s, i) => s + i.total - i.paid, 0),
      invoicedMonth: INVOICES.filter(thisMonth).reduce((s, i) => s + i.total, 0),
      collected: INVOICES.reduce((s, i) => s + i.paid, 0)
    };
  }

  const compact = n => n >= 1e6 ? 'UGX ' + (n / 1e6).toFixed(1) + 'M' : money(n);

  function receivableRow(i) {
    return `<div class="recv-row" onclick="openInvoiceWorkspace(${arg(i.id)})"><div><div class="recv-matter">${esc(i.id)} · ${esc(i.matter)}</div><div class="recv-client">${esc(i.client)}</div></div><div style="text-align:right;"><div class="recv-amt" style="color:var(--ember);">${money(i.total - i.paid)}</div><div class="recv-age" style="color:var(--slate);">Due ${esc(i.dueDate || '—')}</div></div></div>`;
  }

  function renderAdminHome() {
    const page = document.getElementById('pg-adm-home');
    if (!page) return;
    const all = matters();
    const critical = S.deadlines.filter(d => !d.done && d.severity === 'critical');
    const today = startOfToday().getTime();
    const week = S.events.filter(e => { const t = new Date(e.date).getTime(); return e.level !== 'complete' && t >= today && t < today + 7 * DAY; });
    const unassigned = all.filter(m => !m.advocate);
    const inv = invoiceTotals();
    const hours = S.staff.reduce((s, x) => s + (x.monthHours || 0), 0);

    const hero = page.querySelector(':scope > .hero');
    if (hero) hero.innerHTML = heroHtml(longDate(new Date()) + ' · ' + me(), 'Firm Dashboard', `${firmName()} · Is the firm operating properly?`);
    const body = page.querySelector(':scope > .pgbody');
    if (!body) return;

    const alerts = [
      ...critical.map(d => attentionRow('stop', 'var(--white)', 'var(--ember)', d.title, `${d.matter} · ${d.owner || 'Unassigned'} · due ${d.dueLabel}`, `showPg('adm-deadlines')`, 'Critical')),
      ...(inv.outstanding.length ? [attentionRow('wallet', 'var(--white)', 'var(--ember)', `${inv.outstanding.length} invoice${inv.outstanding.length === 1 ? '' : 's'} outstanding — ${money(inv.outstandingSum)}`, 'Awaiting client payment', `showPg('adm-billing')`, 'Billing')] : []),
      ...(unassigned.length ? [attentionRow('alert', 'var(--white)', 'var(--gold-d)', `${unassigned.length} matter${unassigned.length === 1 ? '' : 's'} without an advocate`, unassigned.map(m => m.ref).join(' · '), `showPg('adm-matters')`, 'Assign')] : [])
    ];

    body.innerHTML = `
      <div style="background:var(--ink);border-radius:var(--r);padding:12px;display:flex;flex-direction:column;gap:8px;">
      <div class="admin-grid">
        ${adminStat(all.length, 'Active Matters')}${adminStat(critical.length, 'Critical Deadlines', critical.length ? 'warn' : '')}
        ${adminStat(inv.outstanding.length, 'Outstanding Invoices', inv.outstanding.length ? 'urg' : '')}${adminStat(week.length, 'Hearings This Week', 'ok')}
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        ${adminStat(compact(inv.invoicedMonth), 'Invoiced This Month', 'ok', true)}${adminStat(compact(inv.outstandingSum), 'Outstanding Receivables', inv.outstandingSum ? 'urg' : '', true)}
        ${adminStat(hours.toFixed(1) + ' hrs', 'Lawyer Hours — This Month', 'warn', true)}${adminStat(unassigned.length, 'Unassigned Matters', unassigned.length ? 'urg' : '', true)}
      </div>
      </div>
      ${section('Firm alerts', '', alerts.length ? `<div style="display:flex;flex-direction:column;gap:6px;">${alerts.join('')}</div>` : quiet(all.length ? 'No alerts — everything is on track.' : 'No matters on record yet.'))}
      ${section('Advocate workload', moreBtn('Full view', "showPg('adm-people')"), `<div class="card" data-vlf-workload="1">${workloadRows()}</div>`)}
      ${section('Outstanding receivables', moreBtn('All', "showPg('adm-billing')"), inv.outstanding.length ? `<div class="card" style="padding:0;overflow:hidden;">${inv.outstanding.map(receivableRow).join('')}</div>` : quiet('No outstanding invoices.'))}
      <div style="height:20px;"></div>`;
  }

  function renderAdminBilling() {
    const page = document.getElementById('pg-adm-billing');
    const body = page && page.querySelector(':scope > .pgbody');
    if (!body) return;
    const inv = invoiceTotals();
    const hs = page.querySelector('.hs');
    if (hs) hs.textContent = `${firmName()} · ${INVOICES.length} invoice${INVOICES.length === 1 ? '' : 's'}`;
    body.innerHTML = `
      <div class="admin-grid" style="background:var(--ink);border-radius:var(--r);padding:12px;">
        ${adminStat(compact(inv.invoicedMonth), 'Invoiced This Month', 'ok', true)}${adminStat(compact(inv.outstandingSum), 'Outstanding', inv.outstandingSum ? 'warn' : '', true)}
        ${adminStat(INVOICES.filter(i => i.status === 'DRAFT' || i.status === 'APPROVED').length, 'Awaiting Issue', '', true)}${adminStat(compact(inv.collected), 'Collected', 'ok', true)}
      </div>
      <div class="card"><div class="section-label">All invoices</div>
        ${INVOICES.length ? INVOICES.slice().reverse().map(i => `<div class="recv-row" onclick="openInvoiceWorkspace(${arg(i.id)})"><div><div class="recv-matter">${esc(i.id)} · ${esc(i.matter)}</div><div class="recv-client">${esc(i.client)}</div></div><div style="text-align:right;"><div class="recv-amt">${money(i.total)}</div><div class="recv-age"><span class="inv-status ${i.status.toLowerCase()}">${esc(i.status)}</span></div></div></div>`).join('') : '<div style="font-size:12px;color:var(--slate);padding:6px 0;">No invoices yet. Invoices are drafted from a matter’s unbilled time.</div>'}
      </div>
      <div style="height:20px;"></div>`;
  }

  function renderAdminTime() {
    const page = document.getElementById('pg-adm-time');
    const body = page && page.querySelector(':scope > .pgbody');
    if (!body) return;
    const people = advocates();
    const hours = people.reduce((s, x) => s + (x.monthHours || 0), 0);
    const value = people.reduce((s, x) => s + (x.monthHours || 0) * (x.rate || 0), 0);
    const hs = page.querySelector('.hs');
    if (hs) hs.textContent = `All advocates · ${new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })}`;
    body.innerHTML = `
      <div class="card" style="background:var(--ink);">
        <div class="section-label" style="color:var(--slate);opacity:.38;">Firm total — this month</div>
        <div style="font-family:var(--serif);font-size:36px;font-weight:300;color:var(--white);">${hours.toFixed(1)} <span style="font-size:18px;color:var(--vu);">hrs</span></div>
        <div style="font-size:11px;color:var(--slate);margin-top:4px;">${compact(value)} billable value · ${people.length} advocate${people.length === 1 ? '' : 's'}</div>
      </div>
      <div class="card"><div class="section-label">By advocate</div>
        ${people.map(s => `<div class="time-row"><div><div class="time-who">${esc(s.name)}</div><div class="time-desc">${esc(s.role)} · ${money(s.rate)}/hr</div></div><div><div class="time-hrs">${(s.monthHours || 0).toFixed(1)} hrs</div><div class="time-amt">${compact((s.monthHours || 0) * (s.rate || 0))}</div></div></div>`).join('')}
      </div>
      <div style="height:20px;"></div>`;
  }

  function renderAdvTime() {
    const page = document.getElementById('pg-adv-time');
    const body = page && page.querySelector(':scope > .pgbody');
    if (!body) return;
    const user = me();
    const entries = TIME_ENTRIES.filter(te => te.advocate === user);
    const mins = entries.reduce((s, te) => s + (te.durationMins || 0), 0);
    const billable = entries.filter(te => te.billable).reduce((s, te) => s + te.amount, 0);
    const hs = page.querySelector('.hs');
    if (hs) hs.textContent = `${user} · ${entries.length} time entr${entries.length === 1 ? 'y' : 'ies'}`;
    body.innerHTML = `
      <div class="card" style="background:var(--ink);">
        <div class="section-label" style="color:var(--slate);opacity:.38;">Recorded time</div>
        <div style="font-family:var(--serif);font-size:36px;font-weight:300;color:var(--white);margin-bottom:4px;">${(mins / 60).toFixed(1)} <span style="font-size:18px;color:var(--vu);">hrs</span></div>
        <div style="font-size:11px;color:var(--slate);">${money(billable)} billable</div>
      </div>
      <div style="display:flex;justify-content:flex-end;"><button class="btn btn-v btn-sm" onclick="openTimeLogger('')">+ Log time</button></div>
      <div class="card"><div class="section-label">Time entries</div>
        ${entries.length ? entries.map(te => `<div class="time-row"><div><div class="time-who">${esc(te.matter)}${matter(te.matter) ? ' — ' + esc(matter(te.matter).title) : ''}</div><div class="time-desc">${esc(te.desc)}</div></div><div><div class="time-hrs">${esc(te.duration)}</div><div class="time-amt">${esc(te.date)}</div></div></div>`).join('') : '<div style="font-size:12px;color:var(--slate);padding:6px 0;">No time recorded yet.</div>'}
      </div>
      <div style="height:20px;"></div>`;
  }

  /* Client portal: only the signed-in client's own matters. */
  function clientContext() {
    const client = S.clients.find(c => c.id === ME.clientId);
    const refs = new Set(client ? client.matters : []);
    return { client, list: matters().filter(m => refs.has(m.ref)), refs };
  }

  function renderClientPortal() {
    const { client, list, refs } = clientContext();
    const noMatters = emptyBlock('briefcase', 'No active matters', 'When the firm opens a matter for you, its progress, documents and invoices will appear here.');
    const setBody = (id, html) => { const b = document.querySelector('#' + id + ' > .pgbody'); if (b) b.innerHTML = html + '<div style="height:20px;"></div>'; };

    const home = document.getElementById('pg-cli-home');
    const hero = home && home.querySelector(':scope > .hero');
    if (hero) hero.innerHTML = heroHtml(longDate(new Date()) + (client ? ' · ' + client.name : ''), `${greeting()}, <span class="hi">${esc(firstName(me()))}.</span>`, `${client ? client.name + ' · ' : ''}Legal Matters Portal · ${firmName()}`);

    const invoices = INVOICES.filter(i => refs.has(i.matter) && i.status !== 'DRAFT' && i.status !== 'APPROVED');
    const owed = invoices.filter(i => i.status === 'ISSUED' || i.status === 'OVERDUE');
    setBody('pg-cli-home', !list.length ? noMatters : list.map(m => {
      const next = S.events.filter(e => e.matter === m.ref && e.level !== 'complete').sort((a, b) => byDate(a.date, b.date))[0];
      return `<div class="client-matter"><div class="cm-head"><div class="cm-id">${esc(m.ref)} · ${esc(m.court || '')}</div><div class="cm-title">${esc(m.title)}</div><div class="cm-status"><span class="pill warn">${esc(m.stage || 'Open')}</span></div></div>
        <div class="cm-body">
          <div class="cm-row"><div><div class="cm-label">Your legal team</div><div class="cm-val">${esc(m.advocate || 'Being assigned')}${m.supervisor ? ' · ' + esc(m.supervisor) + ' (partner)' : ''}</div></div></div>
          <div class="cm-row"><div><div class="cm-label">Next court event</div><div class="cm-val">${next ? esc(next.title + ' — ' + next.dateLabel + (next.time ? ' · ' + next.time : '')) : 'None scheduled'}</div></div></div>
        </div></div>`;
    }).join('') + (owed.length ? `<div class="card" style="border-left:3px solid var(--ember);"><div class="section-label" style="color:var(--ember);">Outstanding balance</div><div style="font-family:var(--serif);font-size:26px;font-weight:300;color:var(--ember);">${money(owed.reduce((s, i) => s + i.total - i.paid, 0))}</div><button class="btn btn-ember btn-sm" style="margin-top:8px;" onclick="showPg('cli-billing')">View invoices</button></div>` : ''));

    setBody('pg-cli-matter', !list.length ? noMatters : list.map(m => `<div class="card"><div class="cm-label">${esc(m.ref)}</div><div style="font-size:14px;font-weight:500;color:var(--ink);margin:4px 0;">${esc(m.title)}</div><div style="font-size:11px;color:var(--slate);">${esc([m.court, m.stage, m.advocate].filter(Boolean).join(' · '))}</div>${m.description ? `<div style="font-size:12px;color:var(--ink);margin-top:6px;">${esc(m.description)}</div>` : ''}</div>`).join(''));

    const docs = Object.values(DOCUMENTS).filter(d => d && refs.has(d.matterId) && d.visibility === 'CLIENT_APPROVED');
    setBody('pg-cli-docs', !list.length ? noMatters : docs.length ? docs.map(d => `<div class="card" style="display:flex;align-items:center;gap:10px;"><div style="display:flex;color:var(--slate);">${ic('file', 18)}</div><div style="flex:1;"><div style="font-size:12px;font-weight:500;color:var(--ink);">${esc(d.title)}</div><div style="font-family:var(--mono);font-size:9px;color:var(--slate);margin-top:2px;">${esc(d.matterId)} · ${esc(docStatusLabel(d.currentStatus))}</div></div>${d.file ? `<a class="btn btn-ghost btn-sm" href="${esc(d.file.url)}">Download</a>` : ''}</div>`).join('') : emptyBlock('file', 'No documents shared yet', 'Documents your legal team approves for you will appear here.'));

    // One conversation with the legal team per matter ("client-{ref}").
    setBody('pg-cli-messages', !list.length ? noMatters : list.map(m => {
      const channel = 'client-' + m.ref;
      const msgs = (COMM_DATA[channel] && COMM_DATA[channel].messages) || [];
      return `<div class="card" style="margin-bottom:10px;">
        <div style="font-size:13px;font-weight:500;color:var(--ink);margin-bottom:2px;">${esc(m.title)}</div>
        <div style="font-family:var(--mono);font-size:9px;color:var(--slate);margin-bottom:10px;">${esc(m.ref)} · ${esc(m.advocate || 'Your legal team')}</div>
        ${msgs.length ? msgs.map(msg => `<div style="background:${msg.mine ? 'var(--vd)' : 'var(--parch)'};color:${msg.mine ? 'var(--white)' : 'var(--ink)'};border-radius:12px;padding:10px 12px;margin-bottom:8px;max-width:85%;${msg.mine ? 'margin-left:auto;' : ''}"><div style="font-size:12px;line-height:1.55;white-space:pre-wrap;">${esc(msg.text)}</div><div style="font-family:var(--mono);font-size:9px;opacity:.5;margin-top:4px;">${esc(msg.name || '')} · ${esc(msg.ts)}</div></div>`).join('') : '<div style="font-size:12px;color:var(--slate);padding:4px 0 10px;">No messages yet.</div>'}
        <div style="display:flex;gap:7px;border-top:1px solid rgba(28,43,43,.06);padding-top:10px;">
          <textarea class="ctf-input" id="vlf-cli-msg-${esc(m.ref)}" rows="2" placeholder="Message your legal team…" style="resize:vertical;"></textarea>
          <button class="btn btn-v btn-sm" onclick="VLFOPS.sendClientMessage(${arg(m.ref)})">Send</button>
        </div>
      </div>`;
    }).join(''));

    setBody('pg-cli-billing', !list.length ? noMatters : invoices.length ? `<div class="card">${invoices.map(i => `<div class="recv-row"><div><div class="recv-matter">${esc(i.id)} · ${esc(i.matter)}</div><div class="recv-client">Issued ${esc(i.issueDate || '—')} · Due ${esc(i.dueDate || '—')}</div></div><div style="text-align:right;"><div class="recv-amt" style="color:${i.status === 'PAID' ? 'var(--vd)' : 'var(--ember)'};">${money(i.status === 'PAID' ? i.total : i.total - i.paid)}</div><div class="recv-age">${esc(i.status === 'PAID' ? 'Paid' : 'Outstanding')}</div></div></div>`).join('')}</div>` : emptyBlock('wallet', 'No invoices', 'Invoices from the firm will appear here.'));

    const link = document.getElementById('sbi-cli-matter');
    if (link) {
      link.style.display = list.length ? '' : 'none';
      const label = link.querySelector('.sb-l');
      if (label && list[0]) label.textContent = list[0].title;
    }
    setBadge('sbi-cli-home', list.length);
    setBadge('sbi-cli-matter', 0);
    setBadge('sbi-cli-docs', docs.length);
    setBadge('sbi-cli-messages', 0);
  }

  function setBadge(id, n) {
    const b = document.querySelector('#' + id + ' .sb-b');
    if (!b) return;
    b.textContent = n;
    b.style.display = n ? '' : 'none';
  }

  function sendClientMessage(ref) {
    const box = document.getElementById('vlf-cli-msg-' + ref);
    const text = box ? box.value.trim() : '';
    if (!text) return;
    const channel = 'client-' + ref;
    api('POST', 'messages', { channel, text }).then(saved => {
      if (!COMM_DATA[channel]) COMM_DATA[channel] = { title: ref, sub: '', to: ref, context: '', messages: [] };
      COMM_DATA[channel].messages.push(saved);
      renderClientPortal();
      t('Message sent', 'Your legal team has been notified', 'g');
    }).catch(saveFailed);
  }

  /*
   * Messages page, built from the data: for every matter an internal discussion
   * ("matter-{ref}", firm only) and a client conversation ("client-{ref}"), plus a
   * direct-message channel with each colleague who has an account ("dm-{staffId}-{staffId}").
   */
  function commChannels() {
    const list = [];
    matters().sort((a, b) => b.ref.localeCompare(a.ref)).forEach(m => {
      list.push({ group: 'Matter discussions', id: 'matter-' + m.ref, name: m.ref, sub: m.title, title: m.ref + ' — internal', desc: 'Matter discussion · ' + m.title + ' · Firm only', context: 'Matter · Internal · Firm only' });
    });
    matters().filter(m => m.client).sort((a, b) => b.ref.localeCompare(a.ref)).forEach(m => {
      list.push({ group: 'Client conversations', id: 'client-' + m.ref, name: m.client, sub: m.ref, title: m.client + ' — ' + m.ref, desc: 'Visible to the client · logged', context: 'Client channel · Visible to client' });
    });
    const mine = myStaff();
    if (mine) {
      S.staff.filter(s => s.id !== mine.id && s.userId && s.active).forEach(s => {
        const ids = [mine.id, s.id].sort((a, b) => a - b).join('-');
        list.push({ group: 'Direct messages', id: 'dm-' + ids, name: s.name, sub: s.role, title: s.name, desc: 'Direct message · ' + s.role, context: 'Direct message · Internal' });
      });
    }
    list.forEach(c => {
      if (!COMM_DATA[c.id]) COMM_DATA[c.id] = { messages: [] };
      Object.assign(COMM_DATA[c.id], { title: c.title, sub: c.desc, to: c.title, context: c.context });
    });
    return list;
  }

  function renderComms() {
    const sidebar = document.querySelector('#pg-adv-comms .comm-sidebar');
    if (!sidebar) return;
    const channels = commChannels();
    if (!channels.some(c => c.id === activeChannel)) activeChannel = channels.length ? channels[0].id : '';
    let group = '';
    const items = channels.map(c => {
      const heading = c.group !== group ? `<div style="padding:8px 14px 4px;font-family:var(--mono);font-size:8px;letter-spacing:.12em;text-transform:uppercase;color:var(--slate);opacity:.35;">${esc(c.group)}</div>` : '';
      group = c.group;
      return heading + `<div class="comm-channel${c.id === activeChannel ? ' active' : ''}" onclick="switchChannel(${arg(c.id)}, ${arg(c.title)}, ${arg(c.desc)})"><div class="comm-channel-name">${esc(c.name)}</div><div class="comm-channel-sub">${esc(c.sub)}</div></div>`;
    }).join('');
    sidebar.innerHTML = `<div class="comm-sb-head"><div class="comm-sb-title">Conversations</div></div><div style="padding:0 0 8px;overflow-y:auto;">${items || '<div style="padding:14px;font-size:11px;color:var(--slate);">Conversations appear here once the firm has matters or colleagues.</div>'}</div>`;

    const data = COMM_DATA[activeChannel];
    const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
    set('comm-channel-title', data ? data.title : 'No conversation selected');
    set('comm-channel-sub', data ? data.sub : '');
    set('comm-compose-to', data ? data.to : '—');
    set('comm-compose-context', data ? data.context : '');
    const openBtn = document.querySelector('#comm-main .comm-main-head .btn');
    if (openBtn) {
      const ref = activeChannel.replace(/^(matter|client)-/, '');
      openBtn.style.display = matter(ref) ? '' : 'none';
      openBtn.setAttribute('onclick', `VLFOPS.openMatter(${arg(ref)})`);
    }
    const compose = document.querySelector('#comm-main .comm-compose');
    if (compose) compose.style.display = activeChannel ? '' : 'none';
    if (activeChannel) renderCommMessages(activeChannel);
    else { const area = document.getElementById('comm-messages-area'); if (area) area.innerHTML = ''; }
  }

  function renderQueuePage() {
    const page = document.getElementById('pg-adv-queue');
    if (!page) return;
    page.querySelectorAll('.wq-section').forEach(el => el.remove()); // sample cards from the prototype
    const hs = page.querySelector('.hs');
    if (hs) hs.textContent = `${me()} · All matters`;
    // The prototype's empty-state box sits above the page title; show the message in the page instead.
    const outside = document.getElementById('queue-empty-state');
    if (outside) outside.style.display = 'none';
    const old = document.getElementById('vlf-queue-empty');
    if (old) old.remove();
    const body = page.querySelector(':scope > .pgbody');
    if (body && !Object.keys(TASKS).length) {
      body.insertAdjacentHTML('beforeend', `<div id="vlf-queue-empty">${emptyBlock('list', 'Your queue is clear', 'No tasks yet. Tasks appear here when work is assigned on a matter.', `<button class="btn btn-ghost btn-sm" onclick="showPg('adv-command')">Go to Command Centre</button>`)}</div>`);
    }
  }

  function renderBadges() {
    const d = commandData();
    setBadge('sbi-adv-command', d.attention);
    setBadge('sbi-adv-queue', d.tasks.length);
    setBadge('sbi-adv-approvals', d.reviews.length);
    setBadge('sbi-adv-comms', 0);
    const equityLink = document.getElementById('sbi-adv-matter-open');
    if (equityLink) equityLink.style.display = matter(EQUITY) ? '' : 'none';
    setBadge('sbi-adm-billing', invoiceTotals().outstanding.length);
    ['sbi-adv-matters', 'sbi-adv-diary', 'sbi-adv-deadlines', 'sbi-adm-deadlines', 'sbi-adm-matters'].forEach(id => {
      const b = document.querySelector('#' + id + ' .sb-b');
      if (b) b.style.display = b.textContent === '0' ? 'none' : '';
    });
    // The prototype's second bell showed a fixed "2"; the working bell is the one with the live count.
    document.querySelectorAll('.tb-bell').forEach(b => { b.style.display = 'none'; });
  }

  function renderDashboards() {
    renderCommand();
    renderAdminHome();
    renderAdminBilling();
    renderAdminTime();
    renderAdvTime();
    renderClientPortal();
    renderQueuePage();
    renderComms();
    renderBadges();
  }

  // The Command Centre is built from real data; stop the prototype injecting its sample
  // panels (morning brief, "why critical" banner and scenario walkthrough cards) into it.
  ['renderMondayBrief', 'injectCommandWhyLayer', 'injectCommandCentreTrigger', 'injectBCTriggers', 'injectDEFTriggers']
    .forEach(name => { if (typeof window[name] === 'function') window[name] = function () {}; });

  /* ══ WIRING ══ */

  function renderAll() {
    renderMatters();
    renderClients();
    renderDiary();
    renderDeadlines();
    renderPeople();
    renderSettings();
    renderNotifPanel();
    renderDashboards();
  }

  function onUserChanged() {
    resetSeen();
    renderAll();
    pollNotifications().then(resetSeen);
  }

  wrap('showPg', function (original, args) {
    const result = original.apply(this, args);
    const id = args[0];
    if (id === 'adv-matters' || id === 'adm-matters') renderMatters();
    else if (id === 'adv-diary') renderDiary();
    else if (id === 'adv-deadlines' || id === 'adm-deadlines') renderDeadlines();
    else if (id === 'adv-clients') renderClients();
    else if (id === 'adm-people' || id === 'adm-home') renderPeople();
    else if (id === 'adm-settings') renderSettings();
    else if (id === 'adv-queue') { refreshQueue(true); renderQueuePage(); }
    else if (id === 'adv-command') renderCommand();
    else if (id === 'adm-billing') renderAdminBilling();
    else if (id === 'adm-time') renderAdminTime();
    else if (id === 'adv-time') renderAdvTime();
    else if (id === 'adv-comms') renderComms();
    else if (id && id.startsWith('cli-')) renderClientPortal();
    if (id === 'adm-home') renderAdminHome();
    renderBadges();
    return result;
  });

  /* ══ THE SIGNED-IN ACCOUNT ══
   * One person per session: no persona switching. The shell switcher only offers the
   * parts of the app this role may open (the server enforces the same rules). */

  const ROLE_CLASS = { partner: 'partner', associate: 'assoc', junior: 'junior', clerk: 'junior', admin: 'admin', client: 'client' };
  const allowedShells = () => (ME.shells && ME.shells.length ? ME.shells : ['adv']);

  const SIDEBAR_ICONS = {
    'adv-command': 'dashboard', 'adv-matters': 'briefcase', 'adv-matter-open': 'folder', 'adv-intake': 'plus', 'adv-diary': 'calendar',
    'adv-deadlines': 'flag', 'adv-queue': 'list', 'adv-approvals': 'approve', 'adv-time': 'timer', 'adv-research': 'book',
    'adv-templates': 'file', 'adv-comms': 'message', 'adv-clients': 'users',
    'cli-home': 'briefcase', 'cli-matter': 'folder', 'cli-docs': 'file', 'cli-messages': 'message', 'cli-billing': 'wallet',
    'adm-home': 'dashboard', 'adm-matters': 'briefcase', 'adm-deadlines': 'flag', 'adm-billing': 'wallet', 'adm-time': 'timer',
    'adm-people': 'users', 'adm-settings': 'settings'
  };

  function applyIcons() {
    Object.entries(SIDEBAR_ICONS).forEach(([id, name]) => {
      const slot = document.querySelector('#sbi-' + id + ' .sb-ic');
      if (slot) slot.innerHTML = ic(name, 17);
    });
    const search = document.querySelector('.global-search-btn span:first-child');
    if (search) search.innerHTML = ic('search', 15);
    ['file', 'list', 'message', 'timer', 'briefcase'].forEach((name, i) => {
      const slot = document.querySelectorAll('.fab-item-icon')[i];
      if (slot) slot.innerHTML = ic(name, 17);
    });
    const fab = document.getElementById('fab-main-btn');
    if (fab && !fab.querySelector('svg')) fab.innerHTML = ic('plus', 22);
  }

  function showAccount() {
    const av = document.getElementById('persona-av');
    if (av) { av.textContent = ME.initials || '?'; av.className = 'av ' + (ROLE_CLASS[ME.role] || 'assoc'); }
    const name = document.getElementById('persona-name');
    if (name) name.textContent = ME.name || '';
    const role = document.getElementById('persona-role');
    if (role) role.textContent = ME.role === 'client' && ME.clientName ? 'Client · ' + ME.clientName : (ME.roleLabel || '');
    const ctx = document.getElementById('ctx-role');
    if (ctx) { ctx.textContent = (ME.roleLabel || '') + ' · ' + (ME.name || ''); ctx.className = 'ctx-role ' + (ME.role === 'client' ? 'client' : ME.role === 'admin' ? 'admin' : ME.role === 'partner' ? 'partner' : 'associate'); }
    const firm = firmName();
    document.querySelectorAll('.firm-name').forEach(el => { el.textContent = firm.replace(/\s+Advocates$/, ''); });
    // Brand mark: the firm's first letter.
    document.querySelectorAll('.firm-logo').forEach(el => { el.dataset.initial = (firm.trim()[0] || 'G').toUpperCase(); });
    // Sidebar footer: who is signed in (replaces the prototype's "IRIS Active · v1.0").
    document.querySelectorAll('.sb-foot').forEach(foot => {
      foot.innerHTML = `<div class="sb-fv"><div class="iris-dot"></div>${esc(ME.name || '')}</div><div class="sb-fl">${esc(ME.roleLabel || '')} · ${esc(firm)}</div>`;
    });
    const crumb = document.getElementById('ctx-firm');
    if (crumb) crumb.textContent = firm.replace(/\s+Advocates$/, '');
  }

  function initAccount() {
    // Old code paths read PERSONAS[personaIdx]; make that the signed-in person.
    if (typeof PERSONAS !== 'undefined') {
      PERSONAS.splice(0, PERSONAS.length, { id: 'me', name: ME.name, role: ME.roleLabel, av: ME.initials, cls: ROLE_CLASS[ME.role] || 'assoc' });
      personaIdx = 0;
    }
    window.cyclePersona = function () {};
    const bar = document.getElementById('persona-bar');
    if (bar) {
      bar.onclick = null;
      bar.style.cursor = 'default';
      const arrow = bar.lastElementChild;
      if (arrow && arrow.textContent.trim() === '↕') arrow.remove();
    }

    const shells = allowedShells();
    ['adv', 'cli', 'adm'].forEach(s => {
      const tab = document.getElementById('shell-' + s);
      if (tab) tab.style.display = shells.includes(s) ? '' : 'none';
    });
    const switcher = document.querySelector('.shell-bar');
    if (switcher && shells.length < 2) switcher.style.display = 'none';

    const right = document.querySelector('.tb-r');
    if (right && !document.getElementById('vlf-signout')) {
      right.insertAdjacentHTML('beforeend', `<form id="vlf-signout" method="POST" action="${esc(window.VLF_LOGOUT || '/logout')}" style="margin:0;">
        <input type="hidden" name="_token" value="${esc((document.querySelector('meta[name="csrf-token"]') || {}).content || '')}">
        <button type="submit" class="global-search-btn" title="Sign out">Sign out</button></form>`);
    }

    setShell(shells[0]);
  }

  wrap('setShell', function (original, args) {
    // Only the parts of the app this account may open.
    const shell = allowedShells().includes(args[0]) ? args[0] : allowedShells()[0];
    const result = original.call(this, shell);
    const body = document.getElementById('shell-' + shell + '-body');
    if (body) body.style.display = 'grid';
    ['adv', 'cli', 'adm'].forEach(s => { const tab = document.getElementById('shell-' + s); if (tab) tab.classList.toggle('on', s === shell); });
    showPg({ adv: 'adv-command', cli: 'cli-home', adm: 'adm-home' }[shell]);
    showAccount();
    onUserChanged();
    return result;
  });
  wrap('updatePersonaBar', function (original, args) {
    const result = original.apply(this, args);
    showAccount();
    return result;
  });

  // Documents tab: list saved drafts and uploads under the matter's folders.
  const setTab = NAV._setMatterTab.bind(NAV);
  NAV._setMatterTab = function (tab) {
    setTab(tab);
    if (tab === 'documents') setTimeout(renderWorkingDocs, 180);
  };

  document.addEventListener('vlf:state', e => {
    const st = e.detail || {};
    S.clients = st.clients || [];
    S.events = st.events || [];
    S.deadlines = st.deadlines || [];
    S.staff = st.staff || [];
    S.signupRequests = st.signupRequests || [];
    S.settings = Array.isArray(st.settings) ? {} : (st.settings || {});
    S.notifications = st.notifications || {};
    Object.keys(S.notifications).forEach(k => { if (Array.isArray(S.notifications[k]) === false) S.notifications[k] = []; });
    resetSeen();
    showAccount();
    renderAll();
    renderWorkingDocs();
    if (!pollTimer) pollTimer = setInterval(pollNotifications, 30000);
  });

  /* ══ SMALL SCREENS: the sidebar becomes a slide-in menu (see vlf-theme.css) ══ */

  const currentSidebar = () => document.querySelector('#shell-' + currentShell + '-body .sb');

  function setMenu(open) {
    document.querySelectorAll('.sb').forEach(sb => sb.classList.remove('vlf-open'));
    const sb = currentSidebar();
    if (open && sb) sb.classList.add('vlf-open');
    const overlay = document.getElementById('vlf-sb-overlay');
    if (overlay) overlay.classList.toggle('vlf-open', !!open);
    const btn = document.getElementById('vlf-menu-btn');
    if (btn) { btn.setAttribute('aria-expanded', open ? 'true' : 'false'); btn.innerHTML = ic(open ? 'close' : 'menu', 20); }
  }

  function initResponsive() {
    const left = document.querySelector('.tb-l');
    if (left && !document.getElementById('vlf-menu-btn')) {
      left.insertAdjacentHTML('afterbegin', '<button type="button" class="vlf-menu-btn" id="vlf-menu-btn" aria-label="Menu" aria-expanded="false">' + ic('menu', 20) + '</button>');
      document.getElementById('vlf-menu-btn').addEventListener('click', () => setMenu(!(currentSidebar() || {}).classList?.contains('vlf-open')));
    }
    if (!document.getElementById('vlf-sb-overlay')) {
      document.body.insertAdjacentHTML('beforeend', '<div class="vlf-sb-overlay" id="vlf-sb-overlay"></div>');
      document.getElementById('vlf-sb-overlay').addEventListener('click', () => setMenu(false));
    }
    // On phones the Advocate / Firm Admin switch moves into the menu.
    const shells = allowedShells();
    if (shells.length > 1) {
      const labels = { adv: 'Advocate', adm: 'Firm Admin', cli: 'Client' };
      document.querySelectorAll('.sb').forEach(sb => {
        if (sb.querySelector('.vlf-sb-shells')) return;
        sb.insertAdjacentHTML('afterbegin', `<div class="vlf-sb-shells">${shells.map(s => `<button type="button" data-shell="${s}" onclick="setShell('${s}')">${labels[s]}</button>`).join('')}</div>`);
      });
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setMenu(false); });
    window.addEventListener('resize', () => { if (window.innerWidth > 900) setMenu(false); });
  }

  function markShellButtons() {
    document.querySelectorAll('.vlf-sb-shells button').forEach(b => b.classList.toggle('on', b.dataset.shell === currentShell));
  }

  // Picking a page from the menu closes it.
  wrap('showPg', function (original, args) {
    const result = original.apply(this, args);
    setMenu(false);
    markShellButtons();
    return result;
  });

  applyIcons();
  initIntake();
  initResponsive();
  initAccount();

  window.VLFOPS = {
    openMatter, openMatterDrawer, assignMatter, saveAssignment, logTimeFor,
    runConflictCheck, newMatterForClient,
    openClientForm, saveClient,
    openEventForm, saveEvent, toggleChecklist, addChecklistItem, setEventLevel,
    openDeadlineForm, saveDeadline, toggleDeadline,
    setTaskStatus,
    addExtraInvoiceLine, createInvoice, invoiceAction,
    openDoc, docAction, openReturnFor, confirmReturn,
    openStaffForm, saveStaff, openSignup, approveSignup, declineSignup, openFirmForm, saveFirm, toggleSetting,
    openNotification, pollNotifications, goTo, sendClientMessage, inviteClient, inviteStaff
  };
})();
