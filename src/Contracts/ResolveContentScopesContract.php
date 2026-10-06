<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentScopeData;
use Nvl\Content\Data\ContentScopeResolutionData;

/**
 * Defines the supported resolve content scopes workflow.
 *
 * @api
 */
interface ResolveContentScopesContract
{
    /**
     * Resolve unique block keys using the first matching scope as highest priority.
     *
     * @param  list<ContentScopeData>  $scopes
     */
    public function execute(
        array $scopes,
        string $locale,
        ContentActorData $actor,
        ?int $limit = null,
        bool $publicOnly = true,
    ): ContentScopeResolutionData;
}
