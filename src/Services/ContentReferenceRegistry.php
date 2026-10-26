<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentReferenceResolver;
use Nvl\Content\Contracts\OwnershipSafeContentReferenceResolver;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Content\Validation\ContentValidationContext;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

/**
 * Allowlist for schema-declared references.
 */
final class ContentReferenceRegistry
{
    /** @var array<string, class-string<ContentReferenceResolver>> */
    private array $resolvers = [];

    /** Create the reference allowlist and its tenant-safety boundary. */
    public function __construct(private readonly Container $container, private readonly ContentPayloadGuard $guard, private readonly OwnershipBoundary $tenancy) {}

    /** Register one stable reference alias and compatible resolver class. */
    public function register(string $alias, string $resolver): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,99}$/', $alias) !== 1) {
            throw new InvalidArgumentException("Content reference alias [{$alias}] is invalid.");
        }
        if (isset($this->resolvers[$alias])) {
            throw new InvalidArgumentException("Content reference [{$alias}] is already registered.");
        }
        if (! is_a($resolver, ContentReferenceResolver::class, true)) {
            throw new InvalidArgumentException('Content reference resolvers must implement ContentReferenceResolver.');
        }
        $this->resolvers[$alias] = $resolver;
        ksort($this->resolvers);
    }

    /** Determine whether the named reference capability is registered. */
    public function has(string $alias): bool
    {
        return isset($this->resolvers[$alias]);
    }

    /** Reject reference aliases outside the registered allowlist. */
    public function assertRegistered(string $alias): void
    {
        if (! $this->has($alias)) {
            throw new InvalidArgumentException("Content reference type [{$alias}] is not registered.");
        }
    }

    /** Admit the exact canonical reference before checking availability. */
    public function assertExists(string $alias, string $identifier, ContentValidationContext $context): void
    {
        $resolver = $this->resolver($alias);
        $this->assertTenantRecord($resolver, $identifier, $context);
        if (! $resolver->exists($identifier, $context)) {
            throw new InvalidArgumentException("Content reference [{$alias}:{$identifier}] does not exist or is unavailable.");
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function display(string $alias, string $identifier, ContentValidationContext $context): ?array
    {
        $resolver = $this->resolver($alias);
        $this->assertTenantRecord($resolver, $identifier, $context);
        $display = $resolver->display($identifier, $context);
        if ($display !== null) {
            $this->guard->referenceDisplay($display);
        }

        return $display;
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return array_keys($this->resolvers);
    }

    /**
     * Identify registered resolver classes without invoking their factories.
     *
     * @internal
     *
     * @return array<string, class-string<ContentReferenceResolver>>
     */
    public function compilationIdentities(): array
    {
        return $this->resolvers;
    }

    /** Native Content operation. */
    private function resolver(string $alias): ContentReferenceResolver
    {
        $this->assertRegistered($alias);
        $class = $this->resolvers[$alias] ?? throw new InvalidArgumentException("Content reference type [{$alias}] is unavailable.");
        $resolver = $this->container->make($class);
        if (! $resolver instanceof ContentReferenceResolver || $resolver->alias() !== $alias) {
            throw new InvalidArgumentException("Content reference resolver [{$class}] is invalid.");
        }

        return $resolver;
    }

    /** Deny legacy or foreign dynamic resolvers before existence/display output is consumed. */
    private function assertTenantRecord(ContentReferenceResolver $resolver, string $identifier, ContentValidationContext $context): void
    {
        if (ContentOwnership::declared()) {
            if (! $resolver instanceof OwnershipSafeContentReferenceResolver) {
                throw new OwnershipViolation('Owned Content references require their canonical record adapter.');
            }
            $record = $resolver->ownershipRecord($identifier, $context);
            if (! $record instanceof Model) {
                throw new OwnershipViolation('A Content reference is unavailable in the admitted owner.');
            }
            $key = $record->getKey();
            if (! $record->exists || ! is_string($key) && ! is_int($key) || (string) $key !== $identifier || $record->isDirty($record->getKeyName())) {
                throw new OwnershipViolation('Content references require the exact requested persisted key.');
            }
            $resource = ContentOwnership::resource($record);
            $this->tenancy->assertRecord($record, $resource);
            $fresh = $this->tenancy->query($record->newQuery(), $resource)->whereKey($key)->first();
            if ($fresh === null || $fresh->getKey() !== $key) {
                throw new OwnershipViolation('Content reference no longer exists in canonical storage.');
            }

            return;
        }
    }
}
