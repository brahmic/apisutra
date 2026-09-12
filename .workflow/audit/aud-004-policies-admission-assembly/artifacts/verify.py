#!/usr/bin/env python3
"""Повторная проверка существующего поведения; новые API политик не реализует."""

import hashlib
import json
from pathlib import Path
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[4]
ARTIFACTS = Path(__file__).resolve().parent
TESTS = [
    "tests/Unit/Timing/TimeoutContractTest.php",
    "tests/Unit/Retry/RetryConfigResolverTest.php",
    "tests/Unit/Retry/SafeRetryContractTest.php",
    "tests/Unit/RateLimit/RateLimitStateTest.php",
    "tests/Unit/RateLimit/RateLimitExecutionTest.php",
    "tests/Unit/Core/ClientConfigTest.php",
    "tests/Unit/Core/TransportTestingTest.php",
    "tests/Unit/Diagnostics/RedactionPolicyTest.php",
]
SOURCES = [
    "src/Config/ClientConfig.php",
    "src/Core/AbstractClient.php",
    "src/Pipeline/Pipeline.php",
    "src/Pipeline/Preparation/TimeoutResolver.php",
    "src/Pipeline/Transport/RetryConfigResolver.php",
    "src/Pipeline/Transport/RetrySender.php",
    "src/Pipeline/Transport/RateLimitApplier.php",
    "src/Pipeline/Diagnostics/AuditLogger.php",
    "src/Pipeline/Flow/ExecutionResultBuilder.php",
    "src/Pipeline/Cache/CacheManager.php",
    "src/Pipeline/Hydration/ResponseHydrator.php",
    "src/Pagination/Paginator.php",
    "src/Execution/BatchExecutor.php",
    "src/Execution/PoolExecutor.php",
]


def main() -> int:
    php = sys.argv[1] if len(sys.argv) > 1 else "php"
    command = [php, "vendor/bin/pest", *TESTS, "--compact"]
    run = subprocess.run(command, cwd=ROOT, text=True, capture_output=True, check=False)
    (ARTIFACTS / "baseline-tests.log").write_text((run.stdout + run.stderr).rstrip() + "\n")
    inventory = subprocess.run(
        [php, "-r", "require 'vendor/autoload.php'; "
         "$r = new ReflectionClass(Brahmic\\ApiSutra\\Config\\ClientConfig::class); "
         "echo json_encode(array_map(fn ($p) => $p->getName(), "
         "$r->getConstructor()->getParameters()));"],
        cwd=ROOT, text=True, capture_output=True, check=True,
    )
    report = {
        "scope": "Existing behavior only; proposed contracts require implementation tests",
        "head": subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip(),
        "php": subprocess.check_output([php, "-r", "echo PHP_VERSION;"], cwd=ROOT, text=True),
        "command": command,
        "exit_code": run.returncode,
        "client_config_parameters": json.loads(inventory.stdout),
        "sha256": {
            path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest()
            for path in [*SOURCES, *TESTS]
        },
    }
    (ARTIFACTS / "baseline.json").write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
    print(run.stdout[-1800:])
    print(f"Report: {ARTIFACTS / 'baseline.json'}")
    return run.returncode


if __name__ == "__main__":
    raise SystemExit(main())
