/*
 * Tiny QR code generator (byte mode, error correction M, versions 1-10 = up to 213 bytes).
 * Used on the "Security" page to show the authenticator-app QR code without any third-party request.
 * Any element with a data-qr="text" attribute gets an inline SVG QR code.
 * GPL-3.0-or-later, University Journal.
 */
(function (root) {
  'use strict';

  var ECC_PER_BLOCK = [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];   // level M, index = version
  var NUM_BLOCKS    = [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5];
  var MAX_VER = 10;

  function rawModules(ver) {
    var r = (16 * ver + 128) * ver + 64;
    if (ver >= 2) {
      var n = Math.floor(ver / 7) + 2;
      r -= (25 * n - 10) * n - 55;
      if (ver >= 7) r -= 36;
    }
    return r;
  }
  function dataCodewords(ver) {
    return Math.floor(rawModules(ver) / 8) - ECC_PER_BLOCK[ver] * NUM_BLOCKS[ver];
  }

  // ---- Reed-Solomon over GF(256), polynomial 0x11D ------------------------------------------
  var EXP = new Array(512), LOG = new Array(256);
  (function () {
    var x = 1;
    for (var i = 0; i < 255; i++) {
      EXP[i] = x; LOG[x] = i;
      x <<= 1; if (x & 0x100) x ^= 0x11D;
    }
    for (var j = 255; j < 512; j++) EXP[j] = EXP[j - 255];
  })();
  function gmul(a, b) { return (a === 0 || b === 0) ? 0 : EXP[LOG[a] + LOG[b]]; }

  function rsGenerator(deg) {
    var poly = [1];
    for (var i = 0; i < deg; i++) {
      var next = new Array(poly.length + 1).fill(0);
      for (var j = 0; j < poly.length; j++) {
        next[j] ^= poly[j];
        next[j + 1] ^= gmul(poly[j], EXP[i]);
      }
      poly = next;
    }
    return poly;               // highest degree first, leading coefficient 1
  }
  function rsRemainder(data, gen) {
    var deg = gen.length - 1;
    var rem = new Array(deg).fill(0);
    for (var i = 0; i < data.length; i++) {
      var f = data[i] ^ rem[0];
      rem.shift(); rem.push(0);
      if (f !== 0) for (var j = 0; j < deg; j++) rem[j] ^= gmul(gen[j + 1], f);
    }
    return rem;
  }

  // ---- bit buffer ----------------------------------------------------------------------------
  function utf8(str) {
    var out = [], s = unescape(encodeURIComponent(str));
    for (var i = 0; i < s.length; i++) out.push(s.charCodeAt(i));
    return out;
  }

  function buildData(bytes, ver) {
    var bits = [];
    function put(val, len) { for (var i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
    put(4, 4);                                   // byte mode
    put(bytes.length, ver <= 9 ? 8 : 16);
    bytes.forEach(function (b) { put(b, 8); });
    var cap = dataCodewords(ver) * 8;
    put(0, Math.min(4, cap - bits.length));      // terminator
    while (bits.length % 8) bits.push(0);
    var cw = [];
    for (var i = 0; i < bits.length; i += 8) {
      var v = 0; for (var k = 0; k < 8; k++) v = (v << 1) | bits[i + k];
      cw.push(v);
    }
    for (var pad = 0xEC; cw.length < dataCodewords(ver); pad ^= 0xEC ^ 0x11) cw.push(pad);
    return cw;
  }

  function addEcc(data, ver) {
    var nb = NUM_BLOCKS[ver], eccLen = ECC_PER_BLOCK[ver];
    var raw = Math.floor(rawModules(ver) / 8);
    var shortCount = nb - raw % nb, shortLen = Math.floor(raw / nb);
    var gen = rsGenerator(eccLen), blocks = [], k = 0;
    for (var i = 0; i < nb; i++) {
      var dat = data.slice(k, k + shortLen - eccLen + (i < shortCount ? 0 : 1));
      k += dat.length;
      var ecc = rsRemainder(dat, gen);
      if (i < shortCount) dat.push(0);
      blocks.push(dat.concat(ecc));
    }
    var out = [];
    for (var c = 0; c < blocks[0].length; c++) {
      for (var j = 0; j < nb; j++) {
        if (c !== shortLen - eccLen || j >= shortCount) out.push(blocks[j][c]);
      }
    }
    return out;
  }

  // ---- matrix --------------------------------------------------------------------------------
  function alignPositions(ver) {
    if (ver === 1) return [];
    var n = Math.floor(ver / 7) + 2, size = ver * 4 + 17;
    var step = Math.floor((ver * 8 + n * 3 + 5) / (n * 4 - 4)) * 2;
    var res = [6];
    for (var pos = size - 7; res.length < n; pos -= step) res.splice(1, 0, pos);
    return res;
  }

  function makeMatrix(codewords, ver) {
    var size = ver * 4 + 17, m = [], fn = [];
    for (var y = 0; y < size; y++) { m.push(new Array(size).fill(false)); fn.push(new Array(size).fill(false)); }
    function setFn(x, y, v) { m[y][x] = v; fn[y][x] = true; }

    for (var i = 0; i < size; i++) { setFn(6, i, i % 2 === 0); setFn(i, 6, i % 2 === 0); }
    function finder(cx, cy) {
      for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) {
        var d = Math.max(Math.abs(dx), Math.abs(dy)), xx = cx + dx, yy = cy + dy;
        if (xx >= 0 && xx < size && yy >= 0 && yy < size) setFn(xx, yy, d !== 2 && d !== 4);
      }
    }
    finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
    var ap = alignPositions(ver), n = ap.length;
    for (var a = 0; a < n; a++) for (var b = 0; b < n; b++) {
      if ((a === 0 && b === 0) || (a === 0 && b === n - 1) || (a === n - 1 && b === 0)) continue;
      for (var dy2 = -2; dy2 <= 2; dy2++) for (var dx2 = -2; dx2 <= 2; dx2++) {
        setFn(ap[a] + dx2, ap[b] + dy2, Math.max(Math.abs(dx2), Math.abs(dy2)) !== 1);
      }
    }
    function drawFormat(mask) {
      var data = (0 << 3) | mask, rem = data;                    // level M = 0
      for (var i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
      var bits = ((data << 10) | rem) ^ 0x5412;
      function bit(i) { return ((bits >>> i) & 1) !== 0; }
      for (var i = 0; i <= 5; i++) setFn(8, i, bit(i));
      setFn(8, 7, bit(6)); setFn(8, 8, bit(7)); setFn(7, 8, bit(8));
      for (var i = 9; i < 15; i++) setFn(14 - i, 8, bit(i));
      for (var i = 0; i < 8; i++) setFn(size - 1 - i, 8, bit(i));
      for (var i = 8; i < 15; i++) setFn(8, size - 15 + i, bit(i));
      setFn(8, size - 8, true);
    }
    drawFormat(0);                                               // reserve the format areas
    if (ver >= 7) {
      var rem = ver;
      for (var i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
      var vb = (ver << 12) | rem;
      for (var i = 0; i < 18; i++) {
        var col = ((vb >>> i) & 1) !== 0, aa = size - 11 + i % 3, bb = Math.floor(i / 3);
        setFn(aa, bb, col); setFn(bb, aa, col);
      }
    }

    // data bits, zig-zag
    var k = 0;
    for (var right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (var vert = 0; vert < size; vert++) {
        for (var j = 0; j < 2; j++) {
          var x = right - j, upward = ((right + 1) & 2) === 0, y = upward ? size - 1 - vert : vert;
          if (!fn[y][x] && k < codewords.length * 8) {
            m[y][x] = ((codewords[k >>> 3] >>> (7 - (k & 7))) & 1) !== 0;
            k++;
          }
        }
      }
    }

    function applyMask(mask) {
      for (var y = 0; y < size; y++) for (var x = 0; x < size; x++) {
        var inv;
        switch (mask) {
          case 0: inv = (x + y) % 2 === 0; break;
          case 1: inv = y % 2 === 0; break;
          case 2: inv = x % 3 === 0; break;
          case 3: inv = (x + y) % 3 === 0; break;
          case 4: inv = (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0; break;
          case 5: inv = x * y % 2 + x * y % 3 === 0; break;
          case 6: inv = (x * y % 2 + x * y % 3) % 2 === 0; break;
          default: inv = ((x + y) % 2 + x * y % 3) % 2 === 0;
        }
        if (inv && !fn[y][x]) m[y][x] = !m[y][x];
      }
    }
    function penalty() {
      var p = 0, y, x, run, i;
      for (y = 0; y < size; y++) {
        run = 1;
        for (x = 1; x < size; x++) {
          if (m[y][x] === m[y][x - 1]) { run++; if (run === 5) p += 3; else if (run > 5) p++; } else run = 1;
        }
      }
      for (x = 0; x < size; x++) {
        run = 1;
        for (y = 1; y < size; y++) {
          if (m[y][x] === m[y - 1][x]) { run++; if (run === 5) p += 3; else if (run > 5) p++; } else run = 1;
        }
      }
      for (y = 0; y < size - 1; y++) for (x = 0; x < size - 1; x++) {
        if (m[y][x] === m[y][x + 1] && m[y][x] === m[y + 1][x] && m[y][x] === m[y + 1][x + 1]) p += 3;
      }
      var pat = [true, false, true, true, true, false, true, false, false, false, false];
      var rev = pat.slice().reverse();
      function match(get) {
        for (var s = 0; s + 11 <= size; s++) {
          var fw = true, bw = true;
          for (var q = 0; q < 11; q++) { var v = get(s + q); if (v !== pat[q]) fw = false; if (v !== rev[q]) bw = false; }
          if (fw) p += 40;
          if (bw) p += 40;
        }
      }
      for (y = 0; y < size; y++) match(function (q) { return m[y][q]; });
      for (x = 0; x < size; x++) match(function (q) { return m[q][x]; });
      var dark = 0;
      for (y = 0; y < size; y++) for (x = 0; x < size; x++) if (m[y][x]) dark++;
      var total = size * size;
      p += (Math.ceil(Math.abs(dark * 20 - total * 10) / total) - 1) * 10;
      return p;
    }
    var best = 0, bestScore = Infinity;
    for (var mask = 0; mask < 8; mask++) {
      applyMask(mask); drawFormat(mask);
      var sc = penalty();
      if (sc < bestScore) { bestScore = sc; best = mask; }
      applyMask(mask);                                           // undo (XOR is its own inverse)
    }
    applyMask(best); drawFormat(best);
    return m;
  }

  /** @returns {boolean[][]|null} dark/light grid, or null when the text is too long */
  function encode(text) {
    var bytes = utf8(String(text));
    for (var ver = 1; ver <= MAX_VER; ver++) {
      var capBits = dataCodewords(ver) * 8, need = 4 + (ver <= 9 ? 8 : 16) + bytes.length * 8;
      if (need <= capBits) return makeMatrix(addEcc(buildData(bytes, ver), ver), ver);
    }
    return null;
  }

  /** Inline SVG markup: black modules on a white square with the 4-module quiet zone scanners need. */
  function svg(text, scale) {
    var m = encode(text);
    if (!m) return '';
    scale = scale || 4;
    var q = 4, n = m.length + q * 2, d = '';
    for (var y = 0; y < m.length; y++) for (var x = 0; x < m.length; x++) {
      if (m[y][x]) d += 'M' + (x + q) + ',' + (y + q) + 'h1v1h-1z';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + n + ' ' + n + '" width="' + (n * scale) + '" height="' + (n * scale) +
      '" shape-rendering="crispEdges" role="img"><rect width="' + n + '" height="' + n + '" fill="#fff"/><path d="' + d + '" fill="#000"/></svg>';
  }

  function auto() {
    var list = document.querySelectorAll('[data-qr]');
    for (var i = 0; i < list.length; i++) {
      var el = list[i];
      if (el.getAttribute('data-qr-done')) continue;
      el.innerHTML = svg(el.getAttribute('data-qr'), 5);
      el.setAttribute('data-qr-done', '1');
    }
  }
  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', auto); else auto();
  }

  root.UJQR = { encode: encode, svg: svg };
  if (typeof module !== 'undefined' && module.exports) module.exports = root.UJQR;
})(typeof window !== 'undefined' ? window : globalThis);
