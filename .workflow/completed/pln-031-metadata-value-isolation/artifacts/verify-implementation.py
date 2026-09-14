"""Проверка исправления CR-01; исходные probes и baseline не переписываются."""

import hashlib
import json
from pathlib import Path
import subprocess

ARTIFACTS = Path(__file__).resolve().parent
ROOT = ARTIFACTS.parents[3]

probe = subprocess.run(["php", str(ARTIFACTS / "metadata-probe.php")], cwd=ROOT, capture_output=True, text=True, timeout=60)
assert probe.returncode in (0, 1), probe.stderr
result = json.loads(probe.stdout)
(ARTIFACTS / "implementation-after-probe.json").write_text(probe.stdout)
observed = result["observations"]
expected_cache = {
    "constructor_shared": False,
    "nested_constructor_shared": False,
    "attribute_default_second": 0,
    "hydrate_cast": [1, 1],
    "dto_serialize_cast": [1, 1],
    "request_query_cast": [1, 1],
    "wire_dto_cast": [1, 1],
}
checks = {name: observed[name] == expected_cache for name in ("cache_off", "cache_on")}
for environment in ("DefaultProduction", "Local", "Testing"):
    checks[environment] = observed[environment] == {
        "returns_second": 0,
        "pagination_items_shared": False,
        "pagination_cross_returns": 0,
        "composite_state": 0,
    }
checks["registry"] = observed["retained_client_second_task"] == 0
checks["client_casts"] = observed["default_client_casts"] == {
    "hydrate": [1, 1], "query": [1, 1], "body": [1, 1],
}
assert all(checks.values()), checks

commands = [
    (["vendor/bin/pest", "tests/Unit/Serialization/MetadataValueIsolationTest.php", "--compact"], "implementation-regressions.log"),
    (["composer", "test", "--", "--compact"], "implementation-tests.log"),
]
executions = []
for command, name in commands:
    run = subprocess.run(command, cwd=ROOT, capture_output=True, text=True, timeout=120)
    log = "\n".join(line.rstrip() for line in (run.stdout + run.stderr).splitlines()).strip() + "\n"
    (ARTIFACTS / name).write_text(log)
    executions.append({"command": command, "exit_code": run.returncode, "log": name})
    assert run.returncode == 0, log

sources = sorted((ROOT / "src/Serialization").rglob("*.php"))
sources += sorted((ROOT / "tests/Stubs/MetadataIsolation").glob("*.php"))
sources.append(ROOT / "tests/Unit/Serialization/MetadataValueIsolationTest.php")
state = {
    "head": subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip(),
    "php": subprocess.check_output(["php", "-r", "echo PHP_VERSION;"], text=True),
    "probe_exit_code": probe.returncode,
    "note": "Исходный probe ожидает дефект; проверяются вручную заданные исправленные observations, а не его checks.",
    "checks": checks,
    "executions": executions,
    "source_sha256": {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for p in sources},
}
(ARTIFACTS / "implementation-state.json").write_text(json.dumps(state, ensure_ascii=False, indent=2) + "\n")
print(json.dumps({k: v for k, v in state.items() if k != "source_sha256"}, ensure_ascii=False, indent=2))
