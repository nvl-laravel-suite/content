<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Config\Repository;
use Nvl\Support\Tenancy\Contracts\TenantContext;
use Nvl\Support\Tenancy\Enums\TenantContextMode;
use Nvl\Support\Tenancy\Exceptions\TenantBoundaryViolation;

/** Restricts mutation of deployment-owned Content definitions to explicit platform execution. */
final readonly class ContentPlatformCatalogGuard
{
    /** Create the catalog admission guard. */
    public function __construct(private Repository $configuration, private TenantContext $context) {}

    /** Require explicit platform context whenever tenancy is enabled. */
    public function authorize(): void
    {
        if ($this->configuration->get('nvl-tenancy.enabled') === true
            && $this->context->snapshot()->mode !== TenantContextMode::Platform) {
            throw new TenantBoundaryViolation('Content definitions are deployment-owned platform vocabulary.');
        }
    }
}
