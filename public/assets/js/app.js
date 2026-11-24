(function () {
  var root = document.documentElement;

  var toggle = document.querySelector('.theme-toggle');
  if (toggle) toggle.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    document.cookie = 'theme=' + next + ';path=/;max-age=31536000;samesite=lax';
  });

  var btn = document.querySelector('.nav-toggle');
  var nav = document.getElementById('nav');
  if (btn && nav) btn.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Show / hide password
  document.querySelectorAll('[data-toggle-pw]').forEach(function (b) {
    b.addEventListener('click', function () {
      var input = document.getElementById(b.getAttribute('data-toggle-pw'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      var ic = b.querySelector('.zi');
      if (ic) { ic.classList.toggle('zi-eye', !show); ic.classList.toggle('zi-eye-off', show); }
    });
  });

  // Confirmation before sensitive forms (data-confirm="message")
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute && ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });

  // Copy text of an element (data-copy="elementId")
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var el = document.getElementById(b.getAttribute('data-copy'));
      if (!el || !navigator.clipboard) return;
      navigator.clipboard.writeText(el.textContent.trim());
    });
  });

  // Live preview of a chosen photo (data-preview="containerId")
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var box = document.getElementById(inp.getAttribute('data-preview'));
      var f = inp.files && inp.files[0];
      if (!box || !f || f.type.indexOf('image/') !== 0) return;
      var img = document.createElement('img');
      img.alt = '';
      img.src = URL.createObjectURL(f);
      var wrap = box.querySelector('.avatar');
      if (wrap) { wrap.textContent = ''; wrap.appendChild(img); }
    });
  });
})();
