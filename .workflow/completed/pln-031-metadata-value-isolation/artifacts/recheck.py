"""Baseline CR-01: новые наблюдения и проверка неизменности прежних доказательств."""

import hashlib
import json
from pathlib import Path
import re
import subprocess

ARTIFACTS = Path(__file__).resolve().parent
ROOT = ARTIFACTS.parents[3]
PREVIOUS = ROOT / ".workflow/discussion/dsc-005-declarative-dto-contracts/artifacts"


def run(args):
    return subprocess.run(args, cwd=ROOT, capture_output=True, check=True, timeout=60).stdout


old_probes = {}
for name, count in [("review-probe", 20), ("followup-probe", 10), ("clarification-probe", 14)]:
    actual = run(["php", str(PREVIOUS / (name + ".php"))])
    assert actual == (PREVIOUS / (name + ".json")).read_bytes()
    checks = json.loads(actual)["checks"]
    assert len(checks) == count and all(checks.values())
    old_probes[name] = {"byte_identical": True, "checks": count}

previous_hashes = json.loads((PREVIOUS / "clarification-state.json").read_text())["source_sha256"]
for path, expected in previous_hashes.items():
    assert hashlib.sha256((ROOT / path).read_bytes()).hexdigest() == expected, path
reviewed = run(["sha256sum", "-c", str(PREVIOUS / "reviewed-source.sha256")])
accepted = run(["sha256sum", "-c", ".workflow/completed/pln-029-hydration-defects/artifacts/sha256.txt"])

actual = run(["php", str(ARTIFACTS / "metadata-probe.php")])
checks = json.loads(actual)["checks"]
assert len(checks) == 33 and all(checks.values())
(ARTIFACTS / "metadata-probe.json").write_bytes(actual)
php_files = sorted(ARTIFACTS.rglob("*.php"))
(ARTIFACTS / "php-lint.log").write_bytes(b"".join(run(["php", "-l", str(path)]) for path in php_files))
test_output = run([
    "vendor/bin/pest", "tests/Unit/Serialization",
    "tests/Unit/Result/ResultHandleContinuationAwaitTest.php",
    "tests/Unit/Execution/CompositeFlowTest.php", "--compact",
]).decode()
test_log = "\n".join(line.rstrip() for line in test_output.splitlines()).strip() + "\n"
assert "433 passed" in test_log and "1385 assertions" in test_log
(ARTIFACTS / "tests.log").write_text(test_log)
sources = sorted(set(php_files + [Path(__file__).resolve()] + [ROOT / path for path in [
    "src/Config/ClientConfig.php", "src/Core/AbstractClient.php", "src/Attributes/AttributeMetadataCache.php",
    "src/Attributes/AttributeRegistry.php", "src/Attributes/DataTransfer/Cast.php",
    "src/Attributes/DataTransfer/DefaultValue.php", "src/Serialization/Hydrator.php",
    "src/Serialization/DtoSerializer.php", "src/Serialization/RequestPartsCollector.php",
    "src/Serialization/Serializer.php", "src/Serialization/Concerns/ReflectionHelperTrait.php",
    "src/Serialization/SerializationValueResolver.php", "src/Serialization/BuiltinHydrationCaster.php",
    "src/Resolver/ClientRegistry.php", "src/Laravel/SdkServiceProvider.php",
    "src/Pipeline/Hydration/ResponseHydrator.php", "src/Pipeline/Execution/CompositeFlow.php",
    "src/DataTransfer/AbstractDto.php", "src/DataTransfer/AbstractPaginationContainerDto.php",
    "tests/Stubs/Dto/PaginationContainerDto.php", "tests/Stubs/TestClient.php", "composer.lock",
]]))
state = {
    "head": run(["git", "rev-parse", "HEAD"]).decode().strip(),
    "php": run(["php", "-r", "echo PHP_VERSION;"]).decode(),
    "old_probes": old_probes,
    "previous_hashes_verified": len(previous_hashes),
    "reviewed_sources_verified": len(reviewed.splitlines()),
    "029_files_verified": len(accepted.splitlines()),
    "metadata_checks": len(checks),
    "php_files_linted": len(php_files),
    "tests": {"passed": 433, "assertions": 1385},
    "source_sha256": {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for p in sources},
}
(ARTIFACTS / "baseline-state.json").write_text(json.dumps(state, indent=2) + "\n")
print(json.dumps({k: v for k, v in state.items() if k != "source_sha256"}, indent=2))
