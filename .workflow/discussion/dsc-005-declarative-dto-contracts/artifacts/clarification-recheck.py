"""Третья перепроверка: исходные два набора доказательств остаются неизменными."""

import hashlib
import json
from pathlib import Path
import subprocess

ARTIFACTS = Path(__file__).resolve().parent
ROOT = ARTIFACTS.parents[3]


def run(args):
    return subprocess.run(args, cwd=ROOT, capture_output=True, check=True, timeout=60).stdout


old_counts = {}
for name, count in [("review-probe", 20), ("followup-probe", 10)]:
    actual = run(["php", str(ARTIFACTS / (name + ".php"))])
    assert actual == (ARTIFACTS / (name + ".json")).read_bytes()
    checks = json.loads(actual)["checks"]
    assert len(checks) == count and all(checks.values())
    old_counts[name] = {"byte_identical": True, "checks": count}

actual = run(["php", str(ARTIFACTS / "clarification-probe.php")])
checks = json.loads(actual)["checks"]
assert len(checks) == 14 and all(checks.values())
(ARTIFACTS / "clarification-probe.json").write_bytes(actual)

sources = [ARTIFACTS / "clarification-probe.php", ARTIFACTS / "Fixtures/DefaultFinalDto.php"]
lint = b"".join(run(["php", "-l", str(path)]) for path in sources)
(ARTIFACTS / "clarification-lint.log").write_bytes(lint)
test_output = run([
    "vendor/bin/pest", "tests/Unit/Result/ResultHandleContinuationAwaitTest.php",
    "tests/Unit/Execution/CompositeFlowTest.php", "--compact",
])
# Убираем только концевые пробелы и пустые строки для git diff --check.
test_log = "\n".join(line.rstrip() for line in test_output.decode().splitlines()).strip() + "\n"
assert "9 passed" in test_log and "21 assertions" in test_log
(ARTIFACTS / "clarification-continuation-composite.log").write_text(test_log)
source_check = run(["sha256sum", "-c", str(ARTIFACTS / "reviewed-source.sha256")])
accepted_check = run(["sha256sum", "-c", ".workflow/completed/pln-029-hydration-defects/artifacts/sha256.txt"])

sources.extend([
    Path(__file__).resolve(), ARTIFACTS / "Fixtures/ExtraDto.php",
    ROOT / "src/Serialization/Hydrator.php", ROOT / "src/Attributes/AttributeMetadataCache.php",
    ROOT / "src/Core/AbstractClient.php", ROOT / "src/Continuation/ContinuationService.php",
    ROOT / "src/Result/ResultHandle.php", ROOT / "src/DataTransfer/AbstractDto.php",
    ROOT / "src/DataTransfer/AbstractResponseDto.php",
    ROOT / "tests/Stubs/Requests/ContinuationStartRequest.php",
])
state = {
    "head": run(["git", "rev-parse", "HEAD"]).decode().strip(),
    "php": run(["php", "-r", "echo PHP_VERSION;"]).decode(),
    "old_probes": old_counts,
    "clarification_checks": len(checks),
    "reviewed_sources_verified": len(source_check.splitlines()),
    "029_files_verified": len(accepted_check.splitlines()),
    "tests": {"passed": 9, "assertions": 21},
    "source_sha256": {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for p in sources},
}
(ARTIFACTS / "clarification-state.json").write_text(json.dumps(state, indent=2) + "\n")
print(json.dumps({key: value for key, value in state.items() if key != "source_sha256"}, indent=2))
