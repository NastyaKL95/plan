(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.getElementById('bxEco');
    var btn = document.getElementById('bxEcoBtn');
    var panel = document.getElementById('bxEcoPanel');
    if (!wrap || !btn || !panel) return;

    function setOpen(open) {
      panel.hidden = !open;
      if (open && window.matchMedia('(max-width: 760px)').matches) {
        panel.style.top = Math.round(btn.getBoundingClientRect().bottom + 8) + 'px';
      } else {
        panel.style.top = '';
      }
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      wrap.classList.toggle('is-open', open);
    }

    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      var open = panel.hidden;
      setOpen(open);
      if (open) {
        var first = panel.querySelector('.bx-eco-item');
        if (first) first.focus({ preventScroll: true });
      }
    });

    document.addEventListener('click', function (ev) {
      if (!panel.hidden && !wrap.contains(ev.target)) setOpen(false);
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !panel.hidden) {
        setOpen(false);
        btn.focus();
      }
    });
  });
})();
