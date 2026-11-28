/* Tech portal > Settings > Colours: presets, "automatic" colour boxes and a live preview. Nothing is saved until the form is sent. */
(function () {
  var form = document.querySelector('form[data-theme-form]');
  if (!form) return;
  var meta = document.querySelector('meta[name="csrf-token"]');
  var csrf = meta ? meta.getAttribute('content') : '';
  var previews = document.querySelectorAll('.theme-preview');
  var checks = document.getElementById('theme-checks');
  var timer = null, seq = 0;

  function refresh() {
    var mine = ++seq;
    fetch('/tech/settings/colors/preview', { method: 'POST', credentials: 'same-origin', body: new FormData(form), headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (mine !== seq) return;                         // an older answer arrived late
        previews.forEach(function (el) { el.style.cssText = j[el.getAttribute('data-theme')] || ''; });
        if (checks && typeof j.checks === 'string') checks.innerHTML = j.checks;
        Object.keys(j.auto || {}).forEach(function (k) {   // colours left on "automatic" show what they currently work out to
          var inp = form.elements[k], auto = form.elements[k + '__auto'];
          if (inp && auto && auto.checked) {
            inp.value = j.auto[k];
            var code = inp.parentNode.querySelector('[data-hex]'); if (code) code.textContent = j.auto[k];
          }
        });
      })
      .catch(function () {});
  }
  function later() { clearTimeout(timer); timer = setTimeout(refresh, 150); }

  form.addEventListener('input', function (e) {
    var t = e.target;
    if (t && t.type === 'color') {
      var code = t.parentNode.querySelector('[data-hex]'); if (code) code.textContent = t.value;
      var auto = form.elements[t.name + '__auto']; if (auto) auto.checked = false;   // picking a colour turns "automatic" off
    }
    later();
  });
  form.addEventListener('change', later);

  document.querySelectorAll('[data-preset]').forEach(function (b) {
    b.addEventListener('click', function () {
      var set = function (name, v) { var el = form.elements[name]; if (el) { el.value = v; var code = el.parentNode.querySelector('[data-hex]'); if (code) code.textContent = v; } };
      set('brand_primary', b.getAttribute('data-light'));
      set('brand_primary_dark', b.getAttribute('data-dark'));
      ['theme_accent', 'theme_accent_dark'].forEach(function (n) { var a = form.elements[n + '__auto']; if (a) a.checked = true; });
      refresh();
    });
  });
})();
