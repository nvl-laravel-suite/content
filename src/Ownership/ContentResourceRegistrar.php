<?php

declare(strict_types=1);

namespace Nvl\Content\Ownership;

use InvalidArgumentException;
use Nvl\Content\Models\ContentBlockTranslation;
use Nvl\Content\Models\ContentDefinition;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Models\ContentRevision;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Ownership\ValueObjects\OwnershipDefinition;
use Nvl\Support\Ownership\ValueObjects\OwnershipResourceDefinition;

/**
 * Keeps code definitions central and compiles one exact mutable Content graph.
 *
 * @internal
 */
final readonly class ContentResourceRegistrar
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(private ContentModelRegistry $models) {}

    /** Register the canonical capabilities without capturing current admission. */
    public function register(OwnershipResourceRegistry $resources): void
    {
        $class = $this->models->blockClass();
        $owned = $class::ownershipDefinition()->strategy !== 'unscoped';
        $central = $owned && $class::allowsCentralCatalog();
        $parents = config('nvl-content.ownership.owner_resources', []);
        if (! is_array($parents) || ! array_is_list($parents)) {
            throw new InvalidArgumentException('Content hosts require explicit resource keys.');
        }
        foreach ($parents as $key) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Invalid Content host resource.');
            }
        }
        $resources->register(new OwnershipResourceDefinition('content.definitions', 'content', ContentDefinition::class, OwnershipDefinition::unscoped()));
        $resources->register(new OwnershipResourceDefinition('content.blocks', 'content', $class, $class::ownershipDefinition(), allowsCentral: $central));
        foreach (['content.translations' => ContentBlockTranslation::class, 'content.revisions' => ContentRevision::class] as $key => $model) {
            $resources->register(new OwnershipResourceDefinition($key, 'content', $model, $owned ? OwnershipDefinition::inherited('block') : OwnershipDefinition::unscoped(), parentResource: $owned ? 'content.blocks' : null, allowsCentral: $central));
        }
        $resources->register(new OwnershipResourceDefinition('content.placements', 'content', ContentPlacement::class, $owned ? OwnershipDefinition::inherited('owner') : OwnershipDefinition::unscoped(), parentResources: $owned ? $parents : [], allowsCentral: $central, immutableFields: $owned ? ['group'] : []));
    }
}
