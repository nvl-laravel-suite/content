<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentDefinitionSyncPlanData;

/**
 * Defines the supported sync content definitions workflow.
 *
 * @api
 */
interface SyncContentDefinitionsContract
{
    /**
     * Execute the sync content definitions workflow.
     */
    public function execute(
        ContentActorData $actor,
        bool $dryRun = false,
    ): ContentDefinitionSyncPlanData;
}
