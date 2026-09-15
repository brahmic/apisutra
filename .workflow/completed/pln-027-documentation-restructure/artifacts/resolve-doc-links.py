"""Закрепляет прямые переходы и карту прежних разделов после редакционной перегруппировки."""
import importlib.util
import json
from pathlib import Path
import posixpath
import re
import subprocess
from urllib.parse import unquote, urlsplit

ROOT=Path(__file__).resolve().parents[4]
PLAN=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('docs_check',ROOT/'tests/Support/check-docs.py')
check=importlib.util.module_from_spec(spec);spec.loader.exec_module(check)
sections=json.loads((PLAN/'artifacts/section-moves.json').read_text())
filemap={}
for row in re.findall(r'^\| `([^`]+)` \| (.+?) \| .+ \|$',(PLAN/'content-map.md').read_text(),re.M):
 filemap[posixpath.normpath('docs/'+row[0])]=[posixpath.normpath('docs/'+p) for p in re.findall(r'`([^`]+)`',row[1])]
canonical={str(p.relative_to(ROOT)):p.read_text() for p in [ROOT/'README.md',ROOT/'CHANEGLOG.md',ROOT/'CONTRIBUTING.md',*sorted((ROOT/'docs').rglob('*.md')),*sorted((ROOT/'development').rglob('*.md'))] if p.exists() and not check.is_bridge(p.read_text())}
# Старые технические и словарный индексы — переходы, а не владельцы.
canonical.pop('docs/technical/README.md',None)
all_anchors={p:check.anchors(s) for p,s in canonical.items()}
by_anchor={}
for p,values in all_anchors.items():
 for a in values:by_anchor.setdefault(a,[]).append(p)

def owner(source,title,default):
 t=title.lower()
 if source=='README.md':
  if 'тест' in t or 'github' in t:return 'development/testing.md'
  if 'быстр' in t:return 'docs/guides/quickstart.md'
  return 'README.md'
 if source=='docs/technical/README.md':return 'development/README.md'
 if source=='docs/example/documentation-guide.md':return 'docs/guides/sdk/release.md'
 if source=='docs/guides/provider-methodology.md':
  for word,target in [('входной','guides/sdk/analysis'),('протокол','guides/sdk/analysis'),('credentials','reference/auth/credentials'),('аутентификац','reference/auth/strategies'),('sandbox','guides/testing/live'),('мультисервис','guides/integration/multi-service'),('версионирован','reference/client/versioning'),('ошибк','reference/results/errors'),('envelope','reference/results/handles'),('resultmeta','reference/results/handles'),('catalog','reference/client/catalogs'),('inventory','reference/client/operation-inventory'),('appcode','guides/sdk/first-operation'),('providertraceid','guides/sdk/first-operation'),('пагинац','reference/execution/pagination'),('тест','guides/testing/unit'),('чек','guides/sdk/coverage'),('практики','guides/testing/unit')]:
   if word in t:return 'docs/'+target+'.md'
  return 'docs/guides/sdk/design.md'
 if source=='docs/guides/provider-checklist.md':return 'docs/guides/sdk/coverage.md'
 if source=='docs/example/request-use-cases.md':
  if 'пагинац' in t or 'pagination' in t:return 'docs/guides/recipes/pagination.md'
  if 'файл' in t or 'архив' in t:return 'docs/guides/recipes/files.md'
  if 'асинхронность через' in t or 'явный async mode' in t:return 'docs/reference/execution/transport.md'
  if 'маппинг ошибок' in t:return 'docs/reference/results/errors.md'
  if 'laravel' in t:return 'docs/reference/integrations/laravel.md'
  return 'docs/reference/results/handles.md'
 if 'responsedtocatalog' in t.replace(' ','') or 'response dto' in t:return 'docs/reference/client/response-dto-catalog.md'
 if 'resultmetaextractor' in t.replace(' ',''):return 'docs/reference/results/handles.md'
 if source=='docs/guides/hydration-rules.md':
  if 'правила и проверка' in t:return 'docs/reference/dto/field-rules.md'
 if source.startswith('docs/glossary/'):
  return source
 return default

moves=[];lookup={}
for source,targets in filemap.items():
 raw=subprocess.check_output(['git','show','6a649f5:'+source],cwd=ROOT,text=True)
 visible=check.without_fences(raw)
 headings=re.findall(r'^#{1,6}\s+(.+?)(?:\s+#+)?$',visible,re.M)
 anchors=check.headings(raw)
 rows=[r for r in sections if r['source']==source]
 positions=[i+1 for i,line in enumerate(visible.splitlines()) if re.match(r'^#{1,6}\s+',line)]
 for title,anchor,line in zip(headings,anchors,positions):
  parent=max((r for r in rows if r['line']<=line),key=lambda r:r['line'])
  proposed=owner(source,title,parent['target'])
  candidates=by_anchor.get(anchor,[])
  # Прямое совпадение заголовка подтверждает место переноса; старая схема служит tie-breaker.
  if proposed in candidates:
   target,fragment,action=proposed,anchor,'раздел сохранён у тематического владельца'
  elif candidates and source not in {'README.md','docs/README.md','docs/guides/README.md','docs/technical/README.md','docs/glossary/README.md','docs/example/documentation-guide.md'}:
   preferred=[p for p in candidates if p in targets]
   if not preferred:preferred=[p for p in candidates if p.startswith('docs/reference/')]
   if not preferred:preferred=candidates
   target,fragment,action=preferred[0],anchor,'раздел перенесён или объединён у владельца'
  else:
   target=proposed
   if target not in canonical:target=next((p for p in targets if p in canonical),targets[0])
   # Переписанный раздел ведёт к ближайшему сохранённому родительскому разделу.
   parent_anchor=check.headings('## '+parent['section'])[0]
   fragment=parent_anchor if parent_anchor in all_anchors.get(target,set()) else check.headings(canonical[target])[0]
   action='переработан: навигация/рецепт/определение; полное поведение у указанного владельца'
  record={'source':source,'line':line,'heading':title,'anchor':anchor,'target':target,'fragment':fragment,'action':action}
  moves.append(record);lookup[source,anchor]=(target,fragment)


def resolve(source,fragment=''):
 if fragment and (source,fragment) in lookup:return lookup[source,fragment]
 if source in filemap and source not in canonical:
  target=next((p for p in filemap[source] if p in canonical),filemap[source][0])
 else:target=source
 if fragment and fragment not in all_anchors.get(target,set()):
  candidates=by_anchor.get(fragment,[])
  if len(candidates)==1:return candidates[0],fragment
  # Ищем исходный раздел, который был перемещён из этого тематического файла.
  destinations={(r['target'],r['fragment']) for r in moves if r['anchor']==fragment}
  if len(destinations)==1:return next(iter(destinations))
 return target,fragment


def link(target,fragment,from_path):
 if from_path.startswith(('docs/','README.md','CHANEGLOG.md')) and target.startswith(('development/','CONTRIBUTING.md','.agents/','.workflow/','tests/')):
  base='https://github.com/brahmic/apisutra/blob/master/'+target
 else:base=posixpath.relpath(target,posixpath.dirname(from_path))
 return base+('#'+fragment if fragment else '')

for source,body in canonical.items():
 def replace(match):
  value=match[1];parsed=urlsplit(value.strip('<>'))
  if parsed.scheme or parsed.netloc:return match[0]
  previous=posixpath.normpath(posixpath.join(posixpath.dirname(source),unquote(parsed.path))) if parsed.path else source
  target,fragment=resolve(previous,unquote(parsed.fragment))
  return ']('+link(target,fragment,source)+')'
 body=re.sub(r'\]\(([^)\n]+)\)',replace,body)
 (ROOT/source).write_text(body)

for source in filemap:
 if source in canonical:continue
 source_moves=[m for m in moves if m['source']==source]
 body=check.BRIDGE+'\n\n# Раздел перенесён\n\n'
 if source.startswith('docs/technical/'):
  body+='[Публичные контракты](../reference/README.md). Внутреннее устройство описано в руководстве разработчика репозитория.\n\n'
 for move in source_moves:
  body+='<a id="'+move['anchor']+'"></a>\n[Открыть актуальный раздел]('+link(move['target'],move['fragment'],source)+').\n'
 (ROOT/source).write_text(body)

# На неизменившихся URL после переписывания сохраняются старые внешние якоря.
for source in filemap:
 if source not in canonical or source=='CHANEGLOG.md':continue
 p=ROOT/source;body=p.read_text();existing=check.anchors(body)
 aliases=[]
 for move in moves:
  if move['source']==source and move['anchor'] not in existing:
   aliases.append('<a id="'+move['anchor']+'"></a>\n[Раздел в текущей документации]('+link(move['target'],move['fragment'],source)+').')
 if aliases:body+='\n## Прежние разделы\n\n'+'\n'.join(aliases)+'\n';p.write_text(body)

(PLAN/'artifacts/resolved-section-moves.json').write_text(json.dumps(moves,ensure_ascii=False,indent=2)+'\n')
print(f'Закреплены адреса {len(moves)} заголовков {len(filemap)} исходных документов.')
