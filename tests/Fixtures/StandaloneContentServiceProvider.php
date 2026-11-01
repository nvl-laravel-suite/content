<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;
use Nvl\Content\Contracts\ContentAuthorization;

/** Supplies an explicit background policy to the isolated lean Laravel consumer. */
final class StandaloneContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ContentAuthorization::class, StandaloneContentAuthorization::class);
    }
}
