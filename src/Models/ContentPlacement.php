<?php

declare(strict_types=1);

namespace Nvl\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nvl\Content\Database\Factories\ContentPlacementFactory;
use Nvl\Content\Models\Concerns\GuardsTenantOwnership;
use Nvl\Content\Relations\StringBelongsTo;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Media\Contracts\HasMedia;
use Nvl\Media\Traits\InteractsWithMedia;
use Nvl\Support\Config\PackageStorage;

/**
 * Ordered placement of a reusable block within an allowlisted owner.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $content_block_id
 * @property string $owner_type
 * @property string $owner_id
 * @property string $group
 * @property string $key
 * @property string|null $parent_id
 * @property string $region
 * @property int $sort_order
 * @property bool $is_visible
 * @property array<string, mixed>|null $overrides
 * @property int $revision
 * @property-read ContentBlock $block
 * @property-read Model $owner
 * @property-read ContentPlacement|null $parent
 * @property-read Collection<int, ContentPlacement> $children
 *
 * @api
 */
final class ContentPlacement extends Model implements HasMedia
{
    use GuardsTenantOwnership;

    /** @use HasFactory<ContentPlacementFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMedia;

    public const string TENANT_RESOURCE = 'content.placements';

    public const string DEFAULT_GROUP = 'default';

    /** @var list<string> */
    protected $fillable = [
        'content_block_id',
        'owner_type',
        'owner_id',
        'group',
        'key',
        'parent_id',
        'region',
        'sort_order',
        'is_visible',
        'overrides',
        'revision',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'group' => self::DEFAULT_GROUP,
        'region' => 'main',
        'sort_order' => 0,
        'is_visible' => true,
        'revision' => 1,
    ];

    public function getTable(): string
    {
        return ContentConfiguration::table('placements');
    }

    public function getConnectionName(): ?string
    {
        return PackageStorage::connectionName($this->connection ?? PackageStorage::connection('content') ?? parent::getConnectionName());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
            'overrides' => 'array',
            'revision' => 'integer',
        ];
    }

    /**
     * Keep inverse polymorphic existence queries portable across native owner key types.
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $child
     * @param  string  $foreignKey
     * @param  string  $ownerKey
     * @param  string  $relation
     * @return BelongsTo<TRelatedModel, TDeclaringModel>
     */
    protected function newBelongsTo(Builder $query, Model $child, $foreignKey, $ownerKey, $relation): BelongsTo
    {
        return $foreignKey === 'owner_id'
            ? new StringBelongsTo($query, $child, $foreignKey, $ownerKey, $relation)
            : parent::newBelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }

    /**
     * @return BelongsTo<ContentBlock, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(ContentBlock::class, 'content_block_id');
    }

    /**
     * Return the registered model that owns this grouped placement.
     *
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }

    /**
     * @return BelongsTo<ContentPlacement, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ContentPlacement, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Return the package's runtime fixture factory.
     *
     * @internal
     */
    protected static function newFactory(): ContentPlacementFactory
    {
        return ContentPlacementFactory::new();
    }
}
