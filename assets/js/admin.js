/* APlusPlugins settings controls. No external JS dependency. */
(() => {
  'use strict';
  function initialize() {
    const root = document.querySelector('.apls-faext-settings');
    if (!root || root.dataset.aplsInitialized) return;
    root.dataset.aplsInitialized = '1';
    const form = root.querySelector('#apls-faext-settings-form');
    const notice = root.querySelector('#apls-faext-save-notice');
    const saveButton = form?.querySelector('input[type="submit"], button[type="submit"]');
    let noticeTimer = null;
    let saving = false;
    function showSaveNotice(message, isError) {
      if (!notice) return;
      if (noticeTimer) clearTimeout(noticeTimer);
      notice.replaceChildren(document.createTextNode(message));
      notice.classList.toggle('is-error', Boolean(isError));
      notice.hidden = false;
      if (!isError) {
        noticeTimer = setTimeout(() => { notice.hidden = true; }, 5000);
      }
    }
    if (form && window.aplsFaextAdmin?.endpoint && window.aplsFaextAdmin?.nonce) {
      form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        saving = true;
        if (saveButton) { saveButton.disabled = true; saveButton.dataset.originalText = saveButton.value || saveButton.textContent; if ('value' in saveButton) saveButton.value = window.aplsFaextAdmin.saving; else saveButton.textContent = window.aplsFaextAdmin.saving; }
        const data = new FormData(form);
        const options = {};
        for (const [name, value] of data.entries()) {
          const match = /^apls_faext_options\[([^\]]+)\](\[\])?$/.exec(name);
          if (!match) continue;
          if (match[2]) { if (!Array.isArray(options[match[1]])) options[match[1]] = []; options[match[1]].push(value); }
          else options[match[1]] = value;
        }
        try {
          const response = await fetch(window.aplsFaextAdmin.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aplsFaextAdmin.nonce },
            body: JSON.stringify({ options })
          });
          const result = await response.json();
          if (!response.ok || !result.success) throw new Error(result.message || window.aplsFaextAdmin.saveFailed);
          showSaveNotice(`${result.saved_at} ${result.message}`, false);
        } catch (error) {
          showSaveNotice(error.message || window.aplsFaextAdmin.saveFailed, true);
        } finally {
          saving = false;
          if (saveButton) { if ('value' in saveButton) saveButton.value = saveButton.dataset.originalText; else saveButton.textContent = saveButton.dataset.originalText; saveButton.disabled = false; }
        }
      });
    }
    const logView = root.querySelector('#apls-faext-log-view');
    const logStatus = root.querySelector('#apls-faext-log-status');
    async function loadLog(method = 'GET') {
      if (!logView || !window.aplsFaextAdmin?.diagnosticsEndpoint) return;
      try {
        const response = await fetch(window.aplsFaextAdmin.diagnosticsEndpoint, {
          method, credentials: 'same-origin', headers: { 'X-WP-Nonce': window.aplsFaextAdmin.nonce }
        });
        const body = await response.json();
        if (!response.ok || body.success === false) throw new Error(body.message || 'Log operation failed');
        logView.value = body.log || '';
        if (logStatus) logStatus.textContent = `${body.status?.writable ? 'Writable' : 'Not writable'} | ${body.status?.bytes || 0} bytes | newest events shown`;
        logView.scrollTop = logView.scrollHeight;
      } catch (error) { if (logStatus) logStatus.textContent = error.message; }
    }
    root.querySelector('#apls-faext-log-refresh')?.addEventListener('click', () => loadLog());
    root.querySelector('#apls-faext-log-clear')?.addEventListener('click', () => { if (window.confirm('Clear registration diagnostics log?')) loadLog('DELETE'); });
    root.querySelector('#apls-faext-log-copy')?.addEventListener('click', () => {
      if (logView) { logView.focus(); logView.select(); if (navigator.clipboard?.writeText) navigator.clipboard.writeText(logView.value).catch(() => {}); }
    });
    if (logView) loadLog();
    const tabs = Array.from(root.querySelectorAll('[data-apls-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-apls-panel]'));
    function activate(id, focus = false) {
      tabs.forEach(button => {
        const current = button.dataset.aplsTab === id;
        button.classList.toggle('is-active', current);
        button.setAttribute('aria-selected', String(current));
        button.tabIndex = current ? 0 : -1;
        if (focus && current) button.focus();
      });
      panels.forEach(panel => { panel.hidden = panel.dataset.aplsPanel !== id; });
    }
    if (tabs.length && panels.length) {
      tabs.forEach((button, index) => {
        button.setAttribute('role', 'tab');
        button.addEventListener('click', () => activate(button.dataset.aplsTab));
        button.addEventListener('keydown', event => {
          if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
          event.preventDefault();
          let next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
          activate(tabs[next].dataset.aplsTab, true);
        });
      });
      root.querySelector('.apls-faext-tabs')?.setAttribute('role', 'tablist');
      activate(tabs[0].dataset.aplsTab);
    }
    function showCrmMode() {
      const chosen = root.querySelector('input[name="apls_faext_options[crm_provider]"]:checked')?.value || 'none';
      root.querySelectorAll('[data-apls-mode]').forEach(panel => { panel.hidden = panel.dataset.aplsMode !== chosen; });
    }
    root.querySelectorAll('input[name="apls_faext_options[crm_provider]"]').forEach(input => input.addEventListener('change', showCrmMode));
    showCrmMode();
    root.querySelectorAll('.apls-faext-tag-select').forEach(select => {
      if (select.disabled || select.dataset.enhanced) return;
      const choices = Array.from(select.options).filter(o => !o.disabled);
      if (!choices.length) return;
      const picker = document.createElement('div');
      picker.className = 'apls-faext-picker';
      const pills = document.createElement('div'); pills.className = 'apls-faext-pills';
      const input = document.createElement('input'); input.type = 'search';
      input.className = 'apls-faext-search';
      input.placeholder = window.aplsFaextAdmin?.search || 'Search tags...';
      input.setAttribute('aria-label', input.placeholder);
      input.autocomplete = 'off';
      const results = document.createElement('div'); results.className = 'apls-faext-results'; results.hidden = true;
      picker.append(pills, input, results);
      select.after(picker);
      select.classList.add('apls-faext-native-select');
      select.dataset.enhanced = '1';
      function refresh() {
        pills.replaceChildren();
        choices.filter(o => o.selected).forEach(option => {
          const pill = document.createElement('span'); pill.className = 'apls-faext-pill';
          pill.append(document.createTextNode(option.textContent));
          const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = '×';
          remove.className = 'apls-faext-remove';
          remove.setAttribute('aria-label', `${window.aplsFaextAdmin?.remove || 'Remove tag'}: ${option.textContent}`);
          remove.addEventListener('click', () => { option.selected = false; refresh(); input.focus(); });
          pill.append(remove); pills.append(pill);
        });
        const term = input.value.trim().toLocaleLowerCase();
        const matches = choices.filter(o => !o.selected && o.textContent.toLocaleLowerCase().includes(term));
        results.replaceChildren();
        matches.slice(0, 100).forEach(option => {
          const choice = document.createElement('button'); choice.type = 'button';
          choice.className = 'apls-faext-result'; choice.textContent = option.textContent;
          choice.addEventListener('click', () => { option.selected = true; input.value = ''; refresh(); input.focus(); });
          results.append(choice);
        });
        if (!matches.length) {
          const empty = document.createElement('span'); empty.className = 'apls-faext-empty';
          empty.textContent = window.aplsFaextAdmin?.none || 'No matching tags'; results.append(empty);
        }
        results.hidden = document.activeElement !== input;
      }
      input.addEventListener('focus', refresh);
      input.addEventListener('input', refresh);
      input.addEventListener('keydown', event => {
        if (event.key === 'Escape') { results.hidden = true; input.blur(); }
        if (event.key === 'Enter' && !results.hidden) {
          const first = results.querySelector('button');
          if (first) { event.preventDefault(); first.click(); }
        }
      });
      document.addEventListener('pointerdown', event => { if (!picker.contains(event.target)) results.hidden = true; });
      refresh();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
  else initialize();
})();
