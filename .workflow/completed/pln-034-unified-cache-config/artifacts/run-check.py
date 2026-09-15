#!/usr/bin/env python3
"""Сохраняет команду проверки, код завершения и полный вывод рядом со скриптом."""
import datetime
import json
import re
from pathlib import Path
import subprocess
import sys
import time

label, *command = sys.argv[1:]
base = Path(__file__).resolve().parent
started = time.monotonic()
with (base / (label + '.stdout')).open('w') as out, (base / (label + '.stderr')).open('w') as err:
    result = subprocess.run(command, stdout=out, stderr=err)
for suffix in ['.stdout', '.stderr']:
    log = base / (label + suffix)
    raw = re.sub(r'\x1b\[[0-9;]*m', '', log.read_text())
    normalized = '\n'.join(line.rstrip() for line in raw.splitlines()).rstrip()
    log.write_text(normalized + ('\n' if normalized else ''))
report = {'log_normalization': 'strip ANSI colors, trailing spaces and empty EOF lines', 'command': command, 'cwd': 'repository root', 'exit_code': result.returncode,
          'seconds': round(time.monotonic() - started, 3),
          'time_utc': datetime.datetime.now(datetime.timezone.utc).isoformat()}
(base / (label + '.json')).write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps(report))
print((base / (label + '.stdout')).read_text()[-2200:])
print((base / (label + '.stderr')).read_text()[-1200:])
sys.exit(result.returncode)
