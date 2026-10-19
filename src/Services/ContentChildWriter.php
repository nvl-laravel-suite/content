<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;

/**
 * Coordinates package-owned native child creation under the exact registered source assignment.
 *
 * @internal
 */
final readonly class ContentChildWriter
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(private OwnershipWriter $writer) {}

    /** @param Closure():bool $persist */
    public function save(Model $model, Closure $persist): bool
    {
        $saved = false;
        $this->writer->create($model::class, static function (array $attributes) use ($model, $persist, &$saved): Model {
            $model->forceFill($attributes);
            $saved = $persist();

            return $model;
        });

        return $saved;
    }
}
