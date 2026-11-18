/**
 * Кастомные выпадающие списки в стиле .bx-create-menu (скрытый native select для совместимости с кодом).
 */
(function () {
  var OPEN_WRAP = null;

  var CHEVRON_SVG =
    '<svg class="bx-dropdown-trigger-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

  function closeAll(exceptWrap) {
    document.querySelectorAll('.bx-dropdown-wrap.is-open').forEach(function (w) {
      if (exceptWrap && w === exceptWrap) return;
      closePanel(w);
    });
    if (typeof window.__plannerCloseCreateMenu === 'function') {
      try {
        window.__plannerCloseCreateMenu();
      } catch (e) {}
    }
  }

  function closePanel(wrap) {
    if (!wrap) return;
    var panel = wrap.querySelector('.bx-dropdown-panel');
    var trigger = wrap.querySelector('.bx-dropdown-trigger');
    if (panel) panel.hidden = true;
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    wrap.classList.remove('is-open');
    if (OPEN_WRAP === wrap) OPEN_WRAP = null;
  }

  function openPanel(wrap) {
    closeAll(wrap);
    var panel = wrap.querySelector('.bx-dropdown-panel');
    var trigger = wrap.querySelector('.bx-dropdown-trigger');
    var select = wrap.querySelector('select.bx-dropdown-native');
    if (!panel || !trigger || !select) return;
    rebuildPanel(select, panel, trigger, wrap);
    panel.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    wrap.classList.add('is-open');
    OPEN_WRAP = wrap;
  }

  function getSelectedLabel(select) {
    var opt = select.options[select.selectedIndex];
    return opt ? (opt.textContent || '').trim() : '';
  }

  function syncTrigger(trigger, select) {
    var span = trigger.querySelector('.bx-dropdown-trigger-text');
    if (span) span.textContent = getSelectedLabel(select);
    trigger.disabled = !!select.disabled;
  }

  function rebuildPanel(select, panel, trigger, wrap) {
    panel.innerHTML = '';
    var cur = String(select.value);
    for (var i = 0; i < select.options.length; i++) {
      var opt = select.options[i];
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'bx-create-menu-item bx-dropdown-item';
      btn.setAttribute('role', 'menuitem');
      btn.textContent = opt.textContent || '';
      btn.dataset.value = opt.value;
      if (opt.disabled) btn.disabled = true;
      if (String(opt.value) === cur) btn.classList.add('is-selected');
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        if (this.disabled) return;
        var v = this.dataset.value;
        var prev = select.value;
        select.value = v;
        if (String(prev) !== String(v)) {
          select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        syncTrigger(trigger, select);
        closePanel(wrap);
      });
      panel.appendChild(btn);
    }
  }

  function shouldEnhance(select) {
    if (!select || select.tagName !== 'SELECT' || select.multiple) return false;
    if (select.getAttribute('data-bx-dropdown-skip') === '1') return false;
    if (select.size > 1) return false;
    return true;
  }

  function enhance(select) {
    if (!shouldEnhance(select)) return;
    if (select.closest('.bx-dropdown-wrap')) return;

    var wrap = document.createElement('div');
    wrap.className = 'bx-dropdown-wrap';
    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'bx-dropdown-trigger';
    trigger.setAttribute('aria-haspopup', 'true');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = '<span class="bx-dropdown-trigger-text"></span>' + CHEVRON_SVG;

    var panel = document.createElement('div');
    panel.className = 'bx-dropdown-panel bx-create-menu';
    panel.hidden = true;
    panel.setAttribute('role', 'menu');

    select.classList.add('bx-dropdown-native');

    var parent = select.parentNode;
    parent.insertBefore(wrap, select);
    wrap.appendChild(trigger);
    wrap.appendChild(select);
    wrap.appendChild(panel);

    function syncWrapFromSelect() {
      wrap.hidden = !!select.hidden;
    }
    syncWrapFromSelect();
    var moAttr = new MutationObserver(syncWrapFromSelect);
    moAttr.observe(select, { attributes: true, attributeFilter: ['hidden'] });

    syncTrigger(trigger, select);
    rebuildPanel(select, panel, trigger, wrap);

    select.addEventListener('change', function () {
      syncTrigger(trigger, select);
      if (!panel.hidden) rebuildPanel(select, panel, trigger, wrap);
    });

    var mo = new MutationObserver(function () {
      syncTrigger(trigger, select);
      rebuildPanel(select, panel, trigger, wrap);
    });
    mo.observe(select, { childList: true, subtree: true });

    wrap._bxDropdownMoAttr = moAttr;

    trigger.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      if (select.disabled) return;
      if (panel.hidden) openPanel(wrap);
      else closePanel(wrap);
    });

    wrap._bxDropdownMo = mo;
  }

  function initIn(root) {
    root = root || document;
    root.querySelectorAll('select').forEach(function (sel) {
      if (shouldEnhance(sel)) enhance(sel);
    });
  }

  function syncOne(select) {
    if (!select) return;
    var wrap = select.closest('.bx-dropdown-wrap');
    if (!wrap) return;
    var trigger = wrap.querySelector('.bx-dropdown-trigger');
    var panel = wrap.querySelector('.bx-dropdown-panel');
    if (!trigger || !panel) return;
    syncTrigger(trigger, select);
    if (!panel.hidden) rebuildPanel(select, panel, trigger, wrap);
  }

  function syncAll() {
    document.querySelectorAll('.bx-dropdown-wrap select.bx-dropdown-native').forEach(syncOne);
  }

  document.addEventListener('click', function (ev) {
    if (!ev.target) return;
    if (ev.target.closest && ev.target.closest('.bx-dropdown-wrap')) return;
    closeAll(null);
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') closeAll(null);
  });

  window.bxCustomSelectInit = initIn;
  window.bxCustomSelectSync = syncOne;
  window.bxCustomSelectSyncAll = syncAll;
  window.bxCustomSelectCloseAll = function () {
    closeAll(null);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initIn(document);
    });
  } else {
    initIn(document);
  }
})();
