<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Support\Collection;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentFieldPresetData;

/**
 * Defines the supported list content presets workflow.
 *
 * @api
 */
interface ListContentPresetsContract
{
    /**
     * Return every registered semantic field preset in deterministic order.
     *
     * @return Collection<int, ContentFieldPresetData>
     */
    public function execute(ContentActorData $actor): Collection;
}
