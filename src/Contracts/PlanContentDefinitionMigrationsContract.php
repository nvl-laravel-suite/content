<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentDefinitionMigrationPlanData;

/**
 * Defines the supported plan content definition migrations workflow.
 *
 * @api
 */
interface PlanContentDefinitionMigrationsContract
{
    /**
     * Execute the plan content definition migrations workflow.
     */
    public function execute(
        ContentActorData $actor,
        ?string $definition = null,
        ?int $limit = null,
    ): ContentDefinitionMigrationPlanData;
}
