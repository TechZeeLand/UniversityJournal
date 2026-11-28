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

  // Copy text to the clipboard. navigator.clipboard only exists on HTTPS (or localhost), so on a plain-HTTP
  // address (for example http://server:8090) we fall back to a hidden textarea and document.execCommand('copy').
  // Resolves true when the text was copied, false when the browser refused (the caller then shows the text to select).
  function copyText(text) {
    function legacy() {
      return new Promise(function (resolve) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try { ta.setSelectionRange(0, text.length); } catch (e) {}
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) {}
        ta.remove();
        resolve(ok);
      });
    }
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).then(function () { return true; }, legacy);
    }
    return legacy();
  }
  window.ujCopy = copyText;

  // Say "Copied!" on a button for a moment (the button's data-done text), or select the text when copying failed
  function flash(btn, ok, fallbackEl) {
    var label = btn.querySelector('span') || btn;
    var old = label.textContent;
    if (ok) {
      label.textContent = btn.getAttribute('data-done') || 'Copied!';
      btn.classList.add('is-done');
    } else if (fallbackEl && window.getSelection) {
      var r = document.createRange(); r.selectNodeContents(fallbackEl);
      var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
      label.textContent = btn.getAttribute('data-fail') || 'Press Ctrl+C';
    }
    setTimeout(function () { label.textContent = old; btn.classList.remove('is-done'); }, 1800);
  }

  // Copy text of an element (data-copy="elementId")
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var el = document.getElementById(b.getAttribute('data-copy'));
      if (!el) return;
      copyText(el.textContent.trim()).then(function (ok) { flash(b, ok, el); });
    });
  });

  // A list of codes (data-copy-list="#list" / data-download-list="#list"): one code per line, for backup codes
  function listText(sel) {
    var out = [];
    document.querySelectorAll(sel + ' li').forEach(function (li) { out.push(li.textContent.trim()); });
    return out.join('\n') + '\n';
  }
  document.querySelectorAll('[data-copy-list]').forEach(function (b) {
    b.addEventListener('click', function () {
      copyText(listText(b.getAttribute('data-copy-list'))).then(function (ok) { flash(b, ok, null); });
    });
  });
  document.querySelectorAll('[data-download-list]').forEach(function (b) {
    b.addEventListener('click', function () {
      var blob = new Blob([listText(b.getAttribute('data-download-list'))], { type: 'text/plain' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = b.getAttribute('data-filename') || 'codes.txt';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
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

/* ===== articles: explore filters, reactions, share, comments, reading progress ===== */
(function () {
  var meta = document.querySelector('meta[name="csrf-token"]');
  var csrf = meta ? meta.getAttribute('content') : '';

  function post(url, data) {
    var body = new URLSearchParams(data || {});
    return fetch(url, {
      method: 'POST', credentials: 'same-origin', body: body,
      headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'fetch', 'Accept': 'application/json' }
    });
  }

  // Explore: apply a filter as soon as it changes
  document.querySelectorAll('form[data-autosubmit]').forEach(function (f) {
    f.addEventListener('change', function (ev) {
      var t = ev.target;
      if (t && (t.tagName === 'SELECT' || t.type === 'radio' || t.type === 'date')) f.submit();
    });
  });

  // Like / dislike without reloading the page
  var box = document.querySelector('.reactions[data-react-url]');
  if (box) box.querySelectorAll('.react-btn[data-value]').forEach(function (btn) {
    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      post(box.getAttribute('data-react-url'), { value: btn.getAttribute('data-value') })
        .then(function (r) { return r.json().then(function (j) { return [r.status, j]; }); })
        .then(function (x) {
          var j = x[1];
          if (x[0] === 401 && j.login) { window.location.href = j.login; return; }
          if (x[0] === 403 && j.redirect) { window.location.href = j.redirect; return; }
          if (!j.ok) return;
          var fmt = function (n) { return document.documentElement.lang === 'bn' ? String(n).replace(/\d/g, function (d) { return '০১২৩৪৫৬৭৮৯'[d]; }) : String(n); };
          box.querySelector('[data-count="like"]').textContent = fmt(j.like);
          box.querySelector('[data-count="dislike"]').textContent = fmt(j.dislike);
          box.querySelectorAll('.react-btn[data-value]').forEach(function (b) {
            var on = (b.getAttribute('data-value') === 'like' && j.mine === 1) || (b.getAttribute('data-value') === 'dislike' && j.mine === -1);
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
          });
          btn.classList.add('pop');
          setTimeout(function () { btn.classList.remove('pop'); }, 300);
        })
        .catch(function () { btn.closest('form').submit(); });
    });
  });

  // Reading list: save or remove without reloading
  document.querySelectorAll('[data-bookmark]').forEach(function (btn) {
    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      post(btn.getAttribute('data-bookmark'), {})
        .then(function (r) { return r.json().then(function (j) { return [r.status, j]; }); })
        .then(function (x) {
          var j = x[1];
          if (x[0] === 401 && j.login) { window.location.href = j.login; return; }
          if (!j.ok) return;
          btn.classList.toggle('is-on', !!j.saved);
          btn.setAttribute('aria-pressed', j.saved ? 'true' : 'false');
          var l = btn.querySelector('.react-label'); if (l) l.textContent = btn.getAttribute(j.saved ? 'data-on' : 'data-off');
        })
        .catch(function () { btn.closest('form').submit(); });
    });
  });

  // Share: native sheet where the browser has one, copy link, one count per visit
  var share = document.querySelector('.share[data-share-url]');
  if (share) {
    var url = share.getAttribute('data-share-url');
    var counted = false;
    var count = function () {
      if (counted) return; counted = true;
      post(share.getAttribute('data-share-count'), {}).catch(function () {});
    };
    var nat = share.querySelector('[data-native-share]');
    if (nat && navigator.share) {
      nat.hidden = false;
      nat.addEventListener('click', function () {
        navigator.share({ title: share.getAttribute('data-share-title'), url: url }).then(count).catch(function () {});
      });
    }
    var cp = share.querySelector('[data-copy-link]');
    if (cp) cp.addEventListener('click', function () {
      copyText(url).then(function (ok) {
        if (ok) { flash(cp, true, null); count(); } else { window.prompt('', url); }
      });
    });
    share.querySelectorAll('[data-share-net]').forEach(function (a) { a.addEventListener('click', count); });
  }

  // Comments: reply target and characters left
  var cf = document.querySelector('[data-comment-form]');
  if (cf) {
    var parent = cf.querySelector('[data-parent]'), note = cf.querySelector('[data-reply-note]'), ta = cf.querySelector('textarea');
    document.querySelectorAll('[data-reply]').forEach(function (b) {
      b.addEventListener('click', function () {
        parent.value = b.getAttribute('data-reply');
        note.hidden = false; cf.scrollIntoView({ behavior: 'smooth', block: 'center' }); ta.focus();
      });
    });
    var cancel = cf.querySelector('[data-reply-cancel]');
    if (cancel) cancel.addEventListener('click', function () { parent.value = ''; note.hidden = true; });
    var left = cf.querySelector('[data-count-left]');
    if (left && ta) ta.addEventListener('input', function () { left.textContent = ta.maxLength - ta.value.length; });
  }

  // Reading progress bar
  var bar = document.querySelector('.read-progress span');
  var body = document.querySelector('.article-body');
  if (bar && body) {
    var upd = function () {
      var r = body.getBoundingClientRect(), h = r.height - window.innerHeight * 0.5;
      var p = h <= 0 ? 1 : Math.min(1, Math.max(0, -r.top / h));
      bar.style.width = (p * 100).toFixed(1) + '%';
    };
    window.addEventListener('scroll', upd, { passive: true }); upd();
  }
})();

/* ===== writer: Markdown toolbar, live preview, inline pictures, unsaved-changes warning ===== */
(function () {
  var form = document.querySelector('form[data-editor]');
  if (!form) return;
  var ta = form.querySelector('textarea.md-body');
  var meta = document.querySelector('meta[name="csrf-token"]');
  var csrf = meta ? meta.getAttribute('content') : '';
  var dirty = false, submitting = false;
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { submitting = true; });
  window.addEventListener('beforeunload', function (e) { if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; } });

  function wrap(before, after, ph) {
    var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value, sel = v.slice(s, e) || ph;
    ta.value = v.slice(0, s) + before + sel + after + v.slice(e);
    ta.focus(); ta.selectionStart = s + before.length; ta.selectionEnd = s + before.length + sel.length;
    ta.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function linePrefix(prefix) {
    var s = ta.selectionStart, v = ta.value, ls = v.lastIndexOf('\n', s - 1) + 1;
    ta.value = v.slice(0, ls) + prefix + v.slice(ls);
    ta.focus(); ta.selectionStart = ta.selectionEnd = s + prefix.length;
    ta.dispatchEvent(new Event('input', { bubbles: true }));
  }
  var acts = {
    h2: function () { linePrefix('## '); }, bold: function () { wrap('**', '**', 'bold'); }, italic: function () { wrap('*', '*', 'italic'); },
    quote: function () { linePrefix('> '); }, ul: function () { linePrefix('- '); }, code: function () { wrap('`', '`', 'code'); },
    link: function () { var u = window.prompt('https://'); if (u) wrap('[', '](' + u + ')', 'link'); }
  };
  form.querySelectorAll('[data-md]').forEach(function (b) { b.addEventListener('click', function () { acts[b.getAttribute('data-md')](); }); });

  var img = form.querySelector('[data-md-image]');
  if (img) img.addEventListener('change', function () {
    var f = img.files && img.files[0]; if (!f) return;
    var fd = new FormData(); fd.append('image', f);
    fetch('/write/image', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j.ok) wrap('![', '](' + j.url + ')', ''); else window.alert(j.error || 'Upload failed'); })
      .catch(function () { window.alert('Upload failed'); });
    img.value = '';
  });

  var split = form.querySelector('[data-md-split]'), wrapEl = form.querySelector('[data-split]'), prev = form.querySelector('[data-preview]'), timer = null;
  function render() {
    fetch('/write/preview', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ body: ta.value }), headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); }).then(function (j) { prev.innerHTML = j.html; }).catch(function () {});
  }
  if (split) split.addEventListener('click', function () {
    var on = prev.hidden; prev.hidden = !on; wrapEl.classList.toggle('is-split', on); split.setAttribute('aria-pressed', on ? 'true' : 'false');
    if (on) render();
  });
  ta.addEventListener('input', function () { if (!prev.hidden) { clearTimeout(timer); timer = setTimeout(render, 500); } });

  // Automatic draft saving: a few seconds after typing stops, only the text fields are sent
  var secs = parseInt(form.getAttribute('data-autosave') || '0', 10);
  var stateEl = form.querySelector('[data-save-state]');
  if (secs > 0) {
    var aTimer = null, busy = false, changed = 0, artId = form.getAttribute('data-article-id') || '0';
    var say = function (t) { if (stateEl) stateEl.textContent = t; };
    var save = function () {
      if (submitting) return;
      if (busy) { aTimer = setTimeout(save, 1500); return; }
      busy = true; var mark = changed; say(form.getAttribute('data-s-saving'));
      var fd = new URLSearchParams();
      ['title', 'summary', 'body', 'lang', 'type_id', 'department_id'].forEach(function (n) { var el = form.elements[n]; if (el) fd.append(n, el.value); });
      fd.append('id', artId);
      fetch('/write/autosave', { method: 'POST', credentials: 'same-origin', body: fd, headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          busy = false;
          if (!j.ok) { say(form.getAttribute('data-s-fail')); return; }
          if (j.skipped) { say(''); return; }
          if (artId === '0' && j.id) {          // the first save made the draft: from now on this page edits it
            artId = String(j.id);
            form.setAttribute('action', '/write/' + j.id);
            try { history.replaceState(null, '', '/write/' + j.id); } catch (e) {}
          }
          if (mark === changed) dirty = false;
          say(form.getAttribute('data-s-saved').replace('{time}', j.at || ''));
        })
        .catch(function () { busy = false; say(form.getAttribute('data-s-fail')); });
    };
    form.addEventListener('input', function () { changed++; clearTimeout(aTimer); aTimer = setTimeout(save, secs * 1000); });
    form.addEventListener('change', function (e) { if (e.target && e.target.type !== 'file') { changed++; clearTimeout(aTimer); aTimer = setTimeout(save, 800); } });
  }
})();
