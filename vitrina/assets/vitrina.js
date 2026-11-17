(function () {
  'use strict';

  var raw = JSON.parse(document.getElementById('vitrina-data').textContent || '{}');
  var TZ = raw.timezone || 'Asia/Chita';
  var PAGE = 24;
  var MONTHS = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
  var MONTHS_GEN = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
  var MONTHS_SHORT = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
  var WEEKDAYS = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
  var ICONS = raw.icons || {};
  var I_CLOCK = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
  var I_PIN = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';
  var I_CAL = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>';
  var I_TAG = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12l-8 8-9-9V3h8z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>';
  var I_ARROW = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';

  function $(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function norm(s) { return String(s || '').toLowerCase().replace(/ё/g, 'е'); }
  function plural(n, one, few, many) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return one;
    if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return few;
    return many;
  }
  function todayKey() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  }
  function keyToDate(k) { var p = k.split('-'); return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2])); }
  function dayDiff(a, b) { return Math.round((keyToDate(b) - keyToDate(a)) / 86400000); }
  function addDays(k, n) { var d = keyToDate(k); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); }

  var catMap = {};
  (raw.categories || []).forEach(function (c) { catMap[c.key] = c; });
  function catLabel(key) { return catMap[key] ? catMap[key].label : 'Событие'; }
  function catColor(key) { return catMap[key] && /^#[0-9a-f]{6}$/i.test(catMap[key].color) ? catMap[key].color : 'var(--c-default)'; }
  function catIcon(key) { return ICONS[catMap[key] ? catMap[key].icon : 'other'] || ICONS.other || ''; }
  function linkify(text) {
    return esc(text).replace(/https?:\/\/[^\s<>"']+[^\s<>"'.,;:!?)\]»]/g, function (u) {
      return '<a href="' + u + '" target="_blank" rel="noopener">' + u + '</a>';
    });
  }

  var now = Date.now();
  var events = (raw.events || []).map(function (e) {
    var startKey = e.start.slice(0, 10);
    var endKey = (e.end || e.start).slice(0, 10);
    e.startKey = startKey;
    e.endKey = endKey < startKey ? startKey : endKey;
    e.timeText = e.time || '';
    e.past = new Date(e.end || e.start).getTime() < now;
    e.year = startKey.slice(0, 4);
    e.hay = norm([e.title, e.place, e.excerpt, e.description, catLabel(e.cat)].join(' '));
    return e;
  });
  var byId = {};
  events.forEach(function (e) { byId[e.id] = e; });
  var upcoming = events.filter(function (e) { return !e.past; }).sort(function (a, b) { return a.start < b.start ? -1 : 1; });
  var past = events.filter(function (e) { return e.past; }).sort(function (a, b) { return a.start < b.start ? 1 : -1; });

  function dateText(e, withWeekday) {
    var s = keyToDate(e.startKey), en = keyToDate(e.endKey);
    var sd = s.getUTCDate(), sm = s.getUTCMonth(), sy = s.getUTCFullYear();
    if (e.endKey !== e.startKey) {
      var ed = en.getUTCDate(), em = en.getUTCMonth(), ey = en.getUTCFullYear();
      if (sy !== ey) return sd + ' ' + MONTHS_GEN[sm] + ' ' + sy + ' — ' + ed + ' ' + MONTHS_GEN[em] + ' ' + ey;
      if (sm !== em) return sd + ' ' + MONTHS_GEN[sm] + ' — ' + ed + ' ' + MONTHS_GEN[em] + ' ' + ey;
      return sd + '–' + ed + ' ' + MONTHS_GEN[sm] + ' ' + sy;
    }
    var t = sd + ' ' + MONTHS_GEN[sm] + ' ' + sy;
    return withWeekday ? t + ', ' + WEEKDAYS[s.getUTCDay()] : t;
  }
  function relText(e) {
    var t = todayKey();
    if (e.startKey <= t && e.endKey >= t) return 'Сегодня';
    var d = dayDiff(t, e.startKey);
    if (d === 1) return 'Завтра';
    if (d > 1) return 'Через ' + d + ' ' + plural(d, 'день', 'дня', 'дней');
    return 'Прошло';
  }

  function media(e, cls, large) {
    var src = large ? (e.imageLarge || e.image) : e.image;
    var ph = '<div class="ph" style="--c:' + catColor(e.cat) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + catIcon(e.cat) + '</svg></div>';
    if (!src) return ph;
    return ph + '<img class="' + (cls || '') + '" src="' + esc(src) + '" alt=""' + (large ? '' : ' loading="lazy"') + ' decoding="async" onerror="this.remove()">';
  }

  /* Состояние фильтров живёт в адресе страницы, чтобы подборкой можно было поделиться. */
  var params = new URLSearchParams(location.search);
  var state = {
    q: params.get('q') || '',
    cat: params.get('cat') || 'all',
    when: params.get('when') || 'all',
    year: params.get('year') || '',
    view: params.get('view') === 'calendar' ? 'calendar' : 'feed',
    month: params.get('month') || '',
    shown: PAGE
  };
  if (['all', 'upcoming', 'past'].indexOf(state.when) === -1) state.when = 'all';
  if (state.cat !== 'all' && !catMap[state.cat]) state.cat = 'all';

  function syncUrl() {
    var p = new URLSearchParams();
    if (state.q) p.set('q', state.q);
    if (state.cat !== 'all') p.set('cat', state.cat);
    if (state.when !== 'all') p.set('when', state.when);
    if (state.year) p.set('year', state.year);
    if (state.view === 'calendar') { p.set('view', 'calendar'); p.set('month', state.month); }
    var qs = p.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
  }

  function matches(e, ignorePeriod) {
    if (state.cat !== 'all' && e.cat !== state.cat) return false;
    if (!ignorePeriod) {
      if (state.when === 'upcoming' && e.past) return false;
      if (state.when === 'past' && !e.past) return false;
      if (state.year && e.year !== state.year) return false;
    }
    if (state.q) {
      var words = norm(state.q).split(/\s+/).filter(Boolean);
      for (var i = 0; i < words.length; i++) if (e.hay.indexOf(words[i]) === -1) return false;
    }
    return true;
  }

  /* Шапка: статистика и ближайшее событие */
  function renderHero() {
    var thisYear = todayKey().slice(0, 4);
    var inYear = events.filter(function (e) { return e.year === thisYear; }).length;
    var types = (raw.categories || []).filter(function (c) { return c.key !== 'other'; }).length;
    $('stats').innerHTML =
      '<div class="stat"><b>' + upcoming.length + '</b><span>' + plural(upcoming.length, 'предстоящее', 'предстоящих', 'предстоящих') + '</span></div>' +
      '<div class="stat"><b>' + inYear + '</b><span>' + plural(inYear, 'событие', 'события', 'событий') + ' в ' + thisYear + ' году</span></div>' +
      '<div class="stat"><b>' + events.length + '</b><span>в архиве с ' + (events.length ? events[0].year : thisYear) + ' года</span></div>' +
      '<div class="stat"><b>' + types + '</b><span>' + plural(types, 'формат', 'формата', 'форматов') + '</span></div>';

    var e = upcoming.filter(function (x) { return x.pinned; })[0] || upcoming[0] || past[0];
    if (!e) { $('feature').innerHTML = ''; return; }
    var d = keyToDate(e.startKey);
    var label = e.past ? 'Последнее событие' : e.pinned && e !== upcoming[0] ? 'Главное событие' : 'Ближайшее событие';
    $('feature').innerHTML =
      '<button type="button" class="feature" data-open="' + e.id + '" style="--c:' + catColor(e.cat) + '">' +
        (e.imageLarge || e.image ? '<img class="feature-img" src="' + esc(e.imageLarge || e.image) + '" alt="" onerror="this.remove()">' : '') +
        '<div class="feature-top"><span class="feature-label">' + label + '</span><span class="countdown">' + esc(relText(e)) + '</span></div>' +
        '<div class="feature-date"><b>' + d.getUTCDate() + '</b><span>' + MONTHS_GEN[d.getUTCMonth()] + ' ' + d.getUTCFullYear() + (e.timeText ? ' · ' + esc(e.timeText) : '') + '</span></div>' +
        '<h3>' + esc(e.title) + '</h3>' +
        '<div class="meta">' +
          '<div>' + I_TAG + '<span>' + esc(catLabel(e.cat)) + '</span></div>' +
          (e.place ? '<div>' + I_PIN + '<span>' + esc(e.place) + '</span></div>' : '') +
        '</div>' +
        '<span class="feature-cta">Подробнее ' + I_ARROW + '</span>' +
      '</button>';
  }

  function renderChips() {
    var counts = {};
    events.forEach(function (e) {
      if (state.when === 'upcoming' && e.past) return;
      if (state.when === 'past' && !e.past) return;
      if (state.year && e.year !== state.year) return;
      counts[e.cat] = (counts[e.cat] || 0) + 1;
    });
    var total = Object.keys(counts).reduce(function (s, k) { return s + counts[k]; }, 0);
    var html = '<button type="button" class="chip" data-cat="all" style="--c:var(--text)" aria-pressed="' + (state.cat === 'all') + '">Все <em>' + total + '</em></button>';
    (raw.categories || []).forEach(function (c) {
      html += '<button type="button" class="chip" data-cat="' + esc(c.key) + '" style="--c:' + catColor(c.key) + '" aria-pressed="' + (state.cat === c.key) + '"><i></i>' + esc(c.label) + ' <em>' + (counts[c.key] || 0) + '</em></button>';
    });
    $('chips').innerHTML = html;
  }

  function renderYears() {
    var years = {};
    events.forEach(function (e) { years[e.year] = 1; });
    var list = Object.keys(years).sort().reverse();
    $('year').innerHTML = '<option value="">Все годы</option>' + list.map(function (y) {
      return '<option value="' + y + '"' + (state.year === y ? ' selected' : '') + '>' + y + '</option>';
    }).join('');
  }

  function cardHtml(e) {
    var d = keyToDate(e.startKey);
    var rel = e.past ? 'Прошло' : relText(e);
    return '<button type="button" class="card' + (e.past ? ' is-past' : '') + '" data-open="' + e.id + '" style="--c:' + catColor(e.cat) + '">' +
      '<div class="card-media">' + media(e) +
        '<div class="date-badge"><b>' + d.getUTCDate() + '</b><span>' + MONTHS_SHORT[d.getUTCMonth()] + '</span></div>' +
        '<span class="state-badge' + (e.past ? '' : ' is-soon') + '">' + esc(rel) + '</span>' +
      '</div>' +
      '<div class="card-body">' +
        '<span class="pill">' + esc(catLabel(e.cat)) + '</span>' +
        '<h3>' + esc(e.title) + '</h3>' +
        '<div class="meta">' +
          '<div>' + I_CAL + '<span>' + esc(dateText(e, false)) + (e.timeText ? ', ' + esc(e.timeText) : '') + '</span></div>' +
          (e.place ? '<div>' + I_PIN + '<span>' + esc(e.place) + '</span></div>' : '') +
        '</div>' +
        (e.excerpt ? '<p>' + esc(e.excerpt) + '</p>' : '') +
      '</div>' +
    '</button>';
  }

  function renderFeed() {
    var up = upcoming.filter(function (e) { return matches(e); });
    var ps = past.filter(function (e) { return matches(e); });
    var list = state.when === 'past' ? ps : state.when === 'upcoming' ? up : up.concat(ps);
    var total = list.length;
    var visible = list.slice(0, state.shown);

    var html = '';
    var group = null, buf = [];
    function flush() {
      if (!buf.length) return;
      var cnt = group.count;
      html += '<div class="month-head"><h3>' + esc(group.title) + '</h3><span>' + cnt + ' ' + plural(cnt, 'событие', 'события', 'событий') + '</span></div>' +
        '<div class="grid">' + buf.join('') + '</div>';
      buf = [];
    }
    var groupCounts = {};
    list.forEach(function (e) {
      var k = e.past ? e.year : 'upcoming';
      groupCounts[k] = (groupCounts[k] || 0) + 1;
    });
    visible.forEach(function (e) {
      var k = e.past ? e.year : 'upcoming';
      if (!group || group.key !== k) {
        flush();
        var title = k === 'upcoming' ? 'Предстоящие' : (k === todayKey().slice(0, 4) ? 'Прошедшие в ' + k + ' году' : k + ' год');
        group = { key: k, title: title, count: groupCounts[k] };
      }
      buf.push(cardHtml(e));
    });
    flush();

    $('feed').innerHTML = html;
    $('more').hidden = total <= state.shown;
    $('more').textContent = 'Показать ещё ' + Math.min(PAGE, total - state.shown) + ' из ' + (total - state.shown);
    $('empty').hidden = total > 0;
    var info = total + ' ' + plural(total, 'событие', 'события', 'событий');
    if (state.when === 'all' && !state.q && state.cat === 'all' && !state.year) {
      info += upcoming.length ? ', из них предстоящих: ' + up.length : '';
    }
    $('resultInfo').textContent = info;
  }

  /* Календарь */
  function defaultMonth() {
    var k = upcoming.length ? upcoming[0].startKey : todayKey();
    return k.slice(0, 7);
  }
  function renderCalendar() {
    if (!/^\d{4}-\d{2}$/.test(state.month)) state.month = defaultMonth();
    var y = +state.month.slice(0, 4), m = +state.month.slice(5, 7) - 1;
    $('calTitle').textContent = MONTHS[m] + ' ' + y;
    var first = new Date(Date.UTC(y, m, 1));
    var offset = (first.getUTCDay() + 6) % 7;
    var startKey = addDays(first.toISOString().slice(0, 10), -offset);
    var daysInMonth = new Date(Date.UTC(y, m + 1, 0)).getUTCDate();
    var cells = Math.ceil((offset + daysInMonth) / 7) * 7;
    var endKey = addDays(startKey, cells - 1);
    var t = todayKey();

    var byDay = {};
    var inMonth = 0;
    events.forEach(function (e) {
      if (!matches(e, true) || e.endKey < startKey || e.startKey > endKey) return;
      if (e.startKey.slice(0, 7) === state.month || e.endKey.slice(0, 7) === state.month) inMonth++;
      var k = e.startKey < startKey ? startKey : e.startKey;
      var guard = 0;
      while (k <= e.endKey && k <= endKey && guard++ < 62) {
        (byDay[k] = byDay[k] || []).push(e);
        k = addDays(k, 1);
      }
    });

    var html = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'].map(function (d) { return '<div class="cal-dow">' + d + '</div>'; }).join('');
    for (var i = 0; i < cells; i++) {
      var k = addDays(startKey, i);
      var dd = keyToDate(k);
      var cls = 'cal-day' + (k.slice(0, 7) !== state.month ? ' is-out' : '') + (k === t ? ' is-today' : '') + (i % 7 >= 5 ? ' is-weekend' : '');
      var list = (byDay[k] || []).sort(function (a, b) { return a.start < b.start ? -1 : 1; });
      var evHtml = list.slice(0, 3).map(function (e) {
        return '<button type="button" class="cal-ev" data-open="' + e.id + '" style="--c:' + catColor(e.cat) + '" title="' + esc(e.title) + '">' +
          (e.timeText && e.startKey === k ? '<time>' + esc(e.timeText.slice(0, 5)) + '</time>' : '') + esc(e.title) + '</button>';
      }).join('');
      if (list.length > 3) {
        evHtml += '<button type="button" class="cal-more" data-day="' + k + '">ещё ' + (list.length - 3) + '</button>';
      }
      html += '<div class="' + cls + '"><span class="cal-num">' + dd.getUTCDate() + '</span>' + evHtml + '</div>';
    }
    $('cal').innerHTML = html;
    $('empty').hidden = true;
    $('resultInfo').textContent = inMonth
      ? inMonth + ' ' + plural(inMonth, 'событие', 'события', 'событий') + ' в этом месяце'
      : 'В этом месяце событий нет';
  }

  function render() {
    renderChips();
    document.querySelectorAll('[data-when]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-when') === state.when)); });
    document.querySelectorAll('[data-view]').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-view') === state.view)); });
    var isCal = state.view === 'calendar';
    $('feedView').hidden = isCal;
    $('calendarView').hidden = !isCal;
    document.querySelector('.seg-when').hidden = isCal;
    $('year').hidden = isCal;
    if (isCal) renderCalendar(); else renderFeed();
    syncUrl();
  }

  /* Карточка события */
  var modal = $('modal');
  var lastFocus = null;
  function openEvent(id, push) {
    var e = byId[id];
    if (!e) return;
    lastFocus = document.activeElement;
    modal.style.setProperty('--c', catColor(e.cat));
    $('mMedia').innerHTML = media(e, '', true);
    $('mCat').textContent = catLabel(e.cat);
    $('mTitle').textContent = e.title;
    var rel = relText(e);
    $('mMeta').innerHTML =
      '<div>' + I_CAL + '<span><small>Дата</small>' + esc(dateText(e, true)) + '</span></div>' +
      (e.timeText ? '<div>' + I_CLOCK + '<span><small>Время (Чита)</small>' + esc(e.timeText) + '</span></div>' : '') +
      (e.place ? '<div>' + I_PIN + '<span><small>Место</small>' + esc(e.place) + '</span></div>' : '') +
      '<div>' + I_TAG + '<span><small>Статус</small>' + esc(e.past ? 'Событие прошло' : rel) + '</span></div>';
    var text = (e.description || e.excerpt || '').split(/\n\s*\n/).filter(function (s) { return s.trim(); });
    $('mText').innerHTML = text.length
      ? text.map(function (p) { return '<p>' + p.trim().split('\n').map(linkify).join('<br>') + '</p>'; }).join('')
      : '<p>Подробности появятся позже.</p>';
    $('mOpen').hidden = !e.link;
    if (e.link) { $('mOpen').href = e.link; $('mOpen').textContent = e.linkLabel || 'Подробнее'; }
    $('mIcs').href = 'ics.php?id=' + e.id;
    $('mIcs').hidden = e.past;
    if (push !== false) history.replaceState(null, '', location.pathname + location.search + '#e-' + e.id);
    if (!modal.open) modal.showModal();
    modal.scrollTop = 0;
  }
  function closeModal() {
    if (modal.open) modal.close();
  }
  modal.addEventListener('close', function () {
    if (/^#e-\d+$/.test(location.hash)) history.replaceState(null, '', location.pathname + location.search);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  });
  modal.addEventListener('click', function (ev) { if (ev.target === modal) closeModal(); });
  $('mClose').addEventListener('click', closeModal);
  $('mShare').addEventListener('click', function () {
    var url = location.href;
    var done = function () { toast('Ссылка скопирована'); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(done, function () { prompt('Ссылка на событие', url); });
    else prompt('Ссылка на событие', url);
  });

  function toast(text) {
    var t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.textContent = text;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 1800);
  }

  /* События интерфейса */
  document.addEventListener('click', function (ev) {
    var open = ev.target.closest('[data-open]');
    if (open) { openEvent(+open.getAttribute('data-open')); return; }
    var chip = ev.target.closest('[data-cat]');
    if (chip) { state.cat = chip.getAttribute('data-cat'); state.shown = PAGE; render(); return; }
    var when = ev.target.closest('[data-when]');
    if (when) { state.when = when.getAttribute('data-when'); state.shown = PAGE; render(); return; }
    var view = ev.target.closest('[data-view]');
    if (view) { state.view = view.getAttribute('data-view'); render(); return; }
    var vl = ev.target.closest('[data-view-link]');
    if (vl) { state.view = vl.getAttribute('data-view-link'); render(); return; }
    var more = ev.target.closest('.cal-more');
    if (more) {
      var day = more.getAttribute('data-day');
      state.view = 'feed'; state.when = 'all'; state.year = day.slice(0, 4); state.shown = 1000;
      render();
      var target = document.querySelector('.card[data-open]');
      var dayEvent = events.filter(function (e) { return e.startKey <= day && e.endKey >= day && matches(e, true); })[0];
      if (dayEvent) target = document.querySelector('.card[data-open="' + dayEvent.id + '"]') || target;
      if (target) target.scrollIntoView({ block: 'center' });
    }
  });
  $('more').addEventListener('click', function () { state.shown += PAGE; renderFeed(); });
  var qTimer = null;
  $('q').value = state.q;
  $('q').addEventListener('input', function () {
    clearTimeout(qTimer);
    qTimer = setTimeout(function () { state.q = $('q').value.trim(); state.shown = PAGE; render(); }, 150);
  });
  $('year').addEventListener('change', function () { state.year = $('year').value; state.shown = PAGE; render(); });
  function shiftMonth(n) {
    var y = +state.month.slice(0, 4), m = +state.month.slice(5, 7) - 1 + n;
    var d = new Date(Date.UTC(y, m, 1));
    state.month = d.toISOString().slice(0, 7);
    render();
  }
  $('calPrev').addEventListener('click', function () { shiftMonth(-1); });
  $('calNext').addEventListener('click', function () { shiftMonth(1); });
  $('calToday').addEventListener('click', function () { state.month = todayKey().slice(0, 7); render(); });
  document.addEventListener('keydown', function (ev) {
    var tag = (ev.target.tagName || '').toLowerCase();
    if (ev.key === '/' && tag !== 'input' && tag !== 'textarea' && !modal.open) { ev.preventDefault(); $('q').focus(); }
    if (ev.key === 'Escape' && document.activeElement === $('q') && $('q').value) { $('q').value = ''; state.q = ''; render(); }
  });
  window.addEventListener('hashchange', function () {
    var m = /^#e-(\d+)$/.exec(location.hash);
    if (m) openEvent(+m[1], false);
  });

  renderHero();
  renderYears();
  render();
  var m0 = /^#e-(\d+)$/.exec(location.hash);
  if (m0) openEvent(+m0[1], false);
})();
