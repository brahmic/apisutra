"""Проверка подготовки 027: baseline, карта файлов, проект дерева и локальные ссылки."""

import hashlib
import json
from pathlib import Path
import posixpath
import re
import subprocess
from urllib.parse import unquote, urlsplit

ARTIFACTS = Path(__file__).resolve().parent
PLAN = ARTIFACTS.parent
ROOT = ARTIFACTS.parents[3]
baseline = json.loads((ARTIFACTS / "baseline.json").read_text())
errors = []

# Контрольные суммы проверяют принятый commit, а не новое содержимое документации.
hashes = {item["path"]: item["sha256"] for item in baseline["files"]}
hashes.update(baseline["source_sha256"])
for path, expected in hashes.items():
    raw = subprocess.check_output(["git", "show", f"{baseline['commit']}:{path}"], cwd=ROOT)
    if hashlib.sha256(raw).hexdigest() != expected:
        errors.append(f"baseline SHA-256: {path}")

block = re.search(r"```text\n(.*?)\n```", (PLAN / "structure.md").read_text(), re.S).group(1)
stack = []
targets = set()
for line in block.splitlines():
    depth = (len(line) - len(line.lstrip())) // 2
    stack = stack[:depth]
    name = line.strip()
    if name.endswith("/"):
        stack.append(name[:-1])
    else:
        path = "/".join(stack + [name])
        if path in targets:
            errors.append(f"Повтор цели: {path}")
        targets.add(path)

# Разработка ApiSutra отделена от пользовательского комплекта и его agent-входа.
developer_targets = {path for path in targets
                     if path == "CONTRIBUTING.md" or path.startswith("development/")}
user_targets = targets - developer_targets
if "CONTRIBUTING.md" not in developer_targets or "development/README.md" not in developer_targets:
    errors.append("Нет отдельного входа для разработчика ApiSutra")
if "docs/start/agent.md" not in user_targets:
    errors.append("Нет входа агента в общие пользовательские маршруты")
if "docs/start/contribute.md" in targets:
    errors.append("Разработка ядра осталась в пользовательских маршрутах")
if any(path.startswith(("docs/ai/", "docs/development/", "docs/technical/")) for path in targets):
    errors.append("Проект дерева смешивает аудитории или создаёт отдельную документацию для ИИ")

rows = re.findall(r"^\| `([^`]+)` \| (.+?) \| .+ \|$", (PLAN / "content-map.md").read_text(), re.M)
mapped = []
for source, destinations in rows:
    mapped.append(posixpath.normpath("docs/" + source))
    for destination in re.findall(r"`([^`]+)`", destinations):
        target = posixpath.normpath("docs/" + destination)
        if target not in targets:
            errors.append(f"Назначение вне дерева: {source} -> {target}")
expected = {item["path"] for item in baseline["files"]}
if set(mapped) != expected:
    errors.append(f"Карта не совпадает с baseline: {sorted(set(mapped) ^ expected)}")
if len(mapped) != len(set(mapped)):
    errors.append("Исходный файл повторяется в карте")


def anchors(path):
    text = re.sub(r"^```.*?^```[^\n]*", "", path.read_text(), flags=re.M | re.S)
    found = set(re.findall(r'\b(?:id|name)=["\']([^"\']+)', text))
    counts = {}
    for title in re.findall(r"^#{1,6}\s+(.+)$", text, re.M):
        slug = re.sub(r"[^\w\- ]", "", title.strip().lower()).replace(" ", "-")
        number = counts.get(slug, 0)
        found.add(slug if not number else f"{slug}-{number}")
        counts[slug] = number + 1
    return found


files = sorted(PLAN.glob("*.md"))
links = 0
for path in files:
    for value in re.findall(r"\[[^\]\n]+\]\(([^)\n]+)\)", path.read_text()):
        parsed = urlsplit(value.strip("<>"))
        if parsed.scheme or parsed.netloc:
            continue
        destination = (path.parent / unquote(parsed.path)).resolve() if parsed.path else path
        links += 1
        if not destination.exists():
            errors.append(f"{path.name}: отсутствует {value}")
        elif parsed.fragment and destination.suffix == ".md" and unquote(parsed.fragment) not in anchors(destination):
            errors.append(f"{path.name}: отсутствует якорь {value}")
    for number, line in enumerate(path.read_text().splitlines(), 1):
        if line != line.rstrip():
            errors.append(f"{path.name}:{number}: концевые пробелы")

print(json.dumps({
    "planning_revision": "audience-separation-2026-09-15",
    "baseline_commit": baseline["commit"],
    "verified_sha256": len(hashes),
    "mapped_source_files": len(mapped),
    "planned_markdown_files_without_redirects": len(targets),
    "planned_user_documents": len(user_targets),
    "planned_developer_documents": len(developer_targets),
    "plan_documents": len(files),
    "local_links": links,
    "errors": errors,
}, ensure_ascii=False, indent=2))
raise SystemExit(bool(errors))
