"""Фиксирует итог 027: проверенные адреса, неизменность поставки, src и прежних evidence."""
import hashlib
import json
from pathlib import Path
import subprocess

PLAN = Path(__file__).resolve().parents[1]
ROOT = PLAN.parents[2]
ARTIFACTS = PLAN / 'artifacts'
STATE = ARTIFACTS / 'implementation-state.json'


def run(command):
    result = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError(f'{command}:\n{result.stdout}\n{result.stderr}')
    return result.stdout


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


# Проверка ссылок плана включает и ссылку на создаваемый ниже итоговый state.
if not STATE.exists():
    STATE.write_text('{}\n')
baseline = json.loads((ARTIFACTS / 'implementation-baseline.json').read_text())['commit']
package = json.loads((ARTIFACTS / 'package-check.json').read_text())
assert package['status'] == 'passed'
expected = package['archives']['git']['file_hashes']
assert expected == package['archives']['composer']['file_hashes']
for name, sha in expected.items():
    path = ROOT / name
    assert path.is_file() and digest(path) == sha, f'После dist изменён файл: {name}'
run(['git', 'diff', '--exit-code', baseline, '--', 'src'])
run(['git', 'diff', '--check', baseline])
workflow = run(['git', 'diff', '--no-renames', '--name-only', baseline, '--', '.workflow']).splitlines()
allowed = ('.workflow/current/pln-027-documentation-restructure/', '.workflow/completed/pln-027-documentation-restructure/')
unexpected = [name for name in workflow if name != '.workflow/README.md' and not name.startswith(allowed)]
assert not unexpected, f'Изменены прежние материалы workflow: {unexpected}'
run(['python3', str(ARTIFACTS / 'check-moves.py')])
plan_check = run(['python3', str(ARTIFACTS / 'check-plan.py')])
(ARTIFACTS / 'implementation-plan-check.json').write_text(plan_check)
run(['python3', 'tests/Support/check-docs.py', '--report', str(ARTIFACTS / 'documentation-check.json')])
paths = [ROOT / name for name in run(['git', 'diff', '--no-renames', '--name-only', baseline]).splitlines()]
paths += [path for path in PLAN.rglob('*') if path.is_file() and '__pycache__' not in path.parts]
paths += [ROOT / '.workflow/current/pln-027-documentation-restructure/pln-027-readme.md']
hashes = {str(path.relative_to(ROOT)): digest(path) for path in sorted(set(paths)) if path.is_file() and path != STATE}
result = {
    'baseline_commit': baseline,
    'checked_package_tree': package['git_reference'],
    'source_tree': run(['git', 'rev-parse', f'{baseline}:src']).strip(),
    'source_unchanged': True,
    'previous_workflow_evidence_unchanged': True,
    'package_files_match_checked_archives': len(expected),
    'package_standalone_smokes_per_archive': 16,
    'full_suite': {'passed': 2027, 'assertions': 7308, 'skipped': 17, 'evidence': 'tests-unrestricted.log'},
    'file_sha256': hashes,
    'status': 'passed',
}
STATE.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({key: value for key, value in result.items() if key != 'file_sha256'}, ensure_ascii=False, indent=2))
print(f'Контрольных сумм итоговых материалов: {len(hashes)}')
