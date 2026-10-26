<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Deterministic definition synchronization plan and execution result.
 *
 * @api
 */
final class ContentDefinitionSyncPlanData extends Data
{
    use DataTransform;

    /**
     * @param  list<string>  $create
     * @param  list<string>  $update
     * @param  list<string>  $unchanged
     * @param  list<string>  $orphan
     */
    public function __construct(public readonly array $create, public readonly array $update, public readonly array $unchanged, public readonly array $orphan, public readonly bool $applied) {}
}
