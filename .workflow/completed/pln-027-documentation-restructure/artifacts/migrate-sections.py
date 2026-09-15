"""Однократная раскладка исходных разделов 027; редакционная приёмка выполняется отдельно."""
import importlib.util
import json
from pathlib import Path
import posixpath
import re
import subprocess
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parents[4]
PLAN = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('docs_check', ROOT / 'tests/Support/check-docs.py')
check = importlib.util.module_from_spec(spec)
spec.loader.exec_module(check)
REF = '6a649f5'
rows = re.findall(r'^\| `([^`]+)` \| (.+?) \| .+ \|$', (PLAN / 'content-map.md').read_text(), re.M)
filemap = {posixpath.normpath('docs/' + source): [posixpath.normpath('docs/' + target) for target in re.findall(r'`([^`]+)`', targets)] for source, targets in rows}

# Конкретный владелец каждой группы разделов; номера — порядок H2 исходного файла.
assignments = {}
def assign(source, groups):
    source = 'docs/' + source + '.md'
    for target, numbers in groups.items():
        target = posixpath.normpath('docs/' + target + '.md')
        for number in numbers:
            assignments[source, number] = target

assign('guides/requests', {'reference/request/declaration':range(0,18)})
assign('guides/dto', {
 'reference/dto/models':[0,2,4], 'reference/serialization/dto-output':[1],
 'reference/dto/scalars':[3], 'reference/dto/lifecycle':[5,14],
 'reference/dto/defaults':[6,10,11], 'reference/dto/profiles':[7],
 'reference/dto/shapes':[8], 'reference/dto/collections':[9],
 'reference/client/validation':[12],
})
assign('guides/hydration-rules', {
 'guides/dto/plain-models':[0,1,10], 'reference/dto/field-rules':[2],
 'reference/dto/scalars':[3], 'reference/dto/shapes':[4],
 'reference/dto/variants':[5], 'reference/dto/scope':[6],
 'reference/dto/extras':[7], 'reference/serialization/receiver-output':[8],
 'reference/dto/diagnostics':[9],
})
assign('guides/serialization', {
 'reference/serialization/request-parts':[0,1,2,3],
 'reference/serialization/uri-query':[4,5,8,9,16],
 'reference/serialization/body':[6,7,15,17],
 'reference/serialization/dto-output':[10,11,12,13],
 'reference/dto/scalars':[14], 'reference/serialization/receiver-output':[18],
})
assign('guides/casts', {'reference/serialization/casts':[0,2,3,4,5,6,7,8,9], 'reference/dto/lifecycle':[1], 'reference/dto/scope':[10]})
assign('guides/auth', {'reference/auth/strategies':[0,1,2,3,4,5,6,7,9,10,11,12,13,14,15], 'reference/auth/tokens':[8,16,17,18,20,21], 'reference/auth/credentials':[19]})
assign('guides/errors', {'reference/results/handles':[0,1,2,3], 'reference/results/errors':[4,5,6,7,8,10,11,12], 'reference/execution/continuation-await':[9], 'reference/dto/diagnostics':[13]})
assign('guides/external-urls', {'reference/serialization/uri-query':[0,1,3,4], 'reference/auth/credentials':[2]})
assign('guides/files', {'reference/files/uploads':[0,1], 'reference/files/downloads':[2,3,5], 'reference/files/archives':[4]})
assign('guides/extensions', {'reference/extensions/extensions':[0,1,2,3,4,6], 'guides/recipes/extensions':[5]})
assign('guides/laravel', {'reference/integrations/laravel':range(0,13)})
assign('guides/live-testing', {'reference/testing/live':[0,1,2,9,11,12,13,16,17,18], 'guides/testing/live':[3,4,5,6,7,8,10,14,15]})
assign('guides/megaclient', {'guides/integration/multi-service':[0,1,2,3,5,6,7,8,9,11], 'reference/client/construction':[4], 'reference/client/catalogs':[10]})
assign('guides/migration', {'migration/unreleased':[0,1,2], 'migration/v0.2.0-alpha.1':[3,4]})
assign('guides/naming-strategy', {'reference/serialization/request-parts':range(0,6)})
assign('guides/operation-inventory', {'reference/client/operation-inventory':range(0,11), 'reference/client/catalogs':[11]})
assign('guides/pagination', {'reference/execution/pagination':range(0,12)})
assign('guides/provider-async-await', {'guides/recipes/continuation':[0,1,3,5], 'reference/execution/continuation-state':[2,4,7], 'reference/execution/continuation-await':[6,8,9]})
assign('guides/provider-catalogs', {'reference/client/catalogs':range(0,12)})
assign('guides/provider-checklist', {'guides/sdk/coverage':[0,1,2,3,4,5,6], 'guides/sdk/release':[7]})
assign('guides/provider-methodology', {'guides/sdk/design':[0,1,2,3,4], 'reference/auth/credentials':[5], 'guides/sdk/analysis':[6,7], 'guides/testing/live':[8], 'guides/integration/multi-service':[9,10], 'guides/sdk/first-operation':[11,12,13], 'guides/testing/unit':[14], 'guides/sdk/coverage':[15]})
assign('guides/retries-rate-limit', {'reference/execution/retry':[0,1,2,6,7,8,9], 'reference/execution/deadlines':[3], 'reference/execution/rate-limit':[4,5]})
assign('guides/testing', {'reference/testing/mocking':[0,2,3,4,5,6,8,9], 'guides/testing/unit':[1,10,11], 'reference/testing/fixtures':[7,14], 'reference/testing/live':[12,13], '../development/testing':[15,16]})
assign('guides/use-cases', {'reference/execution/batch-pool':[1,2], 'guides/recipes/pagination':[3], 'guides/recipes/files':[4], 'guides/recipes/continuation':[5], 'guides/integration/multi-service':[6]})
assign('example/request-use-cases', {'reference/results/handles':[0,1,2,6,7,8,9,12,13], 'guides/recipes/pagination':[3,10], 'guides/recipes/files':[4,5], 'reference/execution/transport':[11]})
assign('example/documentation-guide', {'guides/sdk/release':range(0,25)})
assign('guides/client-config/auth', {'reference/auth/strategies':[0,1,2,3], 'reference/auth/tokens':[4,8], 'reference/auth/credentials':[5,6,7]})
assign('guides/client-config/serialization', {'reference/serialization/request-parts':[0,1,2,3], 'reference/serialization/casts':[4], 'reference/serialization/body':[5,7,8], 'reference/serialization/dto-output':[6], 'reference/dto/field-rules':[9]})
assign('guides/client-config/responses-errors', {'reference/results/handles':[0,1,3,9], 'reference/results/errors':[2,4,8], 'reference/execution/continuation-await':[5,7], 'reference/execution/continuation-state':[6]})

# Навигационные страницы переписываются целиком; их содержание не является вторым контрактом.
rewrite = {'README.md', 'docs/README.md', 'docs/guides/README.md', 'docs/glossary.md',
           'docs/glossary/README.md', 'docs/technical/README.md', 'docs/guides/quickstart.md'}
rewrite |= {name for name in filemap if name.startswith('docs/glossary/')}
ignored_titles = {'Где детали', 'Где подробности', 'См. также', 'Где ещё смотреть', 'Дальше', 'Содержание', 'Карта разделов', 'Как читать этот гайд'}
sections, origins, by_target = [], {}, {}
old_anchors = {}
for name, targets in filemap.items():
    raw = subprocess.check_output(['git', 'show', REF + ':' + name], cwd=ROOT, text=True)
    old_anchors[name] = check.headings(raw)
    # H2 только вне fenced code: вложенные шаблоны документации не разбиваются.
    visible = check.without_fences(raw).splitlines()
    lines = raw.splitlines(keepends=True)
    starts = [0] + [n for n, line in enumerate(visible) if line.startswith('## ')]
    starts = sorted(set(starts))
    starts.append(len(lines))
    for number, (start, end) in enumerate(zip(starts, starts[1:])):
        body = ''.join(lines[start:end])
        title = re.search(r'^#{1,2} (.+)$', body, re.M)
        title = title[1] if title else 'Введение'
        target = assignments.get((name, number), targets[0])
        section = {'source': name, 'section': title, 'line': start + 1, 'target': target,
                   'anchors': check.headings(body), 'action': 'перенос; требуется редакционная сверка'}
        sections.append(section)
        if name in rewrite or name == 'CHANEGLOG.md':
            section['action'] = 'переработка навигации/определения' if name != 'CHANEGLOG.md' else 'история сохранена'
            continue
        if title in ignored_titles:
            section['action'] = 'навигация заменяется прямыми ссылками'
            continue
        if number == 0:
            body = re.sub(r'^# .+\n+', '', body, count=1)
            if not body.strip():
                continue
        by_target.setdefault(target, []).append((name, body))
        origins.setdefault(target, []).append(name)

# Сохраняется до изменения источников: это карта переноса, а не приёмка содержания.
(PLAN / 'artifacts/section-moves.json').write_text(json.dumps(sections, ensure_ascii=False, indent=2) + '\n')
anchor_map = {}
for section in sections:
    for anchor in section['anchors']:
        anchor_map[section['source'], anchor] = (section['target'], anchor)


def relocate(body, source, target):
    def rewrite_link(match):
        value = match[1]
        parsed = urlsplit(value.strip('<>'))
        if parsed.scheme or parsed.netloc:
            return match[0]
        old = posixpath.normpath(posixpath.join(posixpath.dirname(source), unquote(parsed.path))) if parsed.path else source
        new = filemap.get(old, [old])[0]
        fragment = unquote(parsed.fragment)
        if fragment and (old, fragment) in anchor_map:
            new, fragment = anchor_map[old, fragment]
        if not target.startswith('development/') and new.startswith(('development/', 'tests/', '.workflow/', '.agents/')):
            link = 'https://github.com/brahmic/apisutra/blob/master/' + new
        else:
            link = posixpath.relpath(new, posixpath.dirname(target))
        if fragment:
            link += '#' + fragment
        return '](' + link + ')'
    return re.sub(r'\]\(([^)\n]+)\)', rewrite_link, body)

for target, chunks in by_target.items():
    path = ROOT / target
    path.parent.mkdir(parents=True, exist_ok=True)
    label = path.stem.replace('-', ' ').capitalize()
    body = '# ' + label + '\n\n' + '\n'.join(relocate(body, name, target).strip() + '\n' for name, body in chunks)
    path.write_text(body.rstrip() + '\n')

# Старые URL и все заголовки остаются; окончательные назначения проверяются после редактуры.
for source, targets in filemap.items():
    if source in by_target or source in rewrite or source == 'CHANEGLOG.md':
        continue
    body = check.BRIDGE + '\n\n# Раздел перенесён\n\n'
    for anchor in old_anchors[source]:
        target, fragment = anchor_map.get((source, anchor), (targets[0], ''))
        if target.startswith('development/'):
            url = 'https://github.com/brahmic/apisutra/blob/master/' + target
        else:
            url = posixpath.relpath(target, posixpath.dirname(source))
        body += '<a id="' + anchor + '"></a>\n[Открыть актуальный раздел](' + url + ('#' + fragment if fragment else '') + ').\n'
    (ROOT / source).write_text(body)
(PLAN / 'artifacts/migration-origins.json').write_text(json.dumps(origins, ensure_ascii=False, indent=2) + '\n')
print(f'Разложено {len(sections)} разделов в {len(by_target)} файлов. Это промежуточная раскладка, требуется редакционная сверка.')
