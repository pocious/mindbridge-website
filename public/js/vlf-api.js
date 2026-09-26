/*
 * Connects public/vlf-fixed.html to the Laravel backend (routes/api.php → VlfController).
 *
 * The prototype keeps its data in in-page objects (TASKS, TIME_ENTRIES, INVOICES,
 * COMM_DATA, MESSAGES, COMMENTS, DOCUMENTS). This script:
 *   1. replaces that data with the saved copy from GET api/vlf/state on load, and
 *   2. wraps each function that changes the data so the change is also sent to the API.
 * Load it after the prototype's own <script>, so those names already exist.
 */
(function () {
  const API = 'api/vlf/';
  const EQUITY = 'KSC-2026-0891';
  let MATTERS = {};

  function api(method, path, body) {
    return fetch(API + path, {
      method,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body)
    }).then(res => {
      if (!res.ok) throw new Error(method + ' ' + path + ' → ' + res.status);
      return res.json();
    });
  }

  function saveFailed(err) {
    console.error('[vlf-api]', err);
    t('Not saved to server', 'The change is only on this screen — check your connection and try again', 'r');
  }

  function persona() {
    return (typeof PERSONAS !== 'undefined' && PERSONAS[personaIdx]) || { name: 'Peter Ssali', av: 'PS' };
  }

  function replaceArray(target, items) {
    target.splice(0, target.length, ...items);
  }

  function replaceObject(target, source) {
    Object.keys(target).forEach(k => delete target[k]);
    Object.assign(target, source);
  }

  /* ── 1. Load saved state ── */

  function applyMatter(m) {
    if (!m || m.ref !== EQUITY) return;
    const title = document.querySelector('#pg-matter-' + EQUITY + ' .mh-title');
    if (title && m.title) title.textContent = m.title;
    if (m.riskLevel) {
      const num = document.querySelector('#matter-risk-bar .mrs-num');
      if (num) { num.textContent = m.riskLevel; num.className = 'mrs-num ' + m.riskLevel.toLowerCase(); }
      const explain = document.querySelector('#matter-risk-bar .mrs-explain');
      if (explain && m.riskNote) explain.textContent = m.riskNote;
    }
  }

  function rerenderVisible() {
    const billing = document.getElementById('mw-billing');
    if (billing) {
      delete billing.dataset.v3;
      if (billing.classList.contains('on')) renderBillingV3();
    }
    const queue = document.getElementById('pg-adv-queue');
    if (queue && queue.dataset.v3) { delete queue.dataset.v3; renderWorkQueueV3(); }
    const thread = document.getElementById('comment-thread-' + EQUITY);
    if (thread) thread.outerHTML = renderCommentThread(EQUITY, null);
    const comms = document.getElementById('pg-adv-comms');
    if (comms && comms.classList.contains('on')) renderCommMessages(activeChannel);
  }

  function loadState() {
    return api('GET', 'state').then(state => {
      MATTERS = state.matters || {};
      Object.values(MATTERS).forEach(applyMatter);

      replaceObject(TASKS, Object.fromEntries((state.tasks || []).map(task => [task.id, task])));
      replaceArray(TIME_ENTRIES, state.timeEntries || []);
      replaceArray(INVOICES, state.invoices || []);

      Object.entries(state.messages || {}).forEach(([channel, list]) => {
        if (channel.startsWith('thread:')) {
          MESSAGES[channel.slice(7)] = list;
        } else if (COMM_DATA[channel]) {
          COMM_DATA[channel].messages = list;
        } else {
          COMM_DATA[channel] = { title: channel, sub: '', to: channel, context: '', messages: list };
        }
      });

      Object.entries(state.comments || {}).forEach(([matter, list]) => { COMMENTS[matter] = list; });

      Object.entries(state.documents || {}).forEach(([key, data]) => {
        if (DOCUMENTS[key]) replaceObject(DOCUMENTS[key], data);
        else DOCUMENTS[key] = data;
      });

      rerenderVisible();
    }).catch(err => {
      console.error('[vlf-api]', err);
      t('Offline mode', 'Could not reach the server — showing sample data, changes will not be saved', 'y');
    });
  }

  /* ── 2. Save changes ── */

  function wrap(name, fn) {
    const original = window[name];
    if (typeof original !== 'function') return;
    window[name] = function (...args) { return fn.call(this, original, args); };
  }

  // Tasks
  wrap('submitCreateTask', function (original, args) {
    const before = new Set(Object.keys(TASKS));
    original.apply(this, args);
    const key = Object.keys(TASKS).find(k => !before.has(k));
    if (!key) return;
    const task = TASKS[key];
    api('POST', 'tasks', task).then(saved => {
      delete TASKS[key];
      TASKS[saved.id] = Object.assign(task, saved);
    }).catch(saveFailed);
  });

  // Time entries — the server works out the code and amount
  wrap('saveTimeEntry', function (original, args) {
    const before = TIME_ENTRIES.length;
    original.apply(this, args);
    if (TIME_ENTRIES.length === before) return;
    const entry = TIME_ENTRIES[0];
    api('POST', 'time-entries', entry).then(saved => Object.assign(entry, saved)).catch(saveFailed);
  });

  // Messages — Messages page, client channel, and the pop-up direct thread
  function saveMessage(channel, list, before) {
    if (!list || list.length === before) return;
    const msg = list[list.length - 1];
    api('POST', 'messages', { channel, from: msg.from, name: msg.name || persona().name, text: msg.text })
      .then(saved => Object.assign(msg, saved)).catch(saveFailed);
  }

  wrap('postCommMessage', function (original, args) {
    const channel = activeChannel;
    const before = COMM_DATA[channel] ? COMM_DATA[channel].messages.length : 0;
    original.apply(this, args);
    saveMessage(channel, COMM_DATA[channel] && COMM_DATA[channel].messages, before);
  });

  wrap('sendClientMessage', function (original, args) {
    const before = COMM_DATA['equity-client'] ? COMM_DATA['equity-client'].messages.length : 0;
    original.apply(this, args);
    saveMessage('equity-client', COMM_DATA['equity-client'] && COMM_DATA['equity-client'].messages, before);
  });

  wrap('sendMessage', function (original, args) {
    const threadId = args[0];
    const before = MESSAGES[threadId] ? MESSAGES[threadId].length : 0;
    original.apply(this, args);
    saveMessage('thread:' + threadId, MESSAGES[threadId], before);
  });

  // Internal discussion comments
  wrap('submitComment', function (original, args) {
    const matter = args[0];
    const before = COMMENTS[matter] ? COMMENTS[matter].length : 0;
    original.apply(this, args);
    const list = COMMENTS[matter];
    if (!list || list.length === before) return;
    const comment = list[list.length - 1];
    api('POST', 'comments', { matter, author: comment.author, av: comment.av, context: comment.context, text: comment.text })
      .then(saved => Object.assign(comment, saved)).catch(saveFailed);
  });

  // Invoice line edits
  wrap('saveInvoiceEdits', function (original, args) {
    original.apply(this, args);
    const inv = INVOICES.find(i => i.id === args[0]);
    if (!inv) return;
    api('PUT', 'invoices/' + encodeURIComponent(inv.id), { lines: inv.lines })
      .then(saved => Object.assign(inv, saved)).catch(saveFailed);
  });

  // Matter details — read the form before the original closes it
  wrap('saveMatterEdit', function (original, args) {
    const ref = args[0];
    const val = id => { const el = document.getElementById(id); return el ? el.value.trim() : undefined; };
    const changes = {
      title: val('me-title'),
      court: val('me-court'),
      judge: val('me-judge'),
      advocate: (val('me-advocate') || '').split(' — ')[0] || undefined,
      stage: val('me-stage')
    };
    original.apply(this, args);
    api('PUT', 'matters/' + encodeURIComponent(ref), changes)
      .then(saved => { MATTERS[ref] = saved; }).catch(saveFailed);
  });

  // Pre-fill the edit form with the saved values instead of the hard-coded ones
  wrap('openMatterEdit', function (original, args) {
    original.apply(this, args);
    const m = MATTERS[args[0]];
    if (!m) return;
    const set = (id, v) => { const el = document.getElementById(id); if (el && v) el.value = v; };
    set('me-title', m.title);
    set('me-court', m.court);
    set('me-judge', m.judge);
    set('me-stage', m.stage);
  });

  // Partner approval — save the approved document and the lowered risk level
  wrap('partnerApprove', function (original, args) {
    original.apply(this, args);
    const doc = DOCUMENTS['witness-statement'];
    if (doc) api('PUT', 'documents/witness-statement', { data: doc }).catch(saveFailed);
    const explain = document.querySelector('#matter-risk-bar .mrs-explain');
    api('PUT', 'matters/' + EQUITY, { riskLevel: 'HIGH', riskNote: explain ? explain.textContent : null })
      .then(saved => { MATTERS[EQUITY] = saved; }).catch(saveFailed);
  });

  // Document drafts from the editor
  wrap('saveDocDraft', function (original, args) {
    const before = new Set(Object.keys(DOCUMENTS));
    original.apply(this, args);
    Object.keys(DOCUMENTS).filter(k => !before.has(k)).forEach(key => {
      api('PUT', 'documents/' + encodeURIComponent(key), { data: DOCUMENTS[key] }).catch(saveFailed);
    });
  });

  // The original inserts a new queue header on every re-render; drop the old one first
  wrap('renderWorkQueueV3', function (original, args) {
    const page = document.getElementById('pg-adv-queue');
    const willRender = page && !page.dataset.v3;
    if (willRender) page.querySelectorAll('.vlf-wq-header').forEach(el => el.remove());
    original.apply(this, args);
    const body = page && page.querySelector('.pgbody');
    if (willRender && body && body.firstElementChild) body.firstElementChild.classList.add('vlf-wq-header');
  });

  loadState();
})();
