<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Content\Tests\TestCase;

/** Boots Content with a recording definition file and an unsupported block root. */
class LazyBootContentTestCase extends TestCase
{
    /** Incremented by the recording definition file each time it is read. */
    public static int $definitionReads = 0;

    /** @var array<string, class-string<Model>> */
    private array $originalMorphMap = [];

    protected function setUp(): void
    {
        $this->originalMorphMap = Relation::morphMap();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Relation::morphMap($this->originalMorphMap, false);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        self::$definitionReads = 0;
        $app['config']->set('nvl-content.models.block', RecastContentBlock::class);
        $app['config']->set('nvl-content.definition_paths', [__DIR__.'/definitions']);
        $app['config']->set('nvl-content.allowed_definition_roots', [__DIR__.'/definitions']);
    }
}
