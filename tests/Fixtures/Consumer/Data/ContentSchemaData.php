<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Schema\ContentSchema;

/**
 * Generated-client contract for a compiled Content schema.
 *
 * @api
 */
final class ContentSchemaData
{
    /**
     * @param  list<ContentFieldDefinitionData>  $fields
     */
    public function __construct(
        public readonly array $fields
    ) {}

    /**
     * Create a generated-client contract from one compiled domain schema.
     */
    public static function fromSchema(ContentSchema $schema): self
    {
        return new self(array_map(ContentFieldDefinitionData::fromDefinition(...), $schema->fields));
    }

    /**
     * Restore the immutable domain schema represented by this transport contract.
     */
    public function toSchema(): ContentSchema
    {
        return new ContentSchema(array_map(static fn (ContentFieldDefinitionData $field) => $field->toDefinition(), $this->fields));
    }

    /** @return array<string, mixed> Consumer-owned response fields. */
    public function toArray(): array
    {
        return [
            'fields' => array_map(static fn (ContentFieldDefinitionData $field): array => $field->toArray(), $this->fields),
        ];
    }
}
