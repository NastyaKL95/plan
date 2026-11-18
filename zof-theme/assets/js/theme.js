(function () {
  'use strict';

  var header = document.getElementById('siteHeader');
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('siteNav');

  function setMenu(open) {
    if (!toggle || !header) return;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    header.classList.toggle('is-menu-open', open);
    document.documentElement.classList.toggle('has-menu-open', open);
  }

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      setMenu(toggle.getAttribute('aria-expanded') !== 'true');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setMenu(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setMenu(false);
        toggle.focus();
      }
    });
    window.matchMedia('(min-width: 961px)').addEventListener('change', function (mq) {
      if (mq.matches) setMenu(false);
    });
  }

  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  function plural(n, forms) {
    var n10 = n % 10, n100 = n % 100;
    if (n10 === 1 && n100 !== 11) return forms[0];
    if (n10 >= 2 && n10 <= 4 && (n100 < 12 || n100 > 14)) return forms[1];
    return forms[2];
  }

  document.querySelectorAll('.countdown[data-deadline]').forEach(function (box) {
    var deadline = Date.parse(box.getAttribute('data-deadline'));
    if (isNaN(deadline)) return;
    var cells = {};
    box.querySelectorAll('[data-unit]').forEach(function (el) {
      cells[el.getAttribute('data-unit')] = {
        num: el,
        unit: el.parentNode.querySelector('.countdown-unit')
      };
    });

    function render() {
      var left = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
      var values = {
        days: Math.floor(left / 86400),
        hours: Math.floor((left % 86400) / 3600),
        minutes: Math.floor((left % 3600) / 60)
      };
      Object.keys(values).forEach(function (key) {
        var cell = cells[key];
        if (!cell) return;
        cell.num.textContent = String(values[key]).padStart(2, '0');
        if (cell.unit) cell.unit.textContent = plural(values[key], cell.unit.getAttribute('data-forms').split(','));
      });
      return left;
    }

    if (render() > 0) {
      var timer = setInterval(function () {
        if (render() === 0) clearInterval(timer);
      }, 15000);
    }
  });
})();
