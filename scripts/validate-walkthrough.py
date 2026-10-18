#!/usr/bin/env python3
"""Ensure every walkthrough's full PHP source listing exactly matches current PHP files."""
from pathlib import Path
from hashlib import sha256
import re

root = Path(__file__).resolve().parents[1]
walkthroughs = list((root / 'learning' / 'code-walkthrough').glob('*.md'))
validated = 0
for document in walkthroughs:
    if document.name == 'README.md':
        continue
    source_path = document.stem.replace('--', '/')
    source = root / (source_path + '.php')
    if not source.exists():
        raise SystemExit(f'MISSING source: {source}')
    source_text = source.read_text(encoding='utf-8')
    doc_text = document.read_text(encoding='utf-8')
    code = re.search(r'```php\n(.*?)\n```', doc_text, flags=re.DOTALL)
    digest = re.search(r'SHA-256: `([a-f0-9]{64})`', doc_text)
    if code is None or digest is None:
        raise SystemExit(f'MISSING code/hash in {document.name}')
    if code.group(1) + '\n' != source_text or digest.group(1) != sha256(source_text.encode()).hexdigest():
        raise SystemExit(f'STALE walkthrough: {document.name}')
    validated += 1
if validated == 0:
    raise SystemExit('No walkthroughs found')
print(f'PASS: {validated} source listings exactly match their source files.')
