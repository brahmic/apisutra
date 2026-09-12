"""Формирование бейджа Shields.io из одного JUnit-отчёта PHPUnit/Pest."""

import json
from pathlib import Path
import sys
import xml.etree.ElementTree as ET


def build_badge(report: Path, outcome: str) -> dict:
    badge = {'schemaVersion': 1, 'label': 'tests', 'message': 'unavailable', 'color': 'lightgrey'}
    if not report.is_file():
        return badge

    # Вложенные testsuite повторяют итоговые счётчики; считаем только testcase.
    # Pest может обрезать русское имя dataset посреди UTF-8 символа.
    # Имена не участвуют в подсчёте; структуру XML по-прежнему проверяет парсер.
    root = ET.fromstring(report.read_text(encoding='utf-8', errors='replace'))
    cases = list(root.iter('testcase'))
    if not cases:
        return badge

    failed = sum(case.find('failure') is not None or case.find('error') is not None for case in cases)
    skipped = sum(case.find('skipped') is not None for case in cases)
    passed = len(cases) - failed - skipped
    parts = [f'{passed} passed']
    if failed:
        parts.append(f'{failed} failed')
    if skipped:
        parts.append(f'{skipped} skipped')
    if outcome != 'success' and not failed:
        parts.append('run failed')

    badge['message'] = ', '.join(parts)
    badge['color'] = 'red' if failed or outcome != 'success' else ('yellow' if skipped else 'brightgreen')
    return badge


if __name__ == '__main__':
    report, destination, outcome = sys.argv[1:]
    Path(destination).write_text(json.dumps(build_badge(Path(report), outcome), indent=2) + '\n')
