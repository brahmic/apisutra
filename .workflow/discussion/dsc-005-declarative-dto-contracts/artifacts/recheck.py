"""Повторяет проверки рецензии; запускается из любого рабочего каталога."""

import hashlib
import json
from pathlib import Path
import subprocess

ARTIFACTS = Path(__file__).resolve().parent
ROOT = ARTIFACTS.parents[3]


def run(args, output, expected=0):
    result = subprocess.run(args, cwd=ROOT, capture_output=True, timeout=60)
    if output.endswith(".log"):
        # Убираем только концевые пробелы и пустые строки лога, не меняя результаты.
        clean = "\n".join(line.rstrip() for line in result.stdout.decode().splitlines()).rstrip() + "\n"
        (ARTIFACTS / output).write_text(clean)
    else:
        (ARTIFACTS / output).write_bytes(result.stdout)
    if result.stderr:
        print(result.stderr.decode(), end="")
    if result.returncode != expected:
        raise RuntimeError(f"{args}: exit {result.returncode}, expected {expected}")
    return result.stdout


serialization = run(
    ["vendor/bin/pest", "tests/Unit/Serialization", "--compact"], "serialization.log"
)
continuation = run(
    ["vendor/bin/pest", "tests/Unit/Result/ResultHandleContinuationAwaitTest.php",
     "tests/Unit/Execution/CompositeFlowTest.php", "--compact"], "continuation-composite.log"
)
standalone = run(["php", "tests/Support/hydration-standalone.php"], "standalone.json")
peer = run(
    ["php", ".workflow/audit/aud-006-declarative-dto/artifacts/peer-review-probe.php"],
    "peer-review.json", expected=1,
)
assert peer == (ROOT / ".workflow/completed/pln-029-hydration-defects/artifacts/peer-review-after.json").read_bytes()
manifest = run(
    ["sha256sum", "-c", ".workflow/completed/pln-029-hydration-defects/artifacts/sha256.txt"],
    "029-sha256.log",
)
probe = json.loads(run(["php", str(ARTIFACTS / "review-probe.php")], "review-probe.json"))
assert all(probe["checks"].values())
numbers = json.loads(run(
    ["node", "-e", "process.stdout.write(JSON.stringify({node:process.version,encoded:JSON.stringify(10.0)})+'\\n')"],
    "javascript-number.json",
))
assert numbers["encoded"] == "10"
lint = []
for path in sorted(ARTIFACTS.rglob("*.php")):
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, capture_output=True, check=True)
    lint.append(result.stdout.decode().replace(str(ROOT) + "/", ""))
(ARTIFACTS / "php-lint.log").write_text("".join(lint))
state = {
    "head": subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT).decode().strip(),
    "php": subprocess.check_output(["php", "-r", "echo PHP_VERSION;"], cwd=ROOT).decode(),
    "composer_lock_sha256": hashlib.sha256((ROOT / "composer.lock").read_bytes()).hexdigest(),
    "probe_checks": len(probe["checks"]),
    "peer_review_equals_029": True,
    "verified_029_files": len(manifest.splitlines()),
    "standalone_equals_readiness": standalone == (ROOT / ".workflow/current/pln-028-declarative-dto/artifacts/readiness-standalone.json").read_bytes(),
    "node": numbers["node"],
}
assert state["standalone_equals_readiness"]
(ARTIFACTS / "recheck-state.json").write_text(json.dumps(state, indent=2) + "\n")
sources = [
    "src/Core/AbstractClient.php", "src/Config/ClientConfig.php",
    "src/Continuation/ContinuationService.php", "src/Result/ResultHandle.php",
    "src/Pipeline/Pipeline.php", "src/Pipeline/Execution/CompositeFlow.php",
    "src/Pipeline/Hydration/ResponseHydrator.php", "src/Pipeline/Cache/CacheManager.php",
    "src/Pipeline/Flow/ExecutionResultBuilder.php", "src/VO/Pipeline/PipelineContext.php",
    "src/Serialization/Hydrator.php", "src/Serialization/DtoSerializer.php",
    "src/Contracts/Interfaces/Casting/CastInterface.php",
    "src/Contracts/Interfaces/DataTransfer/DefaultValueProviderInterface.php",
    ".workflow/issue/iss-002-apisutra-reuse/upstream-requirements.md",
    ".workflow/audit/aud-006-declarative-dto/artifacts/Stubs/StrictListCast.php",
    ".workflow/audit/aud-006-declarative-dto/artifacts/Stubs/ReviewChildCast.php",
    ".workflow/audit/aud-006-declarative-dto/artifacts/Stubs/UnknownVariantCast.php",
]
sources += [str(path.relative_to(ROOT)) for path in sorted(ARTIFACTS.rglob("*.php"))]
sources += [str(Path(__file__).resolve().relative_to(ROOT))]
manifest_lines = [f"{hashlib.sha256((ROOT / path).read_bytes()).hexdigest()}  {path}" for path in sources]
(ARTIFACTS / "reviewed-source.sha256").write_text("\n".join(manifest_lines) + "\n")
print(serialization.decode().strip())
print(continuation.decode().strip())
print(json.dumps(state, indent=2))
