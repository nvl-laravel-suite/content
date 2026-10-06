<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentDefinitionMigrationPlanData;
use Nvl\Content\Data\ContentDefinitionMigrationResultData;

/**
 * Defines the supported apply content definition migrations workflow.
 *
 * @api
 */
interface ApplyContentDefinitionMigrationsContract
{
    /**
     * Execute the apply content definition migrations workflow.
     */
    public function execute(
        ContentDefinitionMigrationPlanData $plan,
        ContentActorData $actor,
    ): ContentDefinitionMigrationResultData;
}
