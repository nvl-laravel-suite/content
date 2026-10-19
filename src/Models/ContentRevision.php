<?php

declare(strict_types=1);

namespace Nvl\Content\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nvl\Content\Database\Factories\ContentRevisionFactory;
use Nvl\Content\Enums\ContentRevisionEvent;
use Nvl\Content\Models\Concerns\GuardsTenantOwnership;
use Nvl\Content\Models\Concerns\OwnsContentChild;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Support\Config\PackageStorage;
use Nvl\Support\Ownership\Contracts\Ownable;

/**
 * Immutable audit snapshot created by public block mutations.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $content_block_id
 * @property int $revision
 * @property ContentRevisionEvent $event
 * @property array<string, mixed> $snapshot
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property-read ContentBlock $block
 *
 * @api
 *
 * @nvl-consumer-read id
 */
final class ContentRevision extends Model implements Ownable
{
    use GuardsTenantOwnership;

    /** @use HasFactory<ContentRevisionFactory> */
    use HasFactory;

    use HasUuids;
    use OwnsContentChild;

    public const string TENANT_RESOURCE = 'content.revisions';

    /** @var list<string> */
    protected $fillable = [
        'content_block_id',
        'revision',
        'event',
        'snapshot',
        'actor_type',
        'actor_id',
    ];

    /** Resolve the configured native table owned by this model. */
    public function getTable(): string
    {
        return ContentConfiguration::table('revisions');
    }

    /** Resolve the actual package source connection. */
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
            'revision' => 'integer',
            'event' => ContentRevisionEvent::class,
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ContentBlock, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(ContentModelRegistry::modelClass(), 'content_block_id');
    }

    /**
     * Return the package's runtime fixture factory.
     *
     * @internal
     */
    protected static function newFactory(): ContentRevisionFactory
    {
        return ContentRevisionFactory::new();
    }
}
