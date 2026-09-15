#!/usr/bin/env python3
"""Сохранение команд и результатов проверки реализации из корня пакета."""
import argparse
import hashlib
import json
import subprocess
from pathlib import Path

root = Path.cwd()
parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
out = args.output
out.mkdir(parents=True, exist_ok=False)
commands = {
    'target': ['vendor/bin/pest', '--colors=never', *map(str, sorted(Path('tests/Unit/Serialization').glob('ConstructorOwned*Test.php')))],
    'tests': ['composer', 'test', '--', '--colors=never'],
    'phpstan': ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G', 'src/Serialization/Hydrator.php', 'src/Serialization/Rules/FieldRule.php', 'src/Serialization/Rules/RuleSetCompiler.php', 'src/Serialization/Rules/ConstructorValues.php', 'src/Serialization/NativePropertyValue.php'],
    'style': ['vendor/bin/phpcs', '-n', '--report=summary', 'src/Serialization/Hydrator.php', 'src/Serialization/Rules/FieldRule.php', 'src/Serialization/Rules/RuleSetCompiler.php', 'src/Serialization/Rules/ConstructorValues.php', 'src/Serialization/NativePropertyValue.php', 'tests/Stubs/ConstructorOwned', 'tests/Support/ConstructorOwnedFixture.php', 'docs/example/constructor-values', 'tests/Support/standalone-constructor-values-smoke.php', *map(str, sorted(Path('tests/Unit/Serialization').glob('ConstructorOwned*Test.php')))],
    'docs': ['composer', 'check-docs'],
    'docs-types': ['composer', 'analyse-docs'],
    'example': ['php', 'tests/Support/standalone-constructor-values-smoke.php'],
    'upstream': ['php', '.workflow/issue/iss-003-apisutra-contracts/artifacts/upstream-probe.php'],
    'whitespace': ['git', 'diff', '--check'],
}
results = {}
for name, command in commands.items():
    proc = subprocess.run(command, capture_output=True, text=True)
    for suffix, value in [('stdout', proc.stdout), ('stderr', proc.stderr)]:
        (out / f'{name}.{suffix}').write_text('\n'.join(line.rstrip() for line in value.splitlines()).rstrip() + '\n' if value.strip() else '')
    results[name] = {'command': command, 'exit': proc.returncode}
    print(f'{name}: {proc.returncode}', flush=True)
(out / 'commands.json').write_text(json.dumps(results, ensure_ascii=False, indent=2) + '\n')
(out / 'state.json').write_text(json.dumps({'base': subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip(), 'php': subprocess.check_output(['php', '-r', 'echo PHP_VERSION;'], text=True), 'sha256': {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(root.glob('src/**/*.php'))}}, indent=2) + '\n')
raise SystemExit(any(item['exit'] for item in results.values()))
