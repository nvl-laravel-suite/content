<?php

declare(strict_types=1);

namespace Nvl\Content\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compares textual placement identities with native UUID, integer or string owner keys.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends BelongsTo<TRelatedModel, TDeclaringModel>
 *
 * @internal
 */
final class StringBelongsTo extends BelongsTo
{
    /**
     * Build the inverse existence query without relying on implicit database casts.
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  Builder<TDeclaringModel>  $parentQuery
     * @param  array<int, string>|string  $columns
     * @return Builder<TRelatedModel>
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        if ($parentQuery->getQuery()->from === $query->getQuery()->from) {
            $query->from($query->getModel()->getTable().' as '.$hash = $this->getRelationCountHash());
            $query->getModel()->setTable($hash);
        }

        $grammar = $query->getQuery()->getGrammar();
        $foreign = $grammar->wrap($this->getQualifiedForeignKeyName());
        $owner = $grammar->wrap($query->qualifyColumn($this->ownerKey));
        $type = match ($query->getModel()->getConnection()->getDriverName()) {
            'pgsql', 'sqlite' => 'TEXT',
            'sqlsrv' => 'NVARCHAR(255)',
            default => 'CHAR',
        };

        return $query->select($columns)->whereRaw(new TextColumnComparison(
            "CAST({$foreign} AS {$type})", "CAST({$owner} AS {$type})",
        ));
    }
}
