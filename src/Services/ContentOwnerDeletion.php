<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentOwner;
use Nvl\Content\Exceptions\ContentOwnerDeletionCancelled;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Support\Ownership\Contracts\OwnershipModelPersistence;
use Throwable;

/**
 * Coordinates owner hard deletion with atomic placement and Media-association cleanup.
 */
final readonly class ContentOwnerDeletion
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(
        private ContentOwnerRegistry $owners,
        private ContentIdentityGuard $identities,
        private ContentPlacementOwnerLock $locks,
        private ContentMediaSynchronizer $media,
        private OwnershipModelPersistence $persistence,
    ) {}

    /**
     * Delete an owner and its Content usages under every declared composition lock.
     *
     * @param  Closure(): (bool|null)  $delete
     */
    public function execute(Model&ContentOwner $owner, Closure $delete): ?bool
    {
        try {
            $ownerType = $this->owners->type($owner);
        } catch (InvalidArgumentException $exception) {
            if (! $owner->contentPlacements()->exists()) {
                return $delete();
            }

            throw $exception;
        }
        $ownerId = $this->owners->id($owner);
        $this->identities->owner($ownerType, $ownerId);
        $groups = $this->owners->groups($owner);

        foreach ($owner->contentPlacements()->distinct()->pluck('group') as $group) {
            if (! is_string($group)) {
                throw new InvalidArgumentException('Persisted Content placement groups must be strings.');
            }

            $this->identities->group($group);
            $groups[] = $group;
        }

        $groups = array_values(array_unique($groups));
        sort($groups);

        return $this->withLocks($ownerType, $ownerId, $groups, function () use ($owner, $delete): ?bool {
            $connection = $owner->getConnection();
            $contentConnection = (new ContentPlacement)->getConnection();

            if ($connection->getName() !== $contentConnection->getName()) {
                if ($owner->contentPlacements()->exists()) {
                    throw new InvalidArgumentException(
                        'Content owners with placements must be deleted on the same named database connection as Content.',
                    );
                }

                return $delete();
            }

            $existed = $owner->exists;

            try {
                $perform = function () use ($owner, $delete, $connection): ?bool {
                    return $connection->transaction(function () use ($owner, $delete): ?bool {
                        $owner->newQuery()
                            ->withoutGlobalScope(SoftDeletingScope::class)
                            ->whereKey($owner->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();
                        $placements = $owner->contentPlacements()->orderBy('id')->lockForUpdate()->get();
                        if (ContentOwnership::declared()) {
                            $this->deleteOwnedPlacements($placements);
                            $deleted = $delete();
                            if ($deleted !== true) {
                                throw new ContentOwnerDeletionCancelled($deleted);
                            }

                            return true;
                        }
                        $deleted = $delete();

                        if ($deleted !== true) {
                            return $deleted;
                        }

                        foreach ($placements as $placement) {
                            $this->media->detachAll($placement);

                            if ($placement->delete() !== true) {
                                throw new InvalidArgumentException('Content placement deletion was canceled.');
                            }
                        }

                        return true;
                    });
                };

                return ContentOwnership::declared() ? $this->persistence->delete($owner, $perform) : $perform();
            } catch (Throwable $exception) {
                $owner->exists = $existed;
                if ($exception instanceof ContentOwnerDeletionCancelled) {
                    return $exception->result;
                }
                throw $exception;
            }
        });
    }

    /** Remove leaves before parents while the canonical host still exists.
     * @param  Collection<int,ContentPlacement>  $placements
     */
    private function deleteOwnedPlacements(Collection $placements): void
    {
        $remaining = $placements->keyBy('id');
        while ($remaining->isNotEmpty()) {
            $parents = [];
            foreach ($remaining as $placement) {
                if ($placement->parent_id !== null) {
                    $parents[$placement->parent_id] = true;
                }
            }
            $leaves = $remaining->reject(static fn (ContentPlacement $placement): bool => isset($parents[$placement->id]));
            if ($leaves->isEmpty()) {
                throw new InvalidArgumentException('Content placement deletion requires an acyclic canonical tree.');
            }
            foreach ($leaves as $placement) {
                $this->media->detachAll($placement);
                if ($placement->delete() !== true) {
                    throw new InvalidArgumentException('Content placement deletion was canceled.');
                }
            }
            $remaining = $remaining->except($leaves->modelKeys());
        }
    }

    /**
     * Acquire owner group locks in deterministic order before deleting any rows.
     *
     * @param  list<string>  $groups
     * @param  Closure(): (bool|null)  $callback
     */
    private function withLocks(string $ownerType, string $ownerId, array $groups, Closure $callback): ?bool
    {
        $group = array_shift($groups);

        return $group === null
            ? $callback()
            : $this->locks->run(
                $ownerType,
                $ownerId,
                $group,
                fn (): ?bool => $this->withLocks($ownerType, $ownerId, $groups, $callback),
            );
    }
}
