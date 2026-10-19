<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Nvl\Content\Models\ContentBlock;
use Nvl\Support\Models\ConfiguredModelResolver;
use Nvl\Support\OwnerRegistry;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;

/**
 * Resolves the one configured ContentBlock extension through the shared native model identity.
 *
 * @api
 */
final class ContentModelRegistry
{
    /** @var class-string<ContentBlock>|null */
    private ?string $definition = null;

    private bool $validated = false;

    /** Retain deployment configuration and the shared identity registry. */
    public function __construct(
        private readonly Repository $configuration,
        private readonly OwnerRegistry $identities,
    ) {}

    /** Resolve and freeze the concrete ContentBlock class before resource compilation.
     * @internal
     *
     * @return class-string<ContentBlock>
     */
    public function prepare(): string
    {
        if ($this->definition !== null) {
            return $this->definition;
        }
        $configured = $this->configuration->get('nvl-content.models.block', ContentBlock::class);
        if (! is_string($configured)) {
            throw new OwnershipConfigurationInvalid('ContentBlock model configuration must name a concrete ContentBlock subclass.');
        }
        $class = (new ConfiguredModelResolver)->resolve($configured, ContentBlock::class);
        if (! in_array($class::ownershipDefinition()->strategy, ['unscoped', 'column', 'pivot'], true)) {
            throw new OwnershipConfigurationInvalid('ContentBlock roots require a native column or concrete primary pivot.');
        }
        $this->identities->registerReplacement('content.blocks', ContentBlock::class, $class);
        $this->definition = $class;

        return $class;
    }

    /** Validate native capabilities after Laravel installs Eloquent's dispatcher.
     * @return class-string<ContentBlock>
     */
    public function blockClass(): string
    {
        $class = $this->prepare();
        if ($this->validated) {
            return $class;
        }
        $model = new $class;
        $base = new ContentBlock;
        (new ConfiguredModelResolver)->assertOwnershipCapabilities($model, $base);
        $this->validated = true;

        return $class;
    }

    /** Resolve the immutable model from native framework entry points.
     * @internal
     *
     * @return class-string<ContentBlock>
     */
    public static function modelClass(): string
    {
        return Container::getInstance()->make(self::class)->blockClass();
    }

    /** Construct the configured root.
     * @internal
     *
     * @param  array<string,mixed>  $attributes
     */
    public static function newInstance(array $attributes = []): ContentBlock
    {
        $class = Container::getInstance()->make(self::class)->blockClass();

        return new $class($attributes);
    }

    /** Persist a package-authored attribute map under the exact source assignment.
     * @internal
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): ContentBlock
    {
        return app(OwnershipWriter::class)->create(self::modelClass(), static function (array $ownership) use ($attributes): ContentBlock {
            $model = self::newInstance();
            $model->forceFill([...$attributes, ...$ownership])->save();

            return $model;
        });
    }

    /** Keep primary ownership facts in bounded native projections.
     * @param  list<string>  $columns
     * @return list<string>
     */
    public static function projectionColumns(array $columns): array
    {
        $definition = self::modelClass()::ownershipDefinition();
        if ($definition->strategy === 'unscoped') {
            return $columns;
        }

        return array_values(array_unique([...$columns, 'owner_scope_key', ...($definition->foreignKey === null ? [] : [$definition->foreignKey])]));
    }

    /** Create the exact configured query.
     * @internal
     *
     * @return Builder<ContentBlock>
     */
    public static function query(): Builder
    {
        return self::newInstance()->newQuery();
    }
}
