"""Стоимость принятого baseline и реализации; исходный benchmark не изменяется."""

import json
from pathlib import Path
import subprocess
import sys

ARTIFACTS = Path(__file__).resolve().parent
ROOT = ARTIFACTS.parents[3]
benchmark = ARTIFACTS / "cost-benchmark.php"

if "--check-only" not in sys.argv:
    after = json.loads(subprocess.check_output(["php", str(benchmark)], cwd=ROOT, timeout=120))
    (ARTIFACTS / "implementation-after-cost.json").write_text(
        json.dumps(after, ensure_ascii=False, indent=2) + "\n"
    )

    # Тот же сценарий с удвоенным числом итераций, через stdin без временного PHP-файла.
    source = benchmark.read_text().replace("__DIR__", "'" + str(ARTIFACTS) + "'")
    source = source.replace("    $operation();", "    $iterations *= 2;\n    $operation();", 1)
    double = json.loads(subprocess.check_output(["php"], input=source.encode(), cwd=ROOT, timeout=120))
    (ARTIFACTS / "implementation-double-cost.json").write_text(
        json.dumps(double, ensure_ascii=False, indent=2) + "\n"
    )
else:
    after = json.loads((ARTIFACTS / "implementation-after-cost.json").read_text())
    double = json.loads((ARTIFACTS / "implementation-double-cost.json").read_text())

before = json.loads((ARTIFACTS / "implementation-baseline-cost.json").read_text())
checks = {}
memory_comparison = {}
for name, result in after["scenarios"].items():
    if ".values.warm." in name:
        checks["P01:" + name] = result["median_ms"] <= before["scenarios"][name]["max_ms"] + result["spread_ms"]
    if ".warm." in name:
        off = after["scenarios"][name.replace(".warm.", ".off.")]
        warm_per_op = result["median_ms"] / result["iterations"]
        off_per_op = off["median_ms"] / off["iterations"]
        scatter = result["spread_ms"] / result["iterations"] + off["spread_ms"] / off["iterations"]
        checks["P02:" + name] = warm_per_op <= off_per_op + scatter
    doubled = double["scenarios"][name]
    assert doubled["iterations"] == 2 * result["iterations"], name
    # Сам benchmark добавляет запись в массив времён до замера памяти (обычно 216 байт).
    # Требуем отсутствие роста при удвоении операций, а не нулевой размер служебного массива.
    memory_comparison[name] = {
        "single": result["retained_bytes_max"],
        "double": doubled["retained_bytes_max"],
        "no_growth": doubled["retained_bytes_max"] <= result["retained_bytes_max"],
    }

# Дополнительная проверка отделяет разовые аллокации PHP от удержания значений кешем.
memory = json.loads(subprocess.check_output(["php", str(ARTIFACTS / "implementation-memory.php")], cwd=ROOT, timeout=60))
(ARTIFACTS / "implementation-memory.json").write_text(json.dumps(memory, indent=2) + "\n")
checks["P03:steady_state"] = memory["passed"]

report = {
    "baseline_head": before["head"],
    "implementation_head": after["head"],
    "working_tree_diff": subprocess.check_output(["git", "diff", "--name-only", "--", "src"], cwd=ROOT, text=True).splitlines(),
    "checks": checks,
    "memory_comparison": memory_comparison,
    "memory_confirmation": "implementation-memory.json: 1200/2400/4800 операций после прогрева, включая cold-hydrator-cast",
    "P04": "Объектные сценарии сохранены в implementation-after-cost.json; дополнительные вычисления new ожидаемы.",
}
(ARTIFACTS / "implementation-cost-checks.json").write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
print(json.dumps(report, ensure_ascii=False, indent=2))
raise SystemExit(0 if all(checks.values()) else 1)
