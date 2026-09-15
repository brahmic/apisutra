"""Проверяет покрытие старых разделов, переходы и ссылки инструкций checkout."""
import collections
import importlib.util
import json
from pathlib import Path
import posixpath
import re
import subprocess
from urllib.parse import unquote, urlsplit

PLAN = Path(__file__).resolve().parents[1]
ROOT = PLAN.parents[2]
spec = importlib.util.spec_from_file_location('docs_check', ROOT / 'tests/Support/check-docs.py')
check = importlib.util.module_from_spec(spec)
spec.loader.exec_module(check)
baseline = json.loads((PLAN / 'artifacts/implementation-baseline.json').read_text())
moves = json.loads((PLAN / 'artifacts/resolved-section-moves.json').read_text())
errors = []
by_source = collections.defaultdict(list)
for move in moves:
    by_source[move['source']].append(move)
old_texts = {row['path']: subprocess.check_output(['git', 'show', f"{baseline['commit']}:{row['path']}"], cwd=ROOT).decode() for row in baseline['files']}
if set(old_texts) != set(by_source):
    errors.append('Неполное множество исходных файлов')
incoming = collections.defaultdict(list)
for source, body in old_texts.items():
    for value in check.links(body):
        parsed = urlsplit(value)
        if parsed.scheme or parsed.netloc:
            continue
        target = posixpath.normpath(posixpath.join(posixpath.dirname(source), unquote(parsed.path))) if parsed.path else source
        incoming[target, unquote(parsed.fragment)].append(source)
verified = []
for source, body in old_texts.items():
    old = check.headings(body)
    rows = by_source[source]
    if old != [row['anchor'] for row in rows]:
        errors.append(f'Неполная или переставленная последовательность разделов: {source}')
    existing = check.anchors((ROOT / source).read_text())
    for row in rows:
        target = ROOT / row['target']
        if not target.is_file() or row['fragment'] not in check.anchors(target.read_text()):
            errors.append(f"Нет назначения: {row['target']}#{row['fragment']}")
        if row['anchor'] not in existing:
            errors.append(f"Нет прежнего адреса: {source}#{row['anchor']}")
        verified.append({**row, 'incoming_from_baseline': sorted(set(incoming[source, row['anchor']])), 'compatibility': 'прежний путь и якорь сохранены', 'status': 'адреса проверены'})
# Редакторские инструкции не входят в пользовательский dist, но их ссылки тоже проверяются.
rule_links = 0
for path in [ROOT / 'AGENTS.md', *sorted((ROOT / '.agents').glob('*.md'))]:
    for value in check.links(path.read_text()):
        parsed = urlsplit(value)
        if parsed.scheme or parsed.netloc:
            continue
        rule_links += 1
        target = (path.parent / unquote(parsed.path)).resolve() if parsed.path else path
        if not target.exists():
            errors.append(f'{path.relative_to(ROOT)}: нет {value}')
        elif parsed.fragment and target.suffix == '.md' and unquote(parsed.fragment) not in check.anchors(target.read_text()):
            errors.append(f'{path.relative_to(ROOT)}: нет якоря {value}')
result = {'baseline_commit': baseline['commit'], 'source_files': len(by_source), 'headings': len(moves), 'rule_links': rule_links, 'errors': errors, 'sections': verified}
(PLAN / 'artifacts/move-check.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({key: value for key, value in result.items() if key != 'sections'}, ensure_ascii=False, indent=2))
raise SystemExit(bool(errors))
