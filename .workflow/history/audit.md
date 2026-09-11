# О чём вообще пакет
brahmic/apisutra — это PHP 8.4+ фреймворк для написания SDK‑клиентов внешних API в декларативном стиле. Идея: разработчик описывает запросы и DTO атрибутами, а единый pipeline берёт на себя auth, retry, rate‑limit, cache, pagination, hooks, batch/pool, мегаклиент, async/continuation. Тонкая прослойка под Laravel, но Laravel не обязателен (PSR‑18/17/16).

Архитектурно это «сутра» в прямом смысле: нитка, на которую нанизано довольно много концепций — BaseClient/AbstractRequest/AbstractResource, ClientConfig как единый источник дефолтов, отдельные профили DtoHydrationProfile / DtoSerializationProfile / wireBodySerializationPolicy, ResolvedResult/ResultHandle, OperationInventory, ProviderCatalogRegistry, ContinuationMode/ContinuationResult и т.д.

## Что мне понравилось
Чёткое разделение трёх плоскостей сериализации. Разделение DtoHydrationProfile (как принимаем извне), DtoSerializationProfile (DX toArray()), и wireBodySerializationPolicy (что реально летит в сеть) — это правильная декомпозиция. Большинство SDK‑фреймворков на этом и спотыкаются, когда «удобный для человека» вид DTO начинает диктовать, что отдавать на wire.

Жёсткое разнесение «бизнес‑данных» и «сервисных вещей». Установка «DTO = бизнес‑данные; ошибки/trace/continuation/meta — в result‑слое» (provider-methodology.mdc, core invariants) спасает от классического анти‑паттерна, когда OrderDto тащит requestId, resultCode, traceId и т.д.

ResultMetaExtractor и branded ResolvedResult. Хороший рецепт против envelope‑дублирования. Критерий «достаёшь данные через result()->meta → пора делать branded result» — практичный и не размытый.

Anti‑flat правило структуры провайдера. В provider-structure.mdc — vertical slice по методу: Resources/<Resource>/Requests/<Method>/{Request,Dto/Response,Dto/Request,Enums}. Это ровно то, что нужно для крупных провайдеров; иначе Dto/Response неизбежно превращается в свалку из 80 файлов.

Чеклист как контракт. provider-checklist.md с колонкой «Мои заметки» — отличная вещь: и онбординг, и code review checklist, и трассируемость решений. Редко встречающийся уровень дисциплины.

Разделение OperationInventory / ProviderCatalog / OperationDescriptor. Видно, что ребята уже наступили на грабли «давайте всё в meta запихнём». Static catalog — read‑only, no‑I/O, с generatedAt, отдельный от runtime meta — это правильно и редко встречается в PHP‑SDK.

Унифицированный ContinuationMode/#[ContinuationResult]. Один request на бизнес‑действие с опциональным sync/async — приятный DX, особенно для провайдеров, где «то sync, то polling».

Документация в нескольких слоях. guides (how‑to), client-config/attributes (полные перечни), technical (обзор), glossary — разделение зон ответственности зафиксировано отдельным правилом docs-structure.mdc. Это редко доживает до продакшена.

## Что настораживает
Поверхностная сложность очень высокая. В src/ 35 подпапок верхнего уровня; в Pipeline/ — 12 подпапок; в Attributes/ — 6 групп с десятками атрибутов; в Config/ — 14 классов. Для SDK‑builder это много. Войти «с улицы» без чеклиста и методологии практически нереально. По сути методология — это не дополнение, а обязательная часть продукта. Это ок, если пакет внутренний; для public — рискованно.

Концептуальная плотность правил. В одном provider-methodology.mdc упомянуты: DtoHydrationProfile, DtoSerializationProfile, wireBodySerializationPolicy, requestPartsEnumOutput, requestPartsStrictEnums, DateTimeFrom/To, #[DtoHydrate]/#[DtoSerialize], #[Cast], RequestOneOf/Discriminator, RequestDefaults/BodyRoot, RequestPartsEnricher, ContinuationTokenExtractor, ResultMetaExtractor, ProviderCatalogRegistry, OperationInventory, OperationDescriptor, ContinuationMode, ContinuationModeApplicator, PaginationConfig, PaginationMetaResolver, branded ResolvedResult, ContinuationResult, ClientErrorMapper, ErrorContextFactory, AuthenticatorInterface/AuthPolicyInterface, RequestContractTestHelper, LiveEnvLoader/LivePolling/LiveResultAssertions, LiveTestGuard, LiveClientFactory, LiveFixtureLoader. Это сильно больше, чем у Saloon/Crell/league/openapi. Пакет ближе по амбициям к Laravel HTTP + Spatie Data + Saloon одновременно. Стоит явно обозначить это в README — сейчас README продаёт пакет как «лёгкую штуку», а это не так.

Терминологический шум. В правилах требуется «provider, resource, SDK client, transport, ClientConfig, DTO». В коде/доках при этом мелькают: BaseClient/AbstractClient, BaseRequest/AbstractRequest, Client/SDK client/provider, мегаклиент, BaseDto/AbstractDto/AbstractResponseDto. То есть в README пишем Base*, а в коде — Abstract*. Я бы выбрал одно. Сейчас «Base» и «Abstract» используются как синонимы, и это вводит в заблуждение (см. чеклист: «Базовые классы: BaseClient…», но в коде — AbstractClient).

DTO‑слой почти обязателен, но контракт не очевиден. Описано минимум 5 уровней приоритета (From -> Map -> NamingStrategy для гидрации, To -> Map -> NamingStrategy для serialize, DefaultValue when:, Missing -> [] фоллбэк, EmptyStringAsNull, safe auto‑cast scalar, #[Cast] и #[DtoHydrate]/#[DtoSerialize]). На больших DTO разработчик не сможет в голове воспроизвести, какое правило сработает. Стоит сделать отдельную таблицу приоритетов / decision tree, а не разбрасывать по 4 параграфам.

«Настоятельные рекомендации» = неявно обязательные. Фраз «настоятельная рекомендация» в правилах несколько (DtoHydration/DtoSerialization profiles, ResultMetaExtractor для envelope, явный EnumSerialization). Если без них реально нельзя жить — лучше сделать их required и валидировать; если можно — убрать слово «настоятельно». Сейчас это серая зона, в ревью каждый раз будет спор.

alwaysApply: false у provider-methodology.mdc и provider-structure.mdc. Эти правила привязаны к узким glob'ам (packages/brahmic/back/apisutra/docs/guides/provider-*.md и packages/bezopasnosdelka/back/*-apisutra/src/**/*.php). То есть когда я редактирую конкретного провайдера — правила подхватятся, но при работе из корня (например, при code review провайдера через PR) методология не активируется автоматически. Я бы либо явно перечислил это в index.mdc как «обязательно прочитать при старте провайдера», либо расширил globs.

Domain/ vs Common/ vs Shared/. В правиле сказано: «Domain — provider‑wide; избегать Shared», но при этом введён Resources/<Resource>/Common/... для resource‑level reuse. Граница «Common → Domain» нигде не количественная. На практике это превращается в «затаскиваем в Domain, как только используется во втором месте» — и Domain пухнет. Стоит сформулировать правило «promote» явно: например, «переносим в Domain/ только когда используется ≥2 ресурсами или ≥3 методами разных ресурсов».

Опечатка в имени файла: CHANEGLOG.md вместо CHANGELOG.md. Мелочь, но это публичный артефакт пакета и его видят все консьюмеры.

README.md не отражает реальный масштаб пакета. В README три абзаца «возможностей» и quickstart на 30 строк, а реальный объём — десятки гайдов и шесть .cursor/rules. Quickstart создаёт ложное ощущение «это micro‑library». Я бы добавил блок «когда НЕ нужен ApiSutra» (один эндпоинт, прототип, нестабильное API) — это сэкономит время потенциальным пользователям и снизит количество «зачем тут столько» вопросов.

Раздел тестирования. В чеклисте опционально для record/playback, live‑testing, LiveFixtureLoader, балансов, Laravel smoke и т.п. — но сразу следует оговорка «на практике почти всегда нужно». Если эти вещи реально базовые — у них должно быть «обязательно при условиях X», а не «опционально, но обычно нужно». Это влияет на onboarding и на то, как новички пишут SDK без живых тестов.

composer.json: "license": "proprietary" — при этом в README/AGENTS пакет явно подаётся как фреймворк. Если планируется внутреннее использование — ок; если когда‑то будет публикация — стоит подумать о лицензии заранее.

example/ и .temp/, .idea/ в дистрибутиве. Если пакет не имеет extra.archive или .gitattributes с export-ignore, то при composer require пользователь получит и .idea/, и .temp/, и workflow/, и tests/. Стоит проверить composer install --no-dev payload.

## Что бы я сделал в ближайшее время
### В порядке убывания пользы:

Привести к одной базовой терминологии: либо Base*, либо Abstract* — и в README, и в коде, и в чеклисте. Сейчас расхождение между правилами и кодом.
Сделать decision tree сериализации/гидрации как одну страницу: «у вас есть значение X на свойстве Y типа Z — какое правило сработает». С наглядной таблицей From | Map | NamingStrategy | DefaultValue | EmptyStringAsNull | safe auto‑cast | #[Cast] | #[DtoHydrate].
Переименовать CHANEGLOG.md → CHANGELOG.md.
Уточнить границу Resources/<Resource>/Common ↔ Domain/ количественным правилом promote.
Расширить index.mdc явным «при работе с любым *-apisutra пакетом — читай provider-methodology и provider-structure обязательно». Сейчас они alwaysApply: false с узкими globs.
В README добавить раздел «когда ApiSutra избыточен» и честный disclaimer про размер концептуального аппарата (включая блок про методологию как часть продукта).
Перевести «настоятельные рекомендации» в одно из двух: либо required + валидация, либо «recommended, optional» без давления.
Проверить .gitattributes на export-ignore для .temp/, .idea/, workflow/, tests/, example/.
Итог
Пакет производит впечатление зрелого внутреннего фреймворка, не «лёгкой обёртки над HTTP». Идеи правильные, разделение слоёв грамотное, методология действительно методология (а не «список советов»). Главный риск — высокая сложность входа и расхождение между «продающим» README и реальным масштабом. Это лечится терминологией, decision‑tree‑документами и честным позиционированием. Ядро при этом — крепкое; видно, что авторы уже прошли через несколько провайдеров и зашили в каркас уроки (catalog vs meta, branded result, three‑plane serialization, vertical slice по методу, anti‑flat для DTO).

### Если хочешь, могу:

набросать единую страницу «правила приоритетов сериализации» (п. 2);
пройтись по терминологии в коде и доках с предложением консистентного варианта (п. 1);
проверить .gitattributes/composer payload (п. 8).