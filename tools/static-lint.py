#!/usr/bin/env python3
"""
Static checks for the University Journal source (no PHP needed). Run from the project root:
    python3 tools/static-lint.py [path/to/techzeeland-icons-docs.md]
Checks: bracket balance in every PHP file, English/Bangla key parity, every t('key') exists, every Class::method() call
exists, every route handler exists, every View::render('x') file exists, every icon exists in the icon set (when the docs file is given),
JavaScript syntax (needs node). It does NOT replace running the site: it only catches typing slips.
"""
import glob, os, re, subprocess, sys

root = os.getcwd()
errors = []
def err(msg): errors.append(msg)

php_files = sorted(glob.glob('app/**/*.php', recursive=True) + glob.glob('bin/*.php') + ['public/index.php'])

def strip_php(src):
    """Remove comments and string contents so brackets inside them do not count."""
    out, i, n = [], 0, len(src)
    while i < n:
        c = src[i]
        if src.startswith('//', i) or c == '#' and not src.startswith('#[', i):
            j = src.find('\n', i); i = n if j < 0 else j; continue
        if src.startswith('/*', i):
            j = src.find('*/', i + 2); i = n if j < 0 else j + 2; continue
        if src.startswith('<<<', i):
            m = re.match(r"<<<\s*['\"]?(\w+)['\"]?\r?\n", src[i:])
            if m:
                end = re.search(r'\n\s*' + m.group(1) + r'\b', src[i + m.end():])
                i = i + m.end() + (end.end() if end else n); continue
        if c in '\'"':
            q = c; i += 1
            while i < n and src[i] != q:
                i += 2 if src[i] == '\\' else 1
            i += 1; out.append('S'); continue
        out.append(c); i += 1
    return ''.join(out)

def view_code(src):
    """Views mix HTML and PHP: keep only what is inside <?php ... ?> and <?= ... ?>."""
    parts = re.findall(r'<\?(?:php|=)(.*?)(?:\?>|\Z)', src, re.S)
    return '\n;'.join(parts)

classes = {}   # short name -> (file, set(methods), set(consts))
for f in php_files:
    s = open(f, encoding='utf8').read()
    m = re.search(r'^(?:final\s+|abstract\s+)?class\s+(\w+)', s, re.M)
    if m:
        classes[m.group(1)] = (f, set(re.findall(r'function\s+(\w+)\s*\(', s)), set(re.findall(r'\bconst\s+(\w+)', s)))

for f in php_files + sorted(glob.glob('app/views/**/*.php', recursive=True)):
    raw = open(f, encoding='utf8').read()
    code = strip_php(view_code(raw) if '/views/' in f else raw)
    for a, b in ('()', '[]', '{}'):
        if code.count(a) != code.count(b):
            err(f'{f}: unbalanced {a}{b} ({code.count(a)} vs {code.count(b)})')

# Class::method references
for f in sorted(set(php_files + glob.glob('app/views/**/*.php', recursive=True))):
    src = open(f, encoding='utf8').read()
    for m in re.finditer(r'(?<![\w$])\\?(?:App\\(?:Core|Services|Controllers)\\)?([A-Z]\w+)::(\w+)\s*\(', src):
        cls, meth = m.group(1), m.group(2)
        if cls in classes and meth not in classes[cls][1] and meth != 'class':
            err(f'{f}: {cls}::{meth}() does not exist')

# routes
rt = open('app/routes.php', encoding='utf8').read()
for m in re.finditer(r'\[(\w+)::class,\s*\'(\w+)\'\]', rt):
    c, meth = m.groups()
    if c not in classes: err(f'routes: class {c} not found')
    elif meth not in classes[c][1]: err(f'routes: {c}::{meth} does not exist')

# views
for f in php_files:
    for m in re.finditer(r"View::(?:render|partial)\('([\w/]+)'", open(f, encoding='utf8').read()):
        if not os.path.exists(f'app/views/{m.group(1)}.php'): err(f'{f}: view {m.group(1)} missing')
for f in glob.glob('app/views/**/*.php', recursive=True):
    for m in re.finditer(r"View::(?:render|partial)\('([\w/]+)'", open(f, encoding='utf8').read()):
        if not os.path.exists(f'app/views/{m.group(1)}.php'): err(f'{f}: view {m.group(1)} missing')

# language files
def keys(p): return set(re.findall(r"'([A-Za-z0-9_.]+)'\s*=>", open(p, encoding='utf8').read()))
L = {}
for loc in ('en', 'bn'):
    k = set()
    for f in glob.glob(f'app/lang/{loc}/*.php') + [f'app/lang/{loc}.php']: k |= keys(f)
    L[loc] = k
for k in sorted(L['en'] ^ L['bn']): err(f'language key only in one language: {k}')
used = set()
for f in glob.glob('app/**/*.php', recursive=True) + ['public/index.php']:
    used |= set(re.findall(r"\bt\(\s*'([a-z0-9_.]+)'\s*[,)]", open(f, encoding='utf8').read()))
for k in sorted(used - L['en']):
    if k not in ('x', 'x_seq'): err(f"t('{k}') has no text")

# icons
if len(sys.argv) > 1 and os.path.exists(sys.argv[1]):
    doc = open(sys.argv[1], encoding='utf8').read()
    sec = doc[doc.find('## All icon names'):doc.find('## Accessibility')]
    have = set(re.findall(r'`zi-([a-z0-9-]+)`', sec))
    ic = {}
    for f in glob.glob('app/**/*.php', recursive=True) + glob.glob('public/assets/**/*.js', recursive=True) + glob.glob('public/assets/**/*.css', recursive=True):
        s = open(f, encoding='utf8').read()
        for rx in (r"icon\(\s*'([a-z0-9-]+)'", r"\bzi-([a-z0-9-]+)", r"'icon'\s*=>\s*'([a-z0-9-]+)'"):
            for m in re.finditer(rx, s):
                if m.group(1) not in ('lg', 'xl', '2x', '3x', 'spin'): ic.setdefault(m.group(1), f)
    for k, f in sorted(ic.items()):
        if k not in have: err(f'icon "{k}" is not in the icon set (used in {f})')

# JavaScript
for f in glob.glob('public/assets/js/*.js'):
    try:
        r = subprocess.run(['node', '--check', f], capture_output=True, text=True)
        if r.returncode: err(f'{f}: {r.stderr.strip().splitlines()[0] if r.stderr else "syntax error"}')
    except FileNotFoundError:
        break

print(f'{len(php_files)} PHP files, {len(L["en"])} language keys per language, {len(classes)} classes')
if errors:
    print(f'{len(errors)} problem(s):'); [print(' -', e) for e in errors]; sys.exit(1)
print('OK: no problems found')
