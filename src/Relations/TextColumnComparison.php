<?php

declare(strict_types=1);

namespace Nvl\Content\Relations;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * Internal expression comparing two grammar-wrapped package-owned columns.
 */
final readonly class TextColumnComparison implements Expression
{
    /** Native Content operation. */
    public function __construct(private string $left, private string $right) {}

    /** Native Content operation. */
    public function getValue(Grammar $grammar): string
    {
        return "{$this->left} = {$this->right}";
    }
}
