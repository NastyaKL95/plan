(function () {
  function pad(n) {
    return n < 10 ? '0' + n : '' + n;
  }
  function tickClock() {
    var el = document.getElementById('bxClock');
    if (!el) return;
    var d = new Date();
    el.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
    el.setAttribute('datetime', d.toISOString());
  }
  tickClock();
  setInterval(tickClock, 30000);

  var input = document.getElementById('globalSearch');
  if (input) {
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var root = typeof window.__PLANNER_ROOT__ === 'string' ? window.__PLANNER_ROOT__ : '';
        var q = encodeURIComponent(input.value.trim());
        window.location.href = root + '/tasks.php' + (q ? '?q=' + q : '');
      }
    });
  }

  document.querySelectorAll('.bx-rail-search').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var s = document.getElementById('globalSearch');
      if (s) {
        s.focus();
        s.select();
      }
    });
  });
})();
