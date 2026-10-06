<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentOwner;
use Nvl\Content\Contracts\ContentOwnerRegistrar;
use Nvl\Support\OwnerRegistry;
use Nvl\Support\Tenancy\Contracts\TenantBoundary;
use Nvl\Support\Tenancy\Services\TenantResourceRegistry;

/**
 * Declares Content capabilities while preserving native Laravel owner identities.
 */
final class ContentOwnerRegistry implements ContentOwnerRegistrar
{
    /** @var array<string, class-string<Model&ContentOwner>> */
    private array $models = [];

    /** Retain Content's allowlist, validation and native owner identity boundaries. */
    public function __construct(
        private readonly ContentIdentityGuard $identities,
        private readonly Repository $configuration,
        private readonly TenantBoundary $tenancy,
        private readonly TenantResourceRegistry $tenantResources,
        private readonly OwnerRegistry $identitiesRegistry,
    ) {}

    /**
     * Register one stable owner alias and its Content-capable Eloquent model.
     *
     * @param  string  $model  Shared alias or deprecated Eloquent class reference
     *
     * @throws InvalidArgumentException When the capability alias or model is already registered or invalid
     */
    public function register(string $alias, string $model): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,99}$/', $alias) !== 1 && ! is_a($alias, Model::class, true)) {
            throw new InvalidArgumentException("Content owner alias [{$alias}] is invalid.");
        }

        if (isset($this->models[$alias])) {
            throw new InvalidArgumentException("Content owner [{$alias}] is already registered.");
        }

        $reference = $model;
        $model = is_a($reference, Model::class, true) ? $reference : $this->identitiesRegistry->model($reference);

        if ($reference !== $model && $reference !== $alias) {
            throw new InvalidArgumentException("Content owner [{$alias}] must use its canonical shared alias [{$reference}].");
        }

        if (! is_a($model, ContentOwner::class, true)) {
            throw new InvalidArgumentException(
                "Content owner model [{$model}] must extend Model and implement ContentOwner.",
            );
        }

        $registeredAlias = array_search($model, $this->models, true);
        if (is_string($registeredAlias)) {
            throw new InvalidArgumentException("Content owner model [{$model}] is already registered as [{$registeredAlias}].");
        }

        /** @var Model&ContentOwner $owner */
        $owner = new $model;
        $this->groups($owner);
        $this->identitiesRegistry->reference($reference, "nvl-content.owners.{$alias}", $alias);
        $this->models[$alias] = $model;
        ksort($this->models);
    }

    /**
     * Resolve one allowlisted owner identity to its Eloquent model.
     */
    public function resolve(string $alias, string $identifier): Model&ContentOwner
    {
        return $this->resolveOwner($alias, $identifier, false);
    }

    /**
     * Resolve an owner of a retained placement, including reversible soft deletion.
     */
    public function resolveRetained(string $alias, string $identifier): Model&ContentOwner
    {
        return $this->resolveOwner($alias, $identifier, true);
    }

    /**
     * Return the stable registered alias for one persisted owner model.
     */
    public function type(Model&ContentOwner $owner): string
    {
        foreach ($this->models as $alias => $model) {
            if ($owner instanceof $model) {
                return $owner->getMorphClass();
            }
        }

        throw new InvalidArgumentException(
            'The supplied model is not a registered Content owner.',
        );
    }

    /**
     * Return and validate the portable persisted identifier for one owner.
     */
    public function id(Model&ContentOwner $owner): string
    {
        if (! $owner->exists) {
            throw new InvalidArgumentException('A Content owner must be persisted.');
        }

        $identifier = $owner->getKey();

        if (! is_int($identifier) && ! is_string($identifier)) {
            throw new InvalidArgumentException(
                'A Content owner identifier must be a string or integer.',
            );
        }

        $id = (string) $identifier;
        $this->identities->owner($this->type($owner), $id);

        $query = $owner->newQuery()->withoutGlobalScope(SoftDeletingScope::class);
        if (! (clone $query)->whereKey($identifier)->exists()) {
            throw new InvalidArgumentException(
                'The supplied Content owner no longer exists.',
            );
        }

        if ($this->configuration->get('nvl-tenancy.enabled') === true) {
            $canonical = $query->findOrFail($identifier);
            $resource = $this->tenantResources->forModel($canonical);
            $this->tenancy->assertRecord($canonical, $resource->key);
        }

        return $id;
    }

    /**
     * Return the Eloquent model registered for one stable owner alias.
     *
     * @return class-string<Model&ContentOwner>
     */
    public function model(string $alias): string
    {
        foreach ($this->models as $registeredModel) {
            if ((new $registeredModel)->getMorphClass() === $alias) {
                return $registeredModel;
            }
        }

        return $this->models[$alias]
            ?? throw new InvalidArgumentException(
                "Content owner [{$alias}] is not registered.",
            );
    }

    /**
     * Return the registered model for an alias, or null when it is available.
     *
     * @return class-string<Model&ContentOwner>|null
     */
    public function registered(string $alias): ?string
    {
        return $this->models[$alias] ?? null;
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return array_keys($this->models);
    }

    /**
     * Identify registered owner models without querying persisted owners.
     *
     * @internal
     *
     * @return array<string, class-string<Model&ContentOwner>>
     */
    public function compilationIdentities(): array
    {
        return $this->models;
    }

    /**
     * Return the owner’s validated composition groups.
     *
     * @return list<string>
     */
    public function groups(Model&ContentOwner $owner): array
    {
        $groups = $owner->contentGroups();
        $ownerClass = $owner::class;

        if ($groups === []) {
            throw new InvalidArgumentException(
                "Content owner [{$ownerClass}] must declare at least one composition group.",
            );
        }

        foreach ($groups as $group) {
            $this->identities->group($group);
        }

        if (count($groups) !== count(array_unique($groups))) {
            throw new InvalidArgumentException(
                "Content owner [{$ownerClass}] contains duplicate composition groups.",
            );
        }

        sort($groups);

        return $groups;
    }

    /**
     * Assert that one composition group is declared by the owner.
     */
    public function assertGroup(Model&ContentOwner $owner, string $group): void
    {
        $this->identities->group($group);

        if (! in_array($group, $this->groups($owner), true)) {
            $ownerClass = $owner::class;

            throw new InvalidArgumentException(
                "Content group [{$group}] is not declared by owner [{$ownerClass}].",
            );
        }
    }

    /**
     * Resolve an exact owner identity while preserving every non-deletion scope.
     */
    private function resolveOwner(string $alias, string $identifier, bool $withTrashed): Model&ContentOwner
    {
        $this->identities->owner($alias, $identifier);
        $class = $this->model($alias);
        $query = (new $class)->newQuery();

        if ($withTrashed) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        $owner = $query->find($identifier)
            ?? throw new InvalidArgumentException(
                "Content owner [{$alias}:{$identifier}] does not exist.",
            );

        if (! $owner instanceof ContentOwner) {
            throw new InvalidArgumentException(
                "Resolved Content owner [{$alias}] does not implement ContentOwner.",
            );
        }

        $key = $owner->getKey();

        if ((! is_int($key) && ! is_string($key)) || (string) $key !== $identifier) {
            throw new InvalidArgumentException(
                "Resolved content owner [{$alias}] does not match identifier [{$identifier}].",
            );
        }

        if ($this->configuration->get('nvl-tenancy.enabled') === true) {
            $resource = $this->tenantResources->forModel($owner);
            $this->tenancy->assertRecord($owner, $resource->key);
        }

        return $owner;
    }
}
