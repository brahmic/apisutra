#!/usr/bin/env python3
"""Проверка ссылок и статуса комплекта плана, без запуска runtime-проверок."""

import json
from pathlib import Path
import re


FOLDER = Path(__file__).resolve().parent.parent
ROOT = FOLDER.parents[2]
FILES = [
    *sorted(FOLDER.glob("*.md")),
    ROOT / ".workflow/README.md",
    ROOT / ".workflow/backlog/pln-015-shared-rate-limit-coordination.md",
]
errors = []
links = 0
for path in FILES:
    content = path.read_text()
    for target in re.findall(r"\[[^\]]*\]\(([^)]+)\)", content):
        if target.startswith(("https://", "http://", "#")):
            continue
        target = target.split("#", 1)[0]
        links += 1
        if not (path.parent / target).exists():
            errors.append(f"Missing target: {path.name} -> {target}")
    for number, line in enumerate(content.splitlines(), 1):
        if line.rstrip() != line:
            errors.append(f"Trailing whitespace: {path.name}:{number}")

main = (FOLDER / "pln-025-readme.md").read_text()
for item in ["- Статус: завершён", "- Дата создания: 2026-09-13", "- Дата обновления: 2026-09-13"]:
    if item not in main:
        errors.append(f"Missing plan metadata: {item}")
if FOLDER.parent.name != "completed":
    errors.append("Unexpected plan directory")

report = {"files": len(FILES), "local_links": links, "errors": errors}
(FOLDER / "artifacts/document-check.json").write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
print(json.dumps(report, ensure_ascii=False, indent=2))
raise SystemExit(1 if errors else 0)
