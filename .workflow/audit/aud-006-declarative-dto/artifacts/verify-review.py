#!/usr/bin/env python3
"""Проверка сохранённых доказательств и учтённых редакторских изменений источника."""

import hashlib
import json
from pathlib import Path
import re


artifacts = Path(__file__).resolve().parent
root = artifacts.parents[3]
edits = json.loads((artifacts / "editorial-changes.json").read_text())["files"]
edits_by_path = {item["old_path"]: item for item in edits}
results = []

for manifest, groups in (
    ("baseline.json", ("source_sha256", "artifact_sha256")),
    ("recheck-baseline.json", ("input_sha256", "artifact_sha256")),
):
    data = json.loads((artifacts / manifest).read_text())
    for group in groups:
        for name, expected in data[group].items():
            path = artifacts / name if group == "artifact_sha256" else root / name
            edit = edits_by_path.get(name) if group != "artifact_sha256" else None
            if edit is not None:
                path = root / edit["new_path"]
            actual = hashlib.sha256(path.read_bytes()).hexdigest() if path.exists() else None
            if actual == expected:
                status = "match"
            elif edit and expected == edit["before_sha256"] and actual == edit["after_sha256"]:
                status = "documented_editorial_change"
            elif name == ".workflow/issue/002.md" and actual is None:
                status = "documented_removed_source"
            else:
                status = "mismatch"
            results.append({"manifest": manifest, "path": name, "status": status})


def conditions(path: Path) -> dict[str, str]:
    # В обоих исходных скриптах verify-вызовы однострочные; сравниваем выражения,
    # а не сообщения наблюдений. Синтаксические пробелы несущественны.
    matches = re.findall(r"^[ \t]*verify\('(P\d+)', (.*), '[^\n]*'\);$", path.read_text(), re.MULTILINE)
    return {key: re.sub(r"\s+", "", expression) for key, expression in matches}


original = conditions(root / ".workflow/issue/iss-002-apisutra-reuse/artifacts/probe.php")
adapted = conditions(artifacts / "colleague-probe.php")
assert len(original) == 21 and len(adapted) == 20
assert set(original) - set(adapted) == {"P20"}
assert all(original[key] == value for key, value in adapted.items())

repeated = {}
for filename in ("probe-results.json", "colleague-results.json", "extensions-results.json"):
    repeated[filename] = (artifacts / filename).read_bytes() == (artifacts / ("review-" + filename)).read_bytes()

output = {
    "hashes": results,
    "counts": {status: sum(row["status"] == status for row in results) for status in (
        "match", "documented_editorial_change", "documented_removed_source", "mismatch"
    )},
    "identical_verify_conditions": len(adapted),
    "excluded_original_condition": "P20",
    "identical_repeated_outputs": repeated,
}
print(json.dumps(output, ensure_ascii=False, indent=2))
assert output["counts"]["mismatch"] == 0 and all(repeated.values())
