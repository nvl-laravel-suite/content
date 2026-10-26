<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Spatie\LaravelData\Data;

/**
 * One exact Content scope in caller-defined fallback order.
 *
 * @api
 */
final class ContentScopeData extends Data
{
    public function __construct(public readonly string $scope, public readonly string $scopeKey) {}
}
