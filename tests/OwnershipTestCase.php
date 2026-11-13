<?php

declare(strict_types=1);

namespace Nvl\Content\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Providers\ContentServiceProvider;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Content\Tests\Fixtures\OwnershipActor;
use Nvl\Content\Tests\Fixtures\OwnershipCompany;
use Nvl\Content\Tests\Fixtures\OwnershipContentBlock;
use Nvl\Content\Tests\Fixtures\OwnershipContentOwner;
use Nvl\Content\Tests\Fixtures\OwnershipContentOwnerAdapter;
use Nvl\Content\Tests\Fixtures\OwnershipMedia;
use Nvl\Media\Providers\MediaServiceProvider;
use Nvl\Ownership\Contracts\CentralAccess;
use Nvl\Ownership\Contracts\OwnerAccess;
use Nvl\Ownership\Contracts\OwnershipAdoptionAccess;
use Nvl\Ownership\Providers\OwnershipServiceProvider;
use Nvl\Ownership\Services\OwnershipAdoptionCoordinator;
use Nvl\Ownership\Services\OwnershipAdoptionRegistry;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\Tests\Fixtures\ReusesAdoptedSchema;
use Nvl\Ownership\ValueObjects\OwnershipMapping;
use Nvl\Ownership\ValueObjects\OwnershipOperation;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Ownership\Services\OwnershipStorageTables;
use Nvl\Support\Ownership\ValueObjects\OwnershipResourceDefinition;
use Nvl\Support\Providers\LocaleServiceProvider;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Translatable\Providers\TranslatableServiceProvider;
use Orchestra\Testbench\TestCase;

/** Exercises committed ownership and audit behavior without NVL Tenancy or Auth providers. */
abstract class OwnershipTestCase extends TestCase
{
    use ReusesAdoptedSchema;

    private array $originalMorphMap = [];

    protected function tearDown(): void
    {
        parent::tearDown();
        Relation::morphMap($this->originalMorphMap, false);
    }

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [LocaleServiceProvider::class, SupportServiceProvider::class, TranslatableServiceProvider::class, OwnershipServiceProvider::class, MediaServiceProvider::class, ContentServiceProvider::class];
    }

    /** @return class-string<ContentBlock> */
    protected function blockClass(): string
    {
        return OwnershipContentBlock::class;
    }

    /** Configure owned package roots before immutable resource registration. */
    protected function defineEnvironment($app): void
    {
        static $authorize = static fn (): bool => true;

        $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'nvl-core.owners' => [OwnershipCompany::class, OwnershipActor::class], 'nvl-ownership.enabled' => true, 'nvl-ownership.migrations.enabled' => false, 'nvl-ownership.central_operations' => ['content-definitions'], 'nvl-tenancy.enabled' => false, 'nvl-content.models.block' => $this->blockClass(), 'nvl-content.ownership.owner_resources' => ['fixture.content-owners'], 'nvl-content.authorization.callback' => $authorize, 'nvl-content.locales.available' => ['en', 'bg'], 'nvl-content.locales.required_on_publish' => ['en'], 'nvl-content.owners' => ['subjects' => OwnershipContentOwner::class], 'nvl-content.definitions' => ['copy' => ['name' => 'Copy', 'version' => 1, 'allowed_regions' => ['main'], 'schema' => ['fields' => [['key' => 'text', 'type' => 'text', 'label' => 'Text', 'localized' => true]]]]], 'nvl-media.models.media' => OwnershipMedia::class, 'nvl-media.migrations.enabled' => false, 'nvl-translatable.locales' => ['en', 'bg'], 'nvl-comments.moderation.new_status' => 'approved', 'nvl-comments.mutation_lock.enabled' => false, 'nvl-content.migrations.enabled' => false, 'nvl-media.locks.deduplication.store' => 'array', 'nvl-media.routes.api_enabled' => false, 'auth.providers.users.model' => OwnershipActor::class]);
        if (getenv('NVL_OWNERSHIP_TEST_DRIVER') === 'pgsql') {
            $database = getenv('NVL_OWNERSHIP_TEST_DATABASE');
            if (! is_string($database) || preg_match('/^nvl_own_test_[a-z0-9_]+$/D', $database) !== 1) {
                throw new \RuntimeException('Native integration tests require a dedicated nvl_own_test_ database.');
            }
            $driver = (string) getenv('NVL_OWNERSHIP_TEST_DRIVER');
            $host = getenv('NVL_OWNERSHIP_TEST_HOST') ?: '/tmp';
            $port = getenv('NVL_OWNERSHIP_TEST_PORT') ?: '5432';
            if (! in_array($host, ['127.0.0.1', 'localhost', '::1', '/tmp'], true) || ! ctype_digit($port)) {
                throw new \RuntimeException('Native ownership fixtures require a loopback service and explicit numeric port.');
            }
            $connection = ['driver' => $driver, 'url' => null, 'host' => $host, 'port' => $port, 'database' => $database, 'username' => getenv('NVL_OWNERSHIP_TEST_USERNAME') ?: get_current_user(), 'password' => getenv('NVL_OWNERSHIP_TEST_PASSWORD') ?: '', 'prefix' => '', 'timezone' => 'UTC'];
            $connection += ['charset' => 'utf8', 'search_path' => 'public', 'sslmode' => 'prefer'];
            $app['config']->set('database.connections.sqlite', $connection);
        }
    }

    /** Install real package storage and activate through the reviewed coordinator. */
    protected function setUp(): void
    {
        $this->originalMorphMap = Relation::morphMap();
        parent::setUp();
        $connection = ContentModelRegistry::newInstance()->getConnection();
        $schema = $connection->getSchemaBuilder();
        $actorSchema = DB::getSchemaBuilder();
        $sharesSource = $connection === DB::connection();
        $this->startAdoptedSchema($sharesSource && $this->mayReuseAdoptedSchema());
        if (getenv('NVL_OWNERSHIP_TEST_DRIVER') === 'pgsql') {
            $this->assertSame(getenv('NVL_OWNERSHIP_TEST_DRIVER'), DB::connection()->getDriverName());
            if (DB::connection()->getDatabaseName() !== getenv('NVL_OWNERSHIP_TEST_DATABASE')) {
                throw new \RuntimeException('The actual coordinator differs from the dedicated test database.');
            }
            if (! $this->hasRetainedOwnershipSchema()) {
                $actorSchema->dropAllTables();
            }
            if (! $sharesSource) {
                if ($connection->getDatabaseName() !== getenv('NVL_OWNERSHIP_TEST_REMOTE_DATABASE')) {
                    throw new \RuntimeException('The actual source differs from the dedicated remote test database.');
                }
                $schema->dropAllTables();
            }
        }
        if (! $this->hasRetainedOwnershipSchema()) {
            $this->installHostSchema($schema, $actorSchema);
        }
        OwnershipCompany::query()->insert([['id' => 7], ['id' => 8]]);
        $actor = OwnershipActor::query()->create(['id' => 1, 'name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'private hash']);
        $this->app->make(OwnershipResourceRegistry::class)->register(new OwnershipResourceDefinition('fixture.content-owners', 'fixture', OwnershipContentOwner::class, OwnershipContentOwner::ownershipDefinition()));
        $this->app->make(OwnershipAdoptionRegistry::class)->register('fixture', OwnershipContentOwnerAdapter::class);
        if (! $this->hasRetainedOwnershipSchema()) {
            $this->installContentSchema($connection, $schema);
        }
        $this->app->bind(OwnerAccess::class, static fn () => new class implements OwnerAccess
        {
            public function assertCanEnter(Authenticatable $actor, Model $owner): void {}
        });
        $this->app->bind(CentralAccess::class, static fn () => new class implements CentralAccess
        {
            public function assertCanRun(string $operation, Authenticatable $actor): void {}
        });
        $this->app->bind(OwnershipAdoptionAccess::class, static fn () => new class implements OwnershipAdoptionAccess
        {
            public function authorize(OwnershipOperation $operation): void {}
        });
        $this->app->instance(MaintenanceMode::class, new class implements MaintenanceMode
        {
            public function activate(array $payload): void {}

            public function deactivate(): void {}

            public function active(): bool
            {
                return true;
            }

            public function data(): array
            {
                return [];
            }
        });
        if ($this->hasRetainedOwnershipSchema()) {
            // Replace the fixture's previous synchronization audit entry when reseeding definitions.
            $connection->table(OwnershipStorageTables::Operations)
                ->where('purpose', 'content-definitions')
                ->where('actor_type', $actor::class)
                ->where('actor_id', (string) $actor->getAuthIdentifier())
                ->delete();
        } else {
            $this->adoptOwnership();
        }
        $this->app->make(OwnershipRunner::class)->central('content-definitions', $actor, fn () => $this->app->make(ContentDefinitions::class)->sync(SystemActor::Background));
    }

    protected function mayReuseAdoptedSchema(): bool
    {
        return false;
    }

    private function installHostSchema(Builder $schema, Builder $actorSchema): void
    {
        $schema->create('content_owned_companies', static fn (Blueprint $table) => $table->unsignedBigInteger('id')->primary());
        $actorSchema->create('content_owned_actors', static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });
        $schema->create('content_owned_subjects', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->foreign('company_id')->references('id')->on('content_owned_companies');
        });
    }

    private function installContentSchema(Connection $connection, Builder $schema): void
    {
        foreach (glob(__DIR__.'/../../media/database/migrations/*.php') ?: [] as $path) {
            (require $path)->up();
        }
        foreach (glob(__DIR__.'/../database/migrations/*.php') ?: [] as $path) {
            (require $path)->up();
        }
        $definitionTable = (new ($this->blockClass())())->getTable();
        $schema->table($definitionTable, static function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('content_owned_companies');
        });
        $schema->table((new OwnershipMedia)->getTable(), static function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('content_owned_companies');
        });
        (require __DIR__.'/../../ownership/database/migrations/ownership/2026_10_18_000001_nvl_ownership_create_adoption_tables.php')->up();
        if ($connection !== DB::connection() && ! $schema->hasTable(OwnershipStorageTables::Stores)) {
            $configured = config('nvl-ownership.connection');
            try {
                config(['nvl-ownership.connection' => $connection->getName()]);
                (require __DIR__.'/../../ownership/database/migrations/ownership/2026_10_18_000001_nvl_ownership_create_adoption_tables.php')->up();
            } finally {
                config(['nvl-ownership.connection' => $configured]);
            }
        }
    }

    /** Activate this fixture graph after its source tables and host policies exist. */
    protected function adoptOwnership(): void
    {
        $operation = new OwnershipOperation('adopt-content', 'operator', '1');
        $coordinator = $this->app->make(OwnershipAdoptionCoordinator::class);
        $plan = $coordinator->prepare(['media', 'content', 'fixture'], $this->reviewedMappings(), $operation);
        $coordinator->backfill($plan, 100, $operation);
        $coordinator->activate($plan, $operation);
    }

    /** @return list<OwnershipMapping> */
    protected function reviewedMappings(): array
    {
        return [];
    }
}
