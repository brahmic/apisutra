"""Проверка локальных Markdown-ссылок публичной документации."""
from pathlib import Path
import re
import sys
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[2]
FILES = [ROOT / 'README.md', ROOT / 'CHANEGLOG.md', *sorted((ROOT / 'docs').rglob('*.md'))]
errors = []
for path in FILES:
    content = path.read_text()
    for link in re.findall(r'\]\(([^)]+)\)', content):
        target = link.strip('<>').split('#', 1)[0]
        if not target or '://' in target or target.startswith('mailto:'):
            continue
        resolved = (path.parent / unquote(target)).resolve()
        if not resolved.exists():
            errors.append(f'{path.relative_to(ROOT)}: {link}')
        if '.workflow' in resolved.parts:
            errors.append(f'{path.relative_to(ROOT)}: ссылка на внутренний workflow')
if errors:
    print('\n'.join(errors), file=sys.stderr)
    sys.exit(1)
print(f'Локальные ссылки проверены: {len(FILES)} документов.')
