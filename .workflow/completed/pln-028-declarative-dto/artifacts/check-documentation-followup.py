"""Локальные ссылки и якоря публичной документации после пересмотра 028/030/031."""

import json
from pathlib import Path
import re
from urllib.parse import unquote, urlsplit

ARTIFACTS = Path(__file__).resolve().parent
ROOT = ARTIFACTS.parents[3]
files = [ROOT / 'README.md', ROOT / 'CHANEGLOG.md', *sorted((ROOT / 'docs').rglob('*.md'))]
files.append(ARTIFACTS.parent / 'documentation-followup.md')


def anchors(path):
    text = re.sub(r"^```.*?^```[^\n]*", "", path.read_text(), flags=re.M | re.S)
    result = set(re.findall(r'\b(?:id|name)=["\']([^"\']+)', text))
    counts = {}
    for heading in re.findall(r"^#{1,6}\s+(.+)$", text, re.M):
        slug = re.sub(r"[^\w\- ]", "", heading.strip().lower()).replace(" ", "-")
        duplicate = counts.get(slug, 0)
        result.add(slug if duplicate == 0 else f"{slug}-{duplicate}")
        counts[slug] = duplicate + 1
    return result


errors = []
count = 0
for path in files:
    for target in re.findall(r"\[[^\]\n]+\]\(([^)\n]+)\)", path.read_text()):
        parsed = urlsplit(target.strip("<>"))
        if parsed.scheme or parsed.netloc:
            continue
        destination = (path.parent / unquote(parsed.path)).resolve() if parsed.path else path
        count += 1
        if not destination.exists():
            errors.append(f"{path.relative_to(ROOT)}: {target}")
        elif parsed.fragment and destination.suffix == ".md" and unquote(parsed.fragment) not in anchors(destination):
            errors.append(f"{path.relative_to(ROOT)}: якорь {target}")
    for line, text in enumerate(path.read_text().splitlines(), 1):
        if ".workflow" in path.parts and text != text.rstrip():
            errors.append(f"{path.relative_to(ROOT)}:{line}: концевые пробелы")

result = {"documents": len(files), "local_links": count, "errors": errors}
print(json.dumps(result, indent=2, ensure_ascii=False))
raise SystemExit(bool(errors))
