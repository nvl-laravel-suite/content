<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Services\ContentBlocks;
use Nvl\Content\Services\ContentPlacements;
use Nvl\Ownership\Services\OwnershipWriter;
use Nvl\Ownership\ValueObjects\OwnershipAssignment;
use Nvl\Support\Enums\SystemActor;

/** Creates canonical mutable content records through real package workflows. */
final class OwnershipContentScenario
{
    public static function block(string $key = 'copy'): ContentBlock
    {
        return app(ContentBlocks::class)->create(['definition' => 'copy', 'key' => $key, 'translations' => ['en' => ['text' => 'Private copy']]], SystemActor::Background);
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
        return app(ContentPlacements::class)->place($block, $host, 'main', ['key' => 'body', 'region' => 'main'], SystemActor::Background);
    }
}
