"""Проверка второго ответа коллеги без изменения доказательств первой рецензии."""

import hashlib
import json
from pathlib import Path
import subprocess

ARTIFACTS = Path(__file__).resolve().parent
ROOT = ARTIFACTS.parents[3]


def run(args):
    return subprocess.run(args, cwd=ROOT, capture_output=True, check=True, timeout=60).stdout


first = run(["php", str(ARTIFACTS / "review-probe.php")])
assert first == (ARTIFACTS / "review-probe.json").read_bytes()
assert len(json.loads(first)["checks"]) == 20
second = run(["php", str(ARTIFACTS / "followup-probe.php")])
data = json.loads(second)
assert all(data["checks"].values())
(ARTIFACTS / "followup-probe.json").write_bytes(second)
run(["php", "-l", str(ARTIFACTS / "followup-probe.php")])
old_sources = run(["sha256sum", "-c", str(ARTIFACTS / "reviewed-source.sha256")])
accepted = run(["sha256sum", "-c", ".workflow/completed/pln-029-hydration-defects/artifacts/sha256.txt"])
state = {
    "head": run(["git", "rev-parse", "HEAD"]).decode().strip(),
    "php": run(["php", "-r", "echo PHP_VERSION;"]).decode(),
    "original_probe_identical": True,
    "original_probe_checks": 20,
    "followup_checks": len(data["checks"]),
    "reviewed_sources_verified": len(old_sources.splitlines()),
    "029_files_verified": len(accepted.splitlines()),
}
sources = [ARTIFACTS / "followup-probe.php", Path(__file__).resolve(), ARTIFACTS / "Fixtures/ExtraDto.php"]
state["artifact_sha256"] = {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for p in sources}
(ARTIFACTS / "followup-state.json").write_text(json.dumps(state, indent=2) + "\n")
print(json.dumps(state, indent=2))
