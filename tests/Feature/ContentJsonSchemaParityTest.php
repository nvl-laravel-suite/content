<?php

declare(strict_types=1);

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Services\ContentFieldPresetRegistry;
use Nvl\Content\Services\ContentJsonSchemaBuilder;
use Nvl\Content\Services\ContentSchemaCompiler;
use Nvl\Content\Validation\ContentValidationContext;
use Nvl\Content\Validation\ContentValueValidator;
use Opis\JsonSchema\Validator;

it('keeps generated schemas aligned with runtime content constraints', function (): void {
    config()->set('content.validation.maximum_items', 2);
    config()->set('content.rich_text.maximum_input_length', 5);

    $schema = app(ContentSchemaCompiler::class)->compile([
        'fields' => [
            [
                'key' => 'items',
                'type' => 'list',
                'label' => 'Items',
                'item' => ['type' => 'text'],
            ],
            [
                'key' => 'body',
                'type' => 'rich_text',
                'label' => 'Body',
            ],
            [
                'key' => 'payload',
                'type' => 'json',
                'label' => 'Payload',
                'settings' => [
                    'schema' => [
                        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                        'type' => 'object',
                        'properties' => [
                            'enabled' => ['type' => 'boolean'],
                        ],
                        'required' => ['enabled'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'key' => 'linkedPayload',
                'type' => 'json',
                'label' => 'Linked payload',
                'settings' => [
                    'schema' => [
                        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                        '$defs' => [
                            'code' => ['type' => 'string', 'minLength' => 2],
                        ],
                        'type' => 'object',
                        'properties' => [
                            'code' => ['$ref' => '#/$defs/code'],
                        ],
                        'required' => ['code'],
                    ],
                ],
            ],
            [
                'key' => 'image',
                'preset' => 'image',
                'label' => 'Image',
            ],
        ],
    ]);
    $generated = app(ContentJsonSchemaBuilder::class)->definition(
        'schema-parity',
        1,
        $schema,
    );
    $jsonValidator = app(Validator::class);

    /** @param array<string, mixed> $value */
    $isValidGeneratedValue = static function (array $value) use (
        $generated,
        $jsonValidator,
    ): bool {
        $data = json_decode(
            json_encode($value, JSON_THROW_ON_ERROR),
            false,
            flags: JSON_THROW_ON_ERROR,
        );
        $schemaObject = json_decode(
            json_encode($generated, JSON_THROW_ON_ERROR),
            false,
            flags: JSON_THROW_ON_ERROR,
        );

        return $jsonValidator->validate($data, $schemaObject)->isValid();
    };

    expect($generated['properties']['items']['maxItems'])->toBe(2)
        ->and($generated['properties']['body']['maxLength'])->toBe(5)
        ->and($isValidGeneratedValue(['items' => ['one', 'two']]))->toBeTrue()
        ->and($isValidGeneratedValue(['items' => ['one', 'two', 'three']]))->toBeFalse()
        ->and($isValidGeneratedValue(['body' => '12345']))->toBeTrue()
        ->and($isValidGeneratedValue(['body' => '123456']))->toBeFalse()
        ->and($isValidGeneratedValue(['payload' => null]))->toBeTrue()
        ->and($isValidGeneratedValue(['payload' => ['enabled' => true]]))->toBeTrue()
        ->and($isValidGeneratedValue(['linkedPayload' => ['code' => 'ok']]))->toBeTrue()
        ->and($isValidGeneratedValue(['linkedPayload' => ['code' => 'x']]))->toBeFalse()
        ->and($isValidGeneratedValue([
            'image' => [
                'media' => '2ff49e0a-c3ae-4d26-a81b-722a422241ca',
                'alt' => 'An accessible image',
            ],
        ]))->toBeTrue()
        ->and($isValidGeneratedValue([
            'image' => [
                'media' => '2ff49e0a-c3ae-4d26-a81b-722a422241ca',
                'alt' => " \t ",
            ],
        ]))->toBeFalse();

    $runtimeValidator = app(ContentValueValidator::class);

    /** @param array<string, mixed> $values */
    $runtimeValues = static fn (array $values) => $runtimeValidator->validate(
        schema: $schema,
        values: $values,
        translations: [],
        actor: ContentActorData::system(),
        visibility: ContentVisibility::Private,
    );

    expect($runtimeValues(['items' => ['one', 'two']])->values['items'])
        ->toBe(['one', 'two'])
        ->and(fn () => $runtimeValues(['items' => ['one', 'two', 'three']]))
        ->toThrow(InvalidArgumentException::class)
        ->and($runtimeValues(['body' => '12345'])->values['body'])
        ->toBe('12345')
        ->and(fn () => $runtimeValues(['body' => '123456']))
        ->toThrow(InvalidArgumentException::class)
        ->and($runtimeValues(['payload' => null])->values['payload'])
        ->toBeNull()
        ->and($runtimeValues(['payload' => ['enabled' => true]])->values['payload'])
        ->toBe(['enabled' => true])
        ->and($runtimeValues(['linkedPayload' => ['code' => 'ok']])->values['linkedPayload'])
        ->toBe(['code' => 'ok'])
        ->and(fn () => $runtimeValues(['linkedPayload' => ['code' => 'x']]))
        ->toThrow(InvalidArgumentException::class);

    $image = $schema->get('image');

    expect($image)->toBeInstanceOf(ContentFieldDefinition::class);

    if (! $image instanceof ContentFieldDefinition) {
        return;
    }

    $imagePreset = app(ContentFieldPresetRegistry::class)->get('image');
    $publishingContext = new ContentValidationContext(
        actor: ContentActorData::system(),
        locale: 'en',
        path: 'image',
        visibility: ContentVisibility::Public,
        publishing: true,
    );

    $imagePreset->validate(
        [
            'media' => '2ff49e0a-c3ae-4d26-a81b-722a422241ca',
            'alt' => 'An accessible image',
        ],
        $image,
        $publishingContext,
    );

    expect(fn () => $imagePreset->validate(
        [
            'media' => '2ff49e0a-c3ae-4d26-a81b-722a422241ca',
            'alt' => " \t ",
        ],
        $image,
        $publishingContext,
    ))->toThrow(InvalidArgumentException::class);
});

it('keeps local JSON references scoped to each nested content field', function (): void {
    $schema = app(ContentSchemaCompiler::class)->compile([
        'fields' => array_map(
            static fn (array $field): array => [
                'key' => $field['key'],
                'type' => 'object',
                'fields' => [[
                    'key' => 'payload',
                    'type' => 'json',
                    'settings' => [
                        'schema' => [
                            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                            '$defs' => [
                                'code' => ['type' => 'string', 'minLength' => $field['minimum']],
                            ],
                            '$ref' => '#/$defs/code',
                        ],
                    ],
                ]],
            ],
            [
                ['key' => 'left', 'minimum' => 2],
                ['key' => 'right', 'minimum' => 4],
            ],
        ),
    ]);
    $generated = app(ContentJsonSchemaBuilder::class)->definition('nested-json', 1, $schema);
    $validator = app(Validator::class);
    $schemaObject = json_decode(json_encode($generated, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);

    $valid = json_decode('{"left":{"payload":"ab"},"right":{"payload":"abcd"}}');
    $invalid = json_decode('{"left":{"payload":"ab"},"right":{"payload":"abc"}}');

    expect($validator->validate($valid, $schemaObject)->isValid())->toBeTrue()
        ->and($validator->validate($invalid, $schemaObject)->isValid())->toBeFalse();
});

it('describes required content fields as non-empty published values', function (): void {
    $schema = app(ContentSchemaCompiler::class)->compile([
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
            ['key' => 'items', 'type' => 'list', 'required' => true, 'item' => ['type' => 'text']],
            ['key' => 'details', 'type' => 'object', 'required' => true, 'fields' => [
                ['key' => 'note', 'type' => 'text'],
            ]],
        ],
    ]);
    $generated = app(ContentJsonSchemaBuilder::class)->definition('required-values', 1, $schema);
    $schemaObject = json_decode(json_encode($generated, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
    $validator = app(Validator::class);

    foreach ([
        ['title' => '', 'items' => ['one'], 'details' => ['note' => 'ok']],
        ['title' => '   ', 'items' => ['one'], 'details' => ['note' => 'ok']],
        ['title' => 'ok', 'items' => [], 'details' => ['note' => 'ok']],
        ['title' => 'ok', 'items' => ['one'], 'details' => []],
    ] as $values) {
        $value = json_decode(json_encode($values, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);

        expect($validator->validate($value, $schemaObject)->isValid())->toBeFalse();
    }

    $valid = json_decode('{"title":"ok","items":["one"],"details":{"note":"ok"}}');

    expect($validator->validate($valid, $schemaObject)->isValid())->toBeTrue();
});

it('exports collection bounds and uniqueness for Media and reference fields', function (): void {
    $schema = app(ContentSchemaCompiler::class)->compile([
        'fields' => [
            [
                'key' => 'attachments',
                'type' => 'media_collection',
                'required' => true,
                'settings' => ['max_items' => 2],
            ],
            [
                'key' => 'related',
                'type' => 'reference_list',
                'required' => true,
                'settings' => ['reference_type' => 'article', 'max_items' => 2],
            ],
        ],
    ]);
    $generated = app(ContentJsonSchemaBuilder::class)->definition('collections', 1, $schema);
    $attachments = $generated['properties']['attachments'];
    $related = $generated['properties']['related'];

    expect($attachments['minItems'])->toBe(1)
        ->and($attachments['maxItems'])->toBe(2)
        ->and($attachments['items']['format'])->toBe('uuid')
        ->and($attachments['uniqueItems'])->toBeTrue()
        ->and($related['minItems'])->toBe(1)
        ->and($related['maxItems'])->toBe(2)
        ->and($related['items']['maxLength'])->toBe(191)
        ->and($related['uniqueItems'])->toBeTrue();

    $validator = app(Validator::class);
    $schemaObject = json_decode(json_encode($generated, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
    $valid = json_decode('{"attachments":["2ff49e0a-c3ae-4d26-a81b-722a422241ca"],"related":["article:1"]}');
    $duplicate = json_decode('{"attachments":["2ff49e0a-c3ae-4d26-a81b-722a422241ca","2ff49e0a-c3ae-4d26-a81b-722a422241ca"],"related":["article:1"]}');
    $empty = json_decode('{"attachments":[],"related":[]}');

    expect($validator->validate($valid, $schemaObject)->isValid())->toBeTrue()
        ->and($validator->validate($duplicate, $schemaObject)->isValid())->toBeFalse()
        ->and($validator->validate($empty, $schemaObject)->isValid())->toBeFalse();
});
