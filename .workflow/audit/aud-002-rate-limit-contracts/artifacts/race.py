"""Два PHP-процесса читают одно окно перед первой записью; каталог стенда удаляется."""
import json
import pathlib
import subprocess
import tempfile

worker = pathlib.Path(__file__).with_name('race-worker.php')
with tempfile.TemporaryDirectory(prefix='apisutra-rate-probe-') as directory:
    processes = [subprocess.Popen(['php', str(worker), directory], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True) for _ in range(2)]
    results = []
    try:
        for process in processes:
            stdout, stderr = process.communicate(timeout=6)
            if process.returncode:
                raise RuntimeError(stderr)
            results.append(json.loads(stdout))
        state = json.loads((pathlib.Path(directory) / 'state.json').read_text())
        print(json.dumps({'case': 'two_processes', 'limit': 1, 'allowed': sum(r['allowed'] for r in results), 'stored_count': state['count']}))
    finally:
        for process in processes:
            if process.poll() is None:
                process.kill()
                process.communicate()
