#!/usr/bin/env python3
"""Static form-control audit for server-rendered templates and controller HTML.
Run: python3 tests/audit_form_controls.py
Dynamic PHP concatenation may require browser verification.
"""
from pathlib import Path
import re
ROOT = Path(__file__).resolve().parents[1]
sources = list((ROOT / 'templates').rglob('*.twig')) + list((ROOT / 'src' / 'Controller').glob('*.php'))
issues = []
for path in sources:
    text = path.read_text(errors='replace')
    for match in re.finditer(r'<(input|select|textarea)\b[^>]*>', text, re.I | re.S):
        tag = match.group(0)
        if not re.search(r'\b(?:id|name)\s*=', tag, re.I):
            line = text.count('\n', 0, match.start()) + 1
            issues.append((str(path.relative_to(ROOT)), line, match.group(1)))
print(f'Controls without id or name in static markup: {len(issues)}')
for path, line, tag in issues:
    print(f'{path}:{line}: <{tag}>')
