"""Повторная проверка фактуры 032/033; не является приёмкой будущего API."""
import argparse
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import subprocess
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parents[4]
REFERENCE = '6a649f5e247adba3ecc0c82f1ba68cb74c018aba'
ISSUE = Path('.workflow/issue/iss-003-apisutra-contracts/artifacts')
P32 = Path('.workflow/current/pln-032-constructor-owned-fields')
P33 = Path('.workflow/current/pln-033-cache-config-copy')


def save(path, value):
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    output = args.output.resolve()
    # Сохранённый прогон не перезаписывается незаметно следующей проверкой.
    output.mkdir(parents=True, exist_ok=False)
    commands = []

    def run(name, argv):
        result = subprocess.run(argv, cwd=ROOT, text=True, capture_output=True)
        # Убираем хвостовые пробелы и пустые строки оформления CLI.
        for suffix, content in [('stdout', result.stdout), ('stderr', result.stderr)]:
            normalized = re.sub(r'[ \t]+(?=\r?$)', '', content, flags=re.M).rstrip('\n')
            (output / (name + '.' + suffix)).write_text(normalized + '\n' if normalized else '')
        commands.append({'name': name, 'argv': argv, 'exit_code': result.returncode})
        save(output / 'commands.json', commands)
        return result

    head = run('git-head', ['git', 'rev-parse', 'HEAD']).stdout.strip()
    trees = run('source-trees', ['git', 'rev-parse', 'HEAD:src', REFERENCE + ':src']).stdout.splitlines()
    source_diff = run('source-diff', ['git', 'diff', '--exit-code', REFERENCE, '--', 'src'])
    run('git-status', ['git', 'status', '--short'])
    run('php-version', ['php', '-v'])
    run('composer-version', ['composer', '--version', '--no-ansi'])
    original = json.loads((ROOT / ISSUE / 'upstream-observations.json').read_text())
    probe = run('upstream', ['php', str(ISSUE / 'upstream-probe.php'), 'vendor/autoload.php'])
    actual = json.loads(probe.stdout)
    verification = run('upstream-verify', ['php', str(ISSUE / 'upstream-probe.php'), 'vendor/autoload.php', '--verify'])
    observations_equal = actual['observations'] == original['observations']
    reference_equal = actual['environment']['reference'] == original['environment']['reference']
    # bootstrap.php сравнивает observations и reference, но не PHP/prettyVersion.
    expected_verify_code = 0 if observations_equal and reference_equal else 1
    comparison = {
        'observations': len(actual['observations']),
        'observations_equal': observations_equal,
        'expected_environment': original['environment'],
        'actual_environment': actual['environment'],
        'composer_reference_equal': reference_equal,
        'original_verify_exit_code': verification.returncode,
        'original_verify_expected_exit_code': expected_verify_code,
        'git_head': head,
        'source_trees': trees,
    }
    save(output / 'upstream-comparison.json', comparison)
    legacy = run('legacy-typing', ['php', str(P32 / 'artifacts/legacy-typing-probe.php')])
    lint = run('legacy-lint', ['php', '-l', str(P32 / 'artifacts/legacy-typing-probe.php')])
    style = run('legacy-style', ['vendor/bin/phpcs', '--standard=PSR12', str(P32 / 'artifacts/legacy-typing-probe.php')])
    tests = run('tests', [
        'vendor/bin/pest', '--colors=never', 'tests/Unit/Core/ClientConfigTest.php',
        'tests/Unit/Serialization/HydrationCompatibilityTest.php',
        'tests/Unit/Serialization/ExternalHydrationRulesTest.php',
        'tests/Unit/Cache/CacheManagerOverridesTest.php',
    ])
    public_docs = run('public-docs', ['composer', 'check-docs'])
    whitespace = run('whitespace', ['git', 'diff', '--check'])

    # Список кандидатов переноса ограничен тестами и helpers; не генерируем код.
    files = run('test-files', ['rg', '--files', 'tests', '--glob', '*.php']).stdout.splitlines()
    pattern = re.compile(r'\bcache\s*:|[\'\"]cache[\'\"]\s*=>')
    inventory = {}
    for name in sorted(files):
        if not (name.endswith('Test.php') or name.startswith('tests/Support/')):
            continue
        hits = [{'line': i, 'text': line.strip()} for i, line in
                enumerate((ROOT / name).read_text().splitlines(), 1) if pattern.search(line)]
        if hits:
            inventory[name] = hits
    save(output / 'migration-inventory.json', {
        'scope': 'tests/**/*Test.php and tests/Support/*.php; candidate syntax cache: or cache =>',
        'test_files': sum(name.endswith('Test.php') for name in inventory),
        'support_files': sum(name.startswith('tests/Support/') for name in inventory),
        'files': inventory,
        'live_guide_matches': [i for i, line in enumerate(
            (ROOT / 'docs/reference/testing/live.md').read_text().splitlines(), 1) if pattern.search(line)],
    })

    spec = importlib.util.spec_from_file_location('doc_check', ROOT / 'tests/Support/check-docs.py')
    checker = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(checker)
    documents = [ROOT / '.workflow/README.md', ROOT / '.workflow/adr/adr-003-constructor-owned-fields.md',
                 ROOT / '.workflow/adr/adr-004-cache-store-separation.md']
    for plan in [P32, P33]:
        documents.extend(sorted((ROOT / plan).glob('*.md')))
        documents.append(ROOT / plan / 'artifacts/README.md')
    errors, links = [], 0
    for path in documents:
        body = path.read_text()
        for line, text in enumerate(body.splitlines(), 1):
            if text.rstrip() != text:
                errors.append(f'{path.relative_to(ROOT)}:{line}: trailing whitespace')
        for href in checker.links(body):
            url = urlsplit(href)
            if url.scheme or url.netloc:
                continue
            links += 1
            target = (path.parent / unquote(url.path)).resolve() if url.path else path
            if not target.exists():
                errors.append(f'{path.relative_to(ROOT)}: missing {href}')
            elif url.fragment and target.is_file() and target.suffix == '.md':
                if unquote(url.fragment) not in checker.anchors(target.read_text()):
                    errors.append(f'{path.relative_to(ROOT)}: missing anchor {href}')
    save(output / 'workflow-docs.json', {
        'files': [str(path.relative_to(ROOT)) for path in documents],
        'documents': len(documents), 'links': links, 'errors': errors,
    })
    sources = [ROOT / name for name in run('source-files', ['rg', '--files', 'src']).stdout.splitlines()]
    proof_inputs = sources + documents + list((ROOT / ISSUE).rglob('*.php')) + [
        ROOT / ISSUE / 'upstream-observations.json', Path(__file__).resolve(),
        ROOT / P32 / 'artifacts/legacy-typing-probe.php',
        ROOT / 'tests/Support/check-docs.py', ROOT / 'docs/reference/testing/live.md',
    ]
    save(output / 'inputs-sha256.json', {
        str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest()
        for path in sorted(set(proof_inputs))
    })
    required = [source_diff, probe, legacy, lint, style, tests, public_docs, whitespace]
    passed = all(item.returncode == 0 for item in required) and observations_equal and not errors
    passed = passed and len(trees) == 2 and trees[0] == trees[1] and verification.returncode == expected_verify_code
    report = {
        'passed': passed, 'git_head': head, 'issue_reference': REFERENCE,
        'upstream_observations_equal': observations_equal,
        'legacy_cases_verified': json.loads(legacy.stdout)['verified'],
        'workflow_documents': len(documents), 'workflow_links': links,
        'migration_test_files': sum(name.endswith('Test.php') for name in inventory),
        'migration_support_files': sum(name.startswith('tests/Support/') for name in inventory),
        'note': 'Проверка фактуры до реализации; не прохождение D01–D36/C01–C26.',
    }
    save(output / 'report.json', report)
    (output / 'SHA256SUMS').write_text(''.join(
        hashlib.sha256(path.read_bytes()).hexdigest() + '  ' + path.name + '\n'
        for path in sorted(output.iterdir()) if path.is_file() and path.name != 'SHA256SUMS'
    ))
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0 if passed else 1


if __name__ == '__main__':
    raise SystemExit(main())
