<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Nvl\Content\Actions\CreateContentBlockAction;
use Nvl\Content\Actions\PlaceContentBlockAction;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\Mutations\CreateContentBlockData;
use Nvl\Content\Data\Mutations\PlaceContentBlockData;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Ownership\Services\OwnershipWriter;
use Nvl\Ownership\ValueObjects\OwnershipAssignment;

/** Creates canonical mutable content records through real package workflows. */
final class OwnershipContentScenario
{
    public static function block(string $key = 'copy'): ContentBlock
    {
        return app(CreateContentBlockAction::class)->execute(CreateContentBlockData::from(['definition' => 'copy', 'key' => $key, 'translations' => ['en' => ['text' => 'Private copy']]]), ContentActorData::system());
    }

    public static function host(): OwnershipContentOwner
    {
        return app(OwnershipWriter::class)->create(OwnershipContentOwner::class, static function (OwnershipAssignment $assignment): OwnershipContentOwner {
            $model = new OwnershipContentOwner;
            $model->forceFill([...$assignment->attributes(), 'name' => 'Host'])->save();

            return $model;
        });
    }

    public static function place(ContentBlock $block, OwnershipContentOwner $host): ContentPlacement
    {
        return app(PlaceContentBlockAction::class)->execute($block, $host, 'main', PlaceContentBlockData::from(['key' => 'body', 'region' => 'main']), ContentActorData::system());
    }
}
