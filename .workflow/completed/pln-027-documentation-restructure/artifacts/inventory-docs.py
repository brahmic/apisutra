"""Снимок публичной документации указанного commit без изменения рабочего дерева."""

import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[4]


def git(*args):
    return subprocess.check_output(["git", *args], cwd=ROOT)


ref = git("rev-parse", sys.argv[1] if len(sys.argv) > 1 else "HEAD").decode().strip()
paths = git("ls-tree", "-r", "--name-only", ref).decode().splitlines()
public = sorted(path for path in paths if path in ("README.md", "CHANEGLOG.md")
                or path.startswith("docs/") and path.endswith(".md"))
files = []
for path in public:
    raw = git("show", f"{ref}:{path}")
    text = raw.decode()
    files.append({
        "path": path,
        "lines": len(text.splitlines()),
        "bytes": len(raw),
        "sha256": hashlib.sha256(raw).hexdigest(),
        "sections": re.findall(r"^## (.+)$", text, re.M),
    })

sources = [
    ".agents/documentation.md", ".agents/providers.md", "composer.json", ".gitattributes",
    "src/Core/AbstractClient.php", "src/Pipeline/Pipeline.php",
    "src/VO/Validation/Validator.php", "src/Serialization/Hydrator.php",
    "tests/Support/check-docs.py", "tests/Support/check-package.py",
    "tests/Support/standalone-hydration-rules-smoke.php",
]
result = {
    "commit": ref,
    "php_version": subprocess.check_output(["php", "-r", "echo PHP_VERSION;"], cwd=ROOT).decode(),
    "summary": {
        "documents": len(files),
        "lines": sum(item["lines"] for item in files),
        "over_300_lines": sum(item["lines"] > 300 for item in files),
        "over_400_lines": sum(item["lines"] > 400 for item in files),
    },
    "files": files,
    "source_sha256": {path: hashlib.sha256(git("show", f"{ref}:{path}")).hexdigest()
                      for path in sources},
    "attribute_scanner_source_files": [path for path in paths
                                       if path.startswith("src/") and path.endswith("/AttributeScanner.php")],
}
print(json.dumps(result, ensure_ascii=False, indent=2))
