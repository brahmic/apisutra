<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

require __DIR__.'/fixtures/ReadMe.php';
require __DIR__.'/fixtures/ReadMessage.php';
require __DIR__.'/fixtures/Wire.php';
require __DIR__.'/fixtures/Mapped.php';
require __DIR__.'/fixtures/Rows.php';
require __DIR__.'/fixtures/ConstructorOwned.php';
require __DIR__.'/fixtures/RenameOnly.php';
require __DIR__.'/fixtures/ScopedMappedCast.php';

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;
use Brahmic\ApiSutra\Enums\Serialization\BooleanFormat;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HandlerSpec;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Serialization\Rules\ValueShape;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\MaxSutra\Internal\ApiSutra\MaxApiClient;
use Brahmic\MaxSutra\Internal\Protocol\BotInfoDecoder;
use Brahmic\MaxSutra\Internal\Protocol\MessageDecoder;
use Brahmic\MaxSutra\Model\Attachments\Buttons\CallbackButton;
use Brahmic\MaxSutra\Model\Attachments\InlineKeyboardAttachment;
use Brahmic\MaxSutra\Model\Messages\Message;
use Brahmic\MaxSutra\Model\Messages\MessageBody;
use Brahmic\MaxSutra\Model\Messages\MessageRecipient;
use Brahmic\MaxSutra\Model\Users\BotCommand;
use Brahmic\MaxSutra\Model\Users\BotInfo;
use Brahmic\MaxSutra\Tests\Support\MaxFixture;
use Brahmic\MaxSutra\Tests\Support\RecordingHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

function rules(): HydrationRules
{
    $bot = DtoRules::create()->extras('extra');
    foreach (['userId' => 'user_id', 'firstName' => 'first_name', 'isBot' => 'is_bot', 'lastActivityTime' => 'last_activity_time', 'lastName' => 'last_name', 'avatarUrl' => 'avatar_url', 'fullAvatarUrl' => 'full_avatar_url'] as $property => $source) {
        $field = FieldRule::create()->from($source);
        if (in_array($property, ['lastActivityTime', 'avatarUrl', 'fullAvatarUrl'], true)) {
            $field = $field->forbidExplicitNull();
        }
        $bot = $bot->field($property, $field);
    }
    $bot = $bot->field('commands', FieldRule::create()->shape(ValueShape::nullable(ValueShape::list(ValueShape::dto(BotCommand::class)))));

    return HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(BotInfo::class, $bot)
        ->withDto(BotCommand::class, DtoRules::create()->extras('extra'))
        ->withDto(MessageRecipient::class, DtoRules::create()->extras('extra')
            ->field('chatType', FieldRule::create()->from('chat_type'))
            ->field('chatId', FieldRule::create()->from('chat_id'))
            ->field('userId', FieldRule::create()->from('user_id'))
            ->field('postId', FieldRule::create()->from('post_id')))
        ->withDto(MessageBody::class, DtoRules::create()->extras('extra'))
        ->withDto(Message::class, DtoRules::create()->extras('extra')
            ->field('recipient', FieldRule::create()->shape(ValueShape::dto(MessageRecipient::class)))
            ->field('body', FieldRule::create()->shape(ValueShape::nullable(ValueShape::dto(MessageBody::class)))))
        ->withDto(Mapped::class, DtoRules::create()->extras('extra')
            ->field('id', FieldRule::create()->from('profile.id', 'id'))
            ->field('count', FieldRule::create()->forbidExplicitNull()));
}

function client(HydrationRules $rules, array $responses): array
{
    $http = new RecordingHttpClient($responses);
    $factory = new HttpFactory;
    $client = new MaxApiClient(new ClientConfig(
        baseUrl: 'https://example.invalid', hydrationRules: $rules,
        textBooleanFormat: BooleanFormat::Literal,
        containerProvider: new NullContainerProvider,
    ), new HttpTransport($http, $factory, $factory));

    return [$client, $http];
}

$rules = rules();
$hydrator = Hydrator::forRules($rules);
$bot = json_decode((string) MaxFixture::me()->getBody(), true, flags: JSON_THROW_ON_ERROR);
observe('real BotInfo equals existing decoder', fn () => $hydrator->hydrate($bot, BotInfo::class) == (new BotInfoDecoder)->decode($bot));
observe('real Message equals existing decoder (no attachments)', fn () => $hydrator->hydrate(MaxFixture::message(), Message::class) == (new MessageDecoder)->decode(MaxFixture::message()));
observe('optional missing vs nullable mandatory', fn () => $hydrator->hydrate(['user_id' => 1, 'first_name' => 'Bot', 'username' => null, 'is_bot' => true], BotInfo::class));
foreach ([['user_id', '123'], ['is_bot', 'false'], ['first_name', 5], ['avatar_url', null], ['last_activity_time', null], ['commands', ['bad' => ['name' => 'start']]], ['commands', [['name' => 5]]]] as [$field, $value]) {
    observe('reject '.$field.' '.json_encode($value), fn () => $hydrator->hydrate(array_replace($bot, [$field => $value]), BotInfo::class));
}
observe('mandatory nullable field omitted', function () use ($hydrator, $bot) {
    unset($bot['username']);

    return $hydrator->hydrate($bot, BotInfo::class);
});
observe('dot path fallback and extra collision', fn () => $hydrator->hydrate(['profile' => ['id' => 7, 'future' => false], 'id' => 8, 'extra' => ['origin' => null], 'zero' => 0, 'empty' => '', 'list' => []], Mapped::class));
observe('missing optional count', fn () => $hydrator->hydrate(['id' => 7], Mapped::class));
observe('explicit null count', fn () => $hydrator->hydrate(['id' => 7, 'count' => null], Mapped::class));
observe('sparse list', fn () => $hydrator->hydrate(array_replace($bot, ['commands' => [1 => ['name' => 'start']]]), BotInfo::class));
observe('HTTP Returns same real BotInfo', function () use ($rules, $hydrator, $bot) {
    [$client] = client($rules, [MaxFixture::me()]);
    $r = $client->send(new ReadMe)->raw();

    return ['success' => $r->isSuccess(), 'same' => $r->data == $hydrator->hydrate($bot, BotInfo::class), 'status' => $r->response?->status];
});
observe('HTTP unwrap Message and source path on error', function () use ($rules) {
    [$client] = client($rules, [MaxFixture::sent(['recipient' => ['chat_type' => 'dialog', 'chat_id' => '7']])]);
    $r = $client->send(new ReadMessage)->raw();

    return ['success' => $r->isSuccess(), 'status' => $r->response?->status, 'errors' => $r->errors, 'context' => method_exists($r->exception, 'context') ? $r->exception->context() : null];
});
observe('native bool query and body null false zero', function () use ($rules) {
    [$client,$http] = client($rules, [new Response(200, [], '{}')]);
    $r = $client->send(new Wire(false, ['text' => '0', 'notify' => false, 'attachments' => null, 'link' => null]))->raw();

    return ['success' => $r->isSuccess(), 'query' => $http->requests[0]->getUri()->getQuery(), 'body' => (string) $http->requests[0]->getBody()];
});
observe('receiver excluded on outbound plain object', function () use ($rules, $hydrator, $bot) {
    [$client,$http] = client($rules, [new Response(200, [], '{}')]);
    $r = $client->send(new Wire(true, $hydrator->hydrate($bot, BotInfo::class)))->raw();

    return ['success' => $r->isSuccess(), 'body' => json_decode((string) $http->requests[0]->getBody(), true)];
});
observe('strict and legacy rules isolated', function () use ($hydrator) {
    $legacy = Hydrator::forRules(HydrationRules::create()->withDto(Mapped::class, DtoRules::create()->extras('extra')));
    $value = $legacy->hydrate(['id' => '7'], Mapped::class);
    try {
        $hydrator->hydrate(['id' => '7'], Mapped::class);

        return false;
    } catch (Throwable) {
        return $value->id === 7;
    }
});
observe('nested lists with variants and raw unknown', function () use ($rules) {
    $nested = $rules->withDto(Rows::class, DtoRules::create()->extras('extra')->field('rows', FieldRule::create()->shape(ValueShape::list(ValueShape::list(ValueShape::variants('type', ['known' => Mapped::class]))))));

    return Hydrator::forRules($nested)->hydrate(['rows' => [[['type' => 'known', 'id' => 7, 'future' => false], ['type' => 'future', 'value' => 0]], []]], Rows::class);
});
observe('real CallbackButton constructor-owned type', fn () => Hydrator::forRules($rules->withDto(CallbackButton::class, DtoRules::create()->extras('extra')))->hydrate(['type' => 'callback', 'text' => 'OK', 'payload' => '0'], CallbackButton::class));
observe('real InlineKeyboardAttachment constructor-owned type', fn () => Hydrator::forRules($rules->withDto(InlineKeyboardAttachment::class, DtoRules::create()->extras('extra')->field('rows', FieldRule::create()->from('payload.buttons'))))->hydrate(['type' => 'inline_keyboard', 'payload' => ['buttons' => []]], InlineKeyboardAttachment::class));
observe('snake naming rejects camel-case-only source', function () {
    $naming = Hydrator::forRules(HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict, naming: NamingStrategy::SnakeCase))->withDto(BotInfo::class, DtoRules::create()->extras('extra')));

    return $naming->hydrate(['userId' => 7, 'firstName' => 'Bot', 'username' => null, 'isBot' => true], BotInfo::class);
});

observe('snake naming policy equals complete BotInfo decoder', function () use ($bot) {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict, naming: NamingStrategy::SnakeCase))
        ->withDto(BotInfo::class, DtoRules::create()->extras('extra')
            ->field('lastActivityTime', FieldRule::create()->forbidExplicitNull())
            ->field('avatarUrl', FieldRule::create()->forbidExplicitNull())
            ->field('fullAvatarUrl', FieldRule::create()->forbidExplicitNull())
            ->field('commands', FieldRule::create()->shape(ValueShape::nullable(ValueShape::list(ValueShape::dto(BotCommand::class))))))
        ->withDto(BotCommand::class, DtoRules::create()->extras('extra'));

    return Hydrator::forRules($rules)->hydrate($bot, BotInfo::class) == (new BotInfoDecoder)->decode($bot);
});
observe('snake naming chooses wire key and preserves camel extra', function () {
    $r = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict, naming: NamingStrategy::SnakeCase))->withDto(RenameOnly::class, DtoRules::create()->extras('extra'));

    return Hydrator::forRules($r)->hydrate(['record_id' => 7, 'recordId' => 8], RenameOnly::class);
});
observe('agnostic constructor-owned scalar reproducer', fn () => Hydrator::forRules(HydrationRules::create()->withDto(ConstructorOwned::class, DtoRules::create()->extras('extra')))->hydrate(['id' => 7, 'kind' => 'known'], ConstructorOwned::class));
observe('constructor-owned scalar omitted in source', fn () => Hydrator::forRules(HydrationRules::create()->withDto(ConstructorOwned::class, DtoRules::create()->extras('extra')))->hydrate(['id' => 7], ConstructorOwned::class));
observe('scoped custom cast shares strict rules', function () use ($rules) {
    $r = $rules->withDto(Rows::class, DtoRules::create()->field('rows', FieldRule::create()->shape(ValueShape::list(ValueShape::mixed(), itemCast: new HandlerSpec(ScopedMappedCast::class)))));

    return Hydrator::forRules($r)->hydrate(['rows' => [['id' => '7']]], Rows::class);
});
observe('strict nested list rejects bad known variant', function () use ($rules) {
    $nested = $rules->withDto(Rows::class, DtoRules::create()->field('rows', FieldRule::create()->shape(ValueShape::list(ValueShape::list(ValueShape::variants('type', ['known' => Mapped::class]))))));

    return Hydrator::forRules($nested)->hydrate(['rows' => [[['type' => 'known', 'id' => '7']]]], Rows::class);
});
observe('HTTP integer overflow remains diagnostic', function () use ($rules) {
    [$client] = $pair = client($rules, [new Response(200, ['Content-Type' => 'application/json'], '{"user_id":9223372036854775808,"first_name":"Bot","username":null,"is_bot":true}')]);
    $r = $client->send(new ReadMe)->raw();

    return ['success' => $r->isSuccess(), 'status' => $r->response?->status, 'context' => $r->exception?->context()];
});
observe('native fake hydrates DTO but bypasses PSR transport', function () use ($rules, $bot) {
    [$client,$http] = client($rules, []);
    $client->fake([ReadMe::class => MockResponse::success($bot)]);
    $client->preventStrayRequests();
    $r = $client->send(new ReadMe)->raw();

    return ['success' => $r->isSuccess(), 'class' => $r->data::class, 'PSR_calls' => count($http->requests)];
});
observe('preventStrayRequests alone reaches PSR transport', function () use ($rules) {
    [$client,$http] = client($rules, []);
    $client->preventStrayRequests();
    $r = $client->send(new ReadMe)->raw();

    return ['success' => $r->isSuccess(), 'error' => $r->exception?->getMessage(), 'PSR_calls' => count($http->requests)];
});
observe('two extra receivers explicitly rejected', fn () => DtoRules::create()->extras('extra')->extras('payloadExtra'));
observe('hydrator permits numeric extra keys unlike current Payload', function () use ($bot, $hydrator) {
    $bot[5] = 'future';
    $legacy = null;
    try {
        (new BotInfoDecoder)->decode($bot);
    } catch (Throwable $e) {
        $legacy = $e::class;
    }

return ['legacyError' => $legacy, 'newExtra' => $hydrator->hydrate($bot, BotInfo::class)->extra];
});

observe('fake plus preventStrayRequests blocks HTTP', function () use ($rules) {
    [$client,$http] = client($rules, []);
    $client->fake([]);
    $client->preventStrayRequests();
    $r = $client->send(new ReadMe)->raw();

    return ['success' => $r->isSuccess(), 'error' => $r->exception?->getMessage(), 'PSR_calls' => count($http->requests)];
});
observe('Returns succeeds with unwrapped real Message', function () use ($rules) {
    [$client] = client($rules, [MaxFixture::sent()]);
    $r = $client->send(new ReadMessage)->raw();

    return ['success' => $r->isSuccess(), 'same' => $r->data == (new MessageDecoder)->decode(MaxFixture::message())];
});
observe('Returns missing unwrap does not use root', function () use ($rules) {
    [$client] = client($rules, [new Response(200, [], json_encode(MaxFixture::message(), JSON_THROW_ON_ERROR))]);
    $r = $client->send(new ReadMessage)->raw();

    return ['success' => $r->isSuccess(), 'status' => $r->response?->status, 'context' => $r->exception?->context()];
});
observe('Returns null unwrap distinct from missing', function () use ($rules) {
    [$client] = client($rules, [new Response(200, [], '{"message":null}')]);
    $r = $client->send(new ReadMessage)->raw();

    return ['success' => $r->isSuccess(), 'status' => $r->response?->status, 'context' => $r->exception?->context()];
});

report('P', 'protocol-observations.json');
