"""Проверяет состав архивов локально; ничего не публикует."""
from collections import Counter
from pathlib import Path
import json
import subprocess
import tarfile
import tempfile

root = Path(__file__).resolve().parents[4]
with tempfile.TemporaryDirectory(prefix='apisutra-release-probe-') as directory:
    composer = subprocess.run(
        ['composer', 'archive', '--format=tar', '--dir=' + directory, '--file=composer-package'],
        cwd=root, capture_output=True, text=True, check=True,
    )
    subprocess.run(['git', 'archive', '--format=tar', '-o', directory + '/git-package.tar', 'HEAD'], cwd=root, check=True)
    for kind in ['composer', 'git']:
        archive = Path(directory) / (kind + '-package.tar')
        with tarfile.open(archive) as contents:
            names = [entry.name[2:] if entry.name.startswith('./') else entry.name for entry in contents if entry.isfile()]
        groups = Counter(name.split('/')[0] for name in names)
        print(json.dumps({
            'case': kind + '_archive', 'files': len(names), 'bytes': archive.stat().st_size,
            'topLevelCounts': dict(sorted(groups.items())),
            'internalSamples': [name for name in names if name.startswith(('.workflow/', '.agents/', 'vendor/'))][:8],
        }, ensure_ascii=False))
