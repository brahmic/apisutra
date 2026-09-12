#!/usr/bin/env python3
"""Проверяет комплект подготовки без чтения issue и исторического архива."""

import json
from pathlib import Path
import re


ROOT = Path(__file__).resolve().parents[4]
AUDIT = Path(__file__).resolve().parent.parent
FILES = [
    ROOT / ".workflow/README.md",
    ROOT / ".workflow/discussion/dsc-002-architecture-directions.md",
    ROOT / ".workflow/discussion/dsc-003-client-assembly-factory.md",
    ROOT / ".workflow/discussion/dsc-004-client-policies-and-assembly.md",
    *sorted(AUDIT.glob("*.md")),
    ROOT / ".workflow/backlog/pln-022-client-assembly.md",
    ROOT / ".workflow/backlog/pln-023-execution-admission.md",
    ROOT / ".workflow/backlog/pln-024-execution-policies.md",
]


def main() -> None:
    errors = []
    links = 0
    for path in FILES:
        source = path.read_text()
        for number, line in enumerate(source.splitlines(), start=1):
            if line.rstrip() != line:
                errors.append(f"Trailing whitespace: {path.name}:{number}")
        for target in re.findall(r"\[[^\]]*\]\(([^)]+)\)", source):
            if "://" in target or target.startswith("#"):
                continue
            target = target.split("#", 1)[0]
            if not target:
                continue
            links += 1
            if not (path.parent / target).exists():
                errors.append(f"Missing link: {path.relative_to(ROOT)} -> {target}")
        if path.name.startswith(("aud-", "pln-", "dsc-")):
            for label in ["Дата создания", "Дата обновления"]:
                if re.search(r"^- " + label + r": \d{4}-\d{2}-\d{2}$", source, re.MULTILINE) is None:
                    errors.append(f"Missing metadata: {path.name}: {label}")
        if path.name.startswith("pln-"):
            if "- Статус: отложен" not in source or path.parent.name != "backlog":
                errors.append(f"Unexpected plan status/location: {path.name}")

    baseline = json.loads((AUDIT / "artifacts/baseline.json").read_text())
    table = (AUDIT / "configuration-model.md").read_text().split("## Инвентаризация", 1)[1]
    table = table.split("Новые кандидаты", 1)[0]
    for field in baseline["client_config_parameters"]:
        if re.search(r"\b" + re.escape(field) + r"\b", table) is None:
            errors.append(f"Missing config field: {field}")

    result = {
        "documents": len(FILES),
        "local_links": links,
        "config_parameters": len(baseline["client_config_parameters"]),
        "errors": errors,
        "scope": "File targets, metadata and parameter coverage; proposed API is not executable yet",
    }
    (AUDIT / "artifacts/document-check.json").write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if errors:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
