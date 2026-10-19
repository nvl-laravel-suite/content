<?php

declare(strict_types=1);

namespace Nvl\Content\Ownership;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Nvl\Ownership\Contracts\OwnershipAdoptionAdapter;
use Nvl\Ownership\Services\OwnershipAdoptionStore;
use Nvl\Ownership\Services\OwnershipDatabaseConstraints;
use Nvl\Ownership\Services\OwnershipSchema;
use Nvl\Ownership\ValueObjects\OwnershipAdoptionPlan;
use Nvl\Ownership\ValueObjects\OwnershipBackfillResult;
use Nvl\Ownership\ValueObjects\OwnershipVerification;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;

/**
 * Adopts exact reviewed Content records without guessing primary owners from associations.
 *
 * @internal
 */
final readonly class ContentAdoptionAdapter implements OwnershipAdoptionAdapter
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(
        private OwnershipResourceRegistry $resources,
        private OwnershipAdoptionStore $mappings,
        private OwnershipSchema $schema,
        private OwnershipDatabaseConstraints $constraints,
    ) {}

    /** @return list<string> */
    public function resources(): array
    {
        return ['content.blocks', 'content.translations', 'content.revisions', 'content.placements'];
    }

    /** Prepare additive partitions and replace only the package's known global receipt identity. */
    public function prepare(OwnershipAdoptionPlan $plan): void
    {
        $root = new ($this->resources->get('content.blocks')->model);
        $connection = $root->getConnection();
        $schema = $connection->getSchemaBuilder();
        foreach ($this->resources() as $key) {
            $model = new ($this->resources->get($key)->model);
            foreach (['tenant_id', 'ownership_key'] as $column) {
                if ($schema->hasColumn($model->getTable(), $column)) {
                    $schema->table($model->getTable(), static fn (Blueprint $table) => ($column === 'tenant_id' ? $table->uuid($column) : $table->string($column, 44))->nullable()->change());
                }
            }
            $this->schema->prepare($key);
        }
        foreach ([['content.translations', 'content_block_id'], ['content.revisions', 'content_block_id'], ['content.placements', 'content_block_id']] as [$childKey,$column]) {
            $child = new ($this->resources->get($childKey)->model);
            $found = false;
            foreach ($schema->getForeignKeys($child->getTable()) as $foreign) {
                if ($foreign['columns'] === [$column] && $foreign['foreign_table'] === $root->getTable() && $foreign['foreign_columns'] === ['id']) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $name = 'content_native_'.substr(hash('sha256', $child->getTable().$column), 0, 24);
                $schema->table($child->getTable(), static fn (Blueprint $table) => $table->foreign([$column], $name)->references(['id'])->on($root->getTable())->restrictOnDelete());
            }
        }
        foreach ($schema->getIndexes($root->getTable()) as $index) {
            if ($index['unique'] && in_array($index['columns'], [['scope', 'scope_key', 'key'], ['tenant_id', 'scope', 'scope_key', 'key']], true)) {
                $schema->table($root->getTable(), static fn (Blueprint $table) => $table->dropUnique($index['name']));
            }
        }
        $placement = new ($this->resources->get('content.placements')->model);
        $identity = ['id', 'owner_type', 'owner_id', 'group'];
        $this->unique($placement, $identity, 'content_native_placement_parent_identity_unique');
        $childColumns = ['parent_id', 'owner_type', 'owner_id', 'group'];
        $foundParent = false;
        foreach ($schema->getForeignKeys($placement->getTable()) as $foreign) {
            if ($foreign['columns'] === $childColumns && $foreign['foreign_table'] === $placement->getTable() && $foreign['foreign_columns'] === $identity) {
                $foundParent = true;
                break;
            }
        }
        if (! $foundParent) {
            $schema->table($placement->getTable(), static fn (Blueprint $table) => $table->foreign($childColumns, 'content_native_placement_parent_foreign')->references($identity)->on($placement->getTable())->restrictOnDelete());
        }
        $this->unique($root, ['owner_scope_key', 'scope', 'scope_key', 'key'], 'content_owner_scope_key_unique');
        $locale = new ($this->resources->get('content.translations')->model);
        $this->unique($locale, ['owner_scope_key', 'content_block_id', 'locale'], 'content_owner_locale_unique');
    }

    /** @param list<string> $columns */
    private function unique(Model $model, array $columns, string $name): void
    {
        $schema = $model->getConnection()->getSchemaBuilder();
        foreach ($schema->getIndexes($model->getTable()) as $index) {
            if ($index['unique'] && $index['columns'] === $columns) {
                return;
            }
        }
        $schema->table($model->getTable(), static fn (Blueprint $table) => $table->unique($columns, $name));
    }

    /** Backfill bounded actual records from explicit immutable per-record mappings. */
    public function backfill(OwnershipAdoptionPlan $plan, ?string $cursor, int $limit): OwnershipBackfillResult
    {
        $processed = 0;
        do {
            $result = $this->backfillResource($plan, $cursor, $limit - $processed);
            $processed += $result->processed;
            $cursor = $result->nextCursor;
        } while ($cursor !== null && $processed < $limit);

        return new OwnershipBackfillResult($cursor, $processed);
    }

    /** Consume at most one resource page without counting empty tables as progress. */
    private function backfillResource(OwnershipAdoptionPlan $plan, ?string $cursor, int $limit): OwnershipBackfillResult
    {
        $parts = $cursor === null ? ['0', ''] : explode(':', $cursor, 2);
        if (count($parts) !== 2 || ! ctype_digit($parts[0])) {
            throw new OwnershipConfigurationInvalid('Invalid Content adoption cursor.');
        }
        $keys = $this->resources();
        $position = (int) $parts[0];
        $key = $keys[$position] ?? throw new OwnershipConfigurationInvalid('Invalid Content adoption resource.');
        $definition = $this->resources->get($key);
        $model = new $definition->model;
        $connection = $model->getConnection();
        $rows = $connection->table($model->getTable())->when($parts[1] !== '', static fn ($query) => $query->where('id', '>', $parts[1]))->orderBy('id')->limit($limit)->get();
        $last = '';
        $connection->transaction(function () use ($rows, $key, $plan, $definition, $model, $connection, &$last): void {
            foreach ($rows as $row) {
                if (! is_string($row->id)) {
                    throw new OwnershipConfigurationInvalid('Content adoption requires canonical UUID records.');
                }
                $mapping = $this->mappings->forRecord($plan, $key, $row->id);
                $owner = $mapping->owner;
                if (isset($row->tenant_id) && $mapping->legacyTenantId !== $row->tenant_id) {
                    throw new OwnershipConfigurationInvalid('Reviewed Content mappings must preserve the original tenant identity.');
                }
                $nativeOwner = $definition->ownership->foreignKey === null ? null : ($row->{$definition->ownership->foreignKey} ?? null);
                if ($nativeOwner !== null && ((! is_int($nativeOwner) && ! is_string($nativeOwner)) || (string) $nativeOwner !== $owner?->key)) {
                    throw new OwnershipConfigurationInvalid('Content adoption cannot relabel an existing native primary owner.');
                }
                $attributes = ['owner_scope_key' => $owner?->scopeKey() ?? 'central'];
                $ownership = $definition->ownership;
                if ($ownership->strategy === 'captured') {
                    $attributes['owner_reference'] = $owner === null ? null : json_encode($owner->toArray(), JSON_THROW_ON_ERROR);
                } elseif ($ownership->strategy === 'column') {
                    $attributes[$ownership->foreignKey ?? throw new OwnershipConfigurationInvalid] = $owner?->key;
                } elseif ($ownership->strategy === 'pivot' && $owner !== null) {
                    $connection->table($ownership->table ?? throw new OwnershipConfigurationInvalid)->insertOrIgnore([
                        $ownership->resourceKey ?? throw new OwnershipConfigurationInvalid => $row->id,
                        $ownership->ownerKey ?? throw new OwnershipConfigurationInvalid => $owner->key,
                        'owner_scope_key' => $owner->scopeKey(),
                    ]);
                }
                $connection->table($model->getTable())->where('id', $row->id)->update($attributes);
                $last = $row->id;
            }
        });
        $next = $rows->count() === $limit ? $position.':'.$last : ($position + 1 < count($keys) ? ($position + 1).':' : null);

        return new OwnershipBackfillResult($next, $rows->count());
    }

    /** Verify persisted partitions and the complete canonical domain graph. */
    public function verify(OwnershipAdoptionPlan $plan): OwnershipVerification
    {
        foreach ($this->resources() as $key) {
            $this->schema->verify($key);
        }

        $root = new ($this->resources->get('content.blocks')->model);
        $placement = new ($this->resources->get('content.placements')->model);
        $connection = $root->getConnection();
        $table = $placement->getTable();
        $bad = $connection->table($table.' as child')->leftJoin($root->getTable().' as block', 'block.id', '=', 'child.content_block_id')->where(static fn (Builder $q) => $q->whereNull('block.id')->orWhereColumn('block.owner_scope_key', '!=', 'child.owner_scope_key'))->exists();
        $bad = $bad || $connection->table($table.' as child')->leftJoin($table.' as parent', 'parent.id', '=', 'child.parent_id')->whereNotNull('child.parent_id')->where(static fn (Builder $q) => $q->whereNull('parent.id')->orWhereColumn('parent.owner_scope_key', '!=', 'child.owner_scope_key')->orWhereColumn('parent.owner_type', '!=', 'child.owner_type')->orWhereColumn('parent.owner_id', '!=', 'child.owner_id')->orWhereColumn('parent.group', '!=', 'child.group'))->exists();
        if ($bad) {
            throw new OwnershipConfigurationInvalid('Content placement graphs must retain their canonical host, block and owner.');
        }

        return new OwnershipVerification([]);
    }

    /** Install native graph constraints before activation can commit. */
    public function activate(OwnershipAdoptionPlan $plan): void
    {
        foreach ($this->resources() as $key) {
            $this->schema->constrain($key);
        }
        $root = new ($this->resources->get('content.blocks')->model);
        $placement = new ($this->resources->get('content.placements')->model);
        $this->constraints->reference($root->getConnection(), $placement->getTable(), 'content_block_id', $root->getTable(), 'id');
        $this->constraints->reference($root->getConnection(), $placement->getTable(), 'parent_id', $placement->getTable(), 'id', true);
        $targets = [];
        foreach ($this->resources->get('content.placements')->parents() as $key) {
            $parent = new ($this->resources->get($key)->model);
            $targets[] = ['type' => $parent->getMorphClass(), 'table' => $parent->getTable(), 'key' => $parent->getKeyName()];
        }
        if ($targets === []) {
            throw new OwnershipConfigurationInvalid('Owned Content placements require canonical host resources.');
        }
        $this->constraints->polymorphicReference($root->getConnection(), $placement->getTable(), 'owner_type', 'owner_id', $targets);
    }
}
