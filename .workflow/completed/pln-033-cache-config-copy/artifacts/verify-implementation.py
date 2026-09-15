#!/usr/bin/env python3
"""Проверки 033, сравнение наблюдений отдельно от метаданных среды."""
import argparse
import hashlib
import json
import subprocess
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
out = args.output
out.mkdir(parents=True, exist_ok=False)
artifacts = Path(__file__).resolve().parent
source = ['src/Config/ClientConfig.php', 'src/Config/CacheConfig.php', 'src/Core/AbstractClient.php', 'src/Pipeline/Auth/AuthBindingResolver.php', 'src/Pipeline/Auth/AuthHandler.php', 'src/Pipeline/Cache/CacheManager.php']
php = json.loads((artifacts / 'implementation/commands.json').read_text())['style']['command'][3:]
commands = {
    'target': ['vendor/bin/pest', '--colors=never', 'tests/Unit/Core/ClientConfigCacheTest.php', 'tests/Unit/Cache', 'tests/Unit/Auth'],
    'tests': ['composer', 'test', '--', '--colors=never'],
    'phpstan': ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G', *source, 'src/Traits/TestingClientTrait.php'],
    'style': ['vendor/bin/phpcs', '-n', '--report=summary', *php],
    'docs': ['composer', 'check-docs'],
    'docs-types': ['composer', 'analyse-docs'],
    'upstream': ['php', str(artifacts / 'migrated-upstream-probe.php')],
    'whitespace': ['git', 'diff', '--check'],
}
results = {}
for name, command in commands.items():
    proc = subprocess.run(command, capture_output=True, text=True)
    for suffix, value in [('stdout', proc.stdout), ('stderr', proc.stderr)]:
        (out / f'{name}.{suffix}').write_text('\n'.join(line.rstrip() for line in value.splitlines()).rstrip() + '\n' if value.strip() else '')
    results[name] = {'command': command, 'exit': proc.returncode}
    print(f'{name}: {proc.returncode}', flush=True)
actual = json.loads((out / 'upstream.stdout').read_text())['observations']
baseline = json.loads((artifacts / 'implementation-baseline/upstream.stdout').read_text())['observations']
expected_changes = {
    'U06': {'retainedOldConfig': False, 'ttl': 10},
    'U07': {'cacheIsNull': True, 'cacheConfigIsNull': False, 'execution': {'httpCalls': 2, 'first': {'n': 1}, 'second': {'n': 2}}},
    'U13': {'originalUsesStore': True, 'copyUsesStore': True, 'before': {'httpCalls': 1, 'first': {'n': 1}, 'second': {'n': 1}}, 'after': {'httpCalls': 1, 'first': {'n': 1}, 'second': {'n': 1}}},
    'U14': {'originalUsesStore': True, 'copyUsesStore': True, 'execution': {'httpCalls': 1, 'first': {'n': 1}, 'second': {'n': 1}}},
}
failures = []
for a, b in zip(actual, baseline, strict=True):
    expected = dict(b)
    if a['id'] in expected_changes:
        expected['result'] = expected_changes[a['id']]
    if a != expected:
        failures.append(a['id'])
(out / 'upstream-comparison.json').write_text(json.dumps({'count': len(actual), 'failures': failures, 'as5_unchanged': actual[14:] == baseline[14:], 'expected_changes': expected_changes}, indent=2) + '\n')
(out / 'commands.json').write_text(json.dumps(results, ensure_ascii=False, indent=2) + '\n')
(out / 'state.json').write_text(json.dumps({'base': subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip(), 'php': subprocess.check_output(['php', '-r', 'echo PHP_VERSION;'], text=True), 'sha256': {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(Path('src').rglob('*.php'))}}, indent=2) + '\n')
raise SystemExit(bool(failures) or any(item['exit'] for item in results.values()))
