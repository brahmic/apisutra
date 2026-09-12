"""Проверка подсчёта сценариев и отображения неполных/неуспешных прогонов."""

from __future__ import annotations

import importlib.util
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest
import xml.etree.ElementTree as ET

spec = importlib.util.spec_from_file_location('badge', Path(__file__).with_name('test-count-badge.py'))
badge = importlib.util.module_from_spec(spec)
spec.loader.exec_module(badge)


class TestCountBadgeTest(unittest.TestCase):
    def render(self, xml: str | None, outcome: str = 'success') -> dict:
        with TemporaryDirectory() as temporary:
            report = Path(temporary) / 'junit.xml'
            if xml is not None:
                report.write_text(xml)
            return badge.build_badge(report, outcome)

    def test_nested_suites_and_datasets_are_counted_once(self):
        result = self.render('''<testsuites><testsuite tests="2"><testsuite tests="2">
            <testcase name="dataset 1"/><testcase name="dataset 2"/>
            </testsuite></testsuite></testsuites>''')
        self.assertEqual(result['message'], '2 passed')
        self.assertEqual(result['color'], 'brightgreen')

    def test_failures_errors_and_skips_are_not_passes(self):
        result = self.render('''<testsuite><testcase/><testcase><failure/></testcase>
            <testcase><error/></testcase><testcase><skipped/></testcase></testsuite>''', 'failure')
        self.assertEqual(result['message'], '1 passed, 2 failed, 1 skipped')
        self.assertEqual(result['color'], 'red')

    def test_skipped_run_is_yellow(self):
        self.assertEqual(self.render('<testsuite><testcase><skipped/></testcase></testsuite>')['color'], 'yellow')

    def test_diagnostics_can_fail_a_run_with_passing_cases(self):
        result = self.render('<testsuite><testcase/></testsuite>', 'failure')
        self.assertEqual(result['message'], '1 passed, run failed')
        self.assertEqual(result['color'], 'red')

    def test_missing_or_empty_report_is_unavailable(self):
        for xml in [None, '<testsuites/>']:
            with self.subTest(xml=xml):
                result = self.render(xml, 'failure')
                self.assertEqual(result['message'], 'unavailable')
                self.assertEqual(result['color'], 'lightgrey')

    def test_malformed_report_is_rejected(self):
        with self.assertRaises(ET.ParseError):
            self.render('<broken')

    def test_truncated_utf8_dataset_name_does_not_break_counting(self):
        with TemporaryDirectory() as temporary:
            report = Path(temporary) / 'junit.xml'
            report.write_bytes(b'<testsuite name="dataset \xd0"><testcase/></testsuite>')
            self.assertEqual(badge.build_badge(report, 'success')['message'], '1 passed')


if __name__ == '__main__':
    unittest.main()
