#!/usr/bin/env python3
"""Отделяет существующие нарушения PSR-12 от новых по файлу и правилу."""
import argparse
import collections
import json
import subprocess
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
if args.output.exists():
    raise FileExistsError(args.output)
out = Path(__file__).resolve().parent / 'implementation'
files = json.loads((out / 'commands.json').read_text())['style']['command'][3:]
records = []
new_errors = []
for file in files:
    old = subprocess.run(['git', 'show', '3ff9531:' + file], capture_output=True, text=True)
    baseline = {'totals': {'errors': 0}, 'files': {}}
    if old.returncode == 0:
        before = subprocess.run(['vendor/bin/phpcs', '-n', '--report=json', '--stdin-path=' + file, '-'], input=old.stdout, capture_output=True, text=True)
        baseline = json.loads(before.stdout)
    after = subprocess.run(['vendor/bin/phpcs', '-n', '--report=json', file], capture_output=True, text=True)
    current = json.loads(after.stdout)
    def errors(report):
        return collections.Counter(message['source'] for item in report['files'].values() for message in item['messages'] if message['type'] == 'ERROR')
    extra = errors(current) - errors(baseline)
    if extra:
        new_errors.append({'file': file, 'rules': dict(extra)})
    records.append({'file': file, 'before': baseline, 'after': current})
args.output.write_text(json.dumps({'baseline': '3ff9531', 'new_errors': new_errors, 'files': records}, indent=2) + '\n')
print('PSR-12: новых нарушений', len(new_errors))
print('Ошибок до:', sum(x['before']['totals']['errors'] for x in records), 'после:', sum(x['after']['totals']['errors'] for x in records))
raise SystemExit(bool(new_errors))
