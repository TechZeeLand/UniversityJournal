// Runs before first paint to avoid a flash of the wrong theme.
(function () {
  var m = document.cookie.match(/(?:^|; )theme=(light|dark)/);
  var t = m ? m[1] : (document.documentElement.getAttribute('data-theme') || 'auto');
  if (t === 'auto') t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', t);
})();
