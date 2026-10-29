<?php

declare(strict_types=1);

namespace Nvl\Content\Providers;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Nvl\Content\Console\Commands\CacheContentDefinitionsCommand;
use Nvl\Content\Console\Commands\ClearContentDefinitionsCommand;
use Nvl\Content\Console\Commands\ContentDoctorCommand;
use Nvl\Content\Console\Commands\MigrateContentDefinitionsCommand;
use Nvl\Content\Console\Commands\SyncContentDefinitionsCommand;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Contracts\ContentDefinitionMigration;
use Nvl\Content\Contracts\ContentFieldPreset;
use Nvl\Content\Contracts\ContentFieldTypeAdapter;
use Nvl\Content\Contracts\ContentOwnerRegistrar;
use Nvl\Content\Contracts\ContentReferenceResolver;
use Nvl\Content\Contracts\ContentSnapshotIntegrity;
use Nvl\Content\Contracts\ContentStorage;
use Nvl\Content\Exceptions\ContentDefinitionCacheException;
use Nvl\Content\FieldPresets\BannerContentFieldPreset;
use Nvl\Content\FieldPresets\ButtonContentFieldPreset;
use Nvl\Content\FieldPresets\ConfiguredContentFieldPreset;
use Nvl\Content\FieldPresets\HeadingContentFieldPreset;
use Nvl\Content\FieldPresets\ImageContentFieldPreset;
use Nvl\Content\FieldPresets\LinkContentFieldPreset;
use Nvl\Content\FieldTypes\BooleanFieldTypeAdapter;
use Nvl\Content\FieldTypes\JsonFieldTypeAdapter;
use Nvl\Content\FieldTypes\MediaFieldTypeAdapter;
use Nvl\Content\FieldTypes\MultiSelectFieldTypeAdapter;
use Nvl\Content\FieldTypes\NumberFieldTypeAdapter;
use Nvl\Content\FieldTypes\ReferenceFieldTypeAdapter;
use Nvl\Content\FieldTypes\RichTextFieldTypeAdapter;
use Nvl\Content\FieldTypes\StringFieldTypeAdapter;
use Nvl\Content\FieldTypes\StructuredFieldTypeAdapter;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Ownership\ContentAdoptionAdapter as OwnershipContentAdoptionAdapter;
use Nvl\Content\Ownership\ContentResourceRegistrar as OwnershipContentResourceRegistrar;
use Nvl\Content\Services\CompiledContentDefinitionCache;
use Nvl\Content\Services\ConfiguredContentAuthorization;
use Nvl\Content\Services\ContentCatalogCopyRegistry;
use Nvl\Content\Services\ContentDefinitionLoader;
use Nvl\Content\Services\ContentDefinitionMigrationRegistry;
use Nvl\Content\Services\ContentDefinitionRegistry;
use Nvl\Content\Services\ContentDoctor;
use Nvl\Content\Services\ContentFieldPresetRegistry;
use Nvl\Content\Services\ContentFieldTypeRegistry;
use Nvl\Content\Services\ContentJsonSchemaBuilder;
use Nvl\Content\Services\ContentLocalizedValues;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Content\Services\ContentOwnerDeletion;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentReferenceRegistry;
use Nvl\Content\Services\ContentSchemaCompiler;
use Nvl\Content\Services\ContentSnapshotIntegrity as ServicesContentSnapshotIntegrity;
use Nvl\Content\Services\ContentStorageRegistry;
use Nvl\Content\Support\ContentArrays;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Support\ContentOwnerDeletionBridge;
use Nvl\Content\Support\ContentUriSchemePolicy;
use Nvl\Ownership\Services\OwnershipAdoptionRegistry;
use Nvl\Support\Doctor\PackageDoctorContributor;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\OwnerRegistry;
use Nvl\Support\Ownership\Contracts\OwnershipIntegratedProvider;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Support\Traits\MergesPackageConfiguration;
use Nvl\Support\Traits\RegistersNamespacedResources;
use Nvl\Translatable\Services\TranslationResourceRegistry;
use Opis\JsonSchema\Validator;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Registers the standalone, headless Content package.
 */
final class ContentServiceProvider extends ServiceProvider implements OwnershipIntegratedProvider
{
    use MergesPackageConfiguration;
    use RegistersNamespacedResources;

    /** Register the canonical capabilities without capturing current admission. */
    public function register(): void
    {
        $this->app->bindIf(ContentSnapshotIntegrity::class, ServicesContentSnapshotIntegrity::class);
        $this->app->bindIf(ContentStorage::class, ContentStorageRegistry::class);
        $this->app->register(SupportServiceProvider::class);
        PackageDoctorContributor::register($this->app, 'nvl/content', fn (): array => PackageDoctorContributor::reportChecks($this->app->make(ContentDoctor::class)->inspect(), 'nvl:content:doctor'));
        ContentOwnerDeletionBridge::clear();
        $this->mergePackageConfiguration(__DIR__.'/../../config/nvl-content.php', 'content');
        $this->app->singleton(ContentModelRegistry::class);
        $this->app->booting(fn () => $this->app->make(ContentModelRegistry::class)->prepare());
        $this->validateUriSchemeConfiguration();
        $authorization = config('nvl-content.authorization.class', ConfiguredContentAuthorization::class);
        if (! is_string($authorization) || ! is_a($authorization, ContentAuthorization::class, true)) {
            throw new InvalidArgumentException('content.authorization.class must implement ContentAuthorization.');
        }
        $this->app->bindIf(ContentAuthorization::class, $authorization);
        $this->app->singleton(ContentDefinitionRegistry::class);
        $this->app->singleton(ContentCatalogCopyRegistry::class);
        $this->app->singleton(ContentDefinitionMigrationRegistry::class);
        $this->app->singleton(ContentFieldPresetRegistry::class);
        $this->app->singleton(ContentFieldTypeRegistry::class);
        $this->app->singleton(ContentJsonSchemaBuilder::class);
        $this->app->scoped(ContentLocalizedValues::class);
        $this->app->singleton(ContentOwnerRegistry::class);
        $names = $this->app->make(GlobalNames::class);
        $exists = fn (string $name): bool => $this->app->bound($name);
        $install = fn (string $name) => $this->app->alias(ContentOwnerRegistry::class, $name);
        $names->reserve('content', 'container', ContentOwnerRegistrar::class, $exists, $install);
        $this->app->singleton(ContentReferenceRegistry::class);
        $this->app->singleton(ContentSchemaCompiler::class);
        $this->app->singleton(Validator::class);
    }

    /** Native Content operation. */
    private function validateUriSchemeConfiguration(): void
    {
        foreach (['nvl-content.links.allowed_schemes', 'nvl-content.validation.url_schemes', 'nvl-content.rich_text.allowed_link_schemes'] as $key) {
            ContentUriSchemePolicy::validateAllowedSchemes(ContentConfiguration::stringList($key), $key);
        }
    }

    /** Compile the package capabilities and register framework integration. */
    public function boot(TranslationResourceRegistry $translationResources, ContentDefinitionLoader $loader, ContentDefinitionRegistry $definitions, ContentDefinitionMigrationRegistry $definitionMigrations, ContentFieldPresetRegistry $presets, ContentFieldTypeRegistry $fieldTypes, ContentOwnerRegistry $owners, ContentReferenceRegistry $references, ContentOwnerDeletion $ownerDeletion): void
    {
        $this->app->make(OwnershipContentResourceRegistrar::class)->register($this->app->make(OwnershipResourceRegistry::class));
        if ($this->app->bound(OwnershipAdoptionRegistry::class)) {
            $this->app->make(OwnershipAdoptionRegistry::class)->register('content', OwnershipContentAdoptionAdapter::class);
        }
        $this->app->make(GlobalNames::class)->translations('content', __DIR__.'/../../lang', $this->app->make('translation.loader'));
        $this->publishes([__DIR__.'/../../lang' => lang_path('vendor/nvl-content')], 'nvl-content-translations');
        $this->registerFieldTypes($fieldTypes);
        $this->registerFieldPresets($presets);
        $this->registerReferences($references);
        $this->registerDefinitionMigrations($definitionMigrations);
        $this->registerOwners($owners);
        $this->registerPlacementMorphAlias();
        ContentOwnerDeletionBridge::use($ownerDeletion);
        $this->app->booted(function () use ($loader, $definitions): void {
            if ($this->isDefinitionMaintenanceCommand()) {
                return;
            }
            $enabled = config('nvl-content.compiled_cache.enabled', false);
            $required = config('nvl-content.compiled_cache.required', false);
            if (! is_bool($enabled) || ! is_bool($required) || $required && ! $enabled) {
                throw ContentDefinitionCacheException::invalid('enabled and required must be booleans and required mode must also be enabled');
            }
            if ($enabled) {
                try {
                    $this->app->make(CompiledContentDefinitionCache::class)->restore($definitions);

                    return;
                } catch (ContentDefinitionCacheException $exception) {
                    if ($required) {
                        throw $exception;
                    }
                }
            }
            $this->registerDefinitions($loader, $definitions);
        });
        if ((bool) config('nvl-content.migrations.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
        $translationResources->register(key: 'content.blocks', modelClass: ContentModelRegistry::modelClass(), label: 'Content blocks', searchableColumns: ['key', 'scope', 'scope_key', 'status'], displayColumns: ['key', 'scope', 'scope_key', 'status', 'revision'], orderColumn: 'updated_at');
        if ($this->app->runningInConsole()) {
            $this->commands([CacheContentDefinitionsCommand::class, ClearContentDefinitionsCommand::class, ContentDoctorCommand::class, MigrateContentDefinitionsCommand::class, SyncContentDefinitionsCommand::class]);
        }
        $this->optimizes('nvl:content:cache', 'nvl:content:clear', 'nvl-content');
        $this->publishes([__DIR__.'/../../config/nvl-content.php' => config_path('nvl-content.php')], 'nvl-content-config');
        $this->publishesMigrations([__DIR__.'/../../database/migrations' => database_path('migrations')], 'nvl-content-migrations');
        $this->publishes([__DIR__.'/../../resources/boost/skills' => base_path('.agents/skills')], 'nvl-content-skills');
    }

    /**
     * Register the package-owned placement alias while retaining explicit consumer mappings.
     */
    private function registerPlacementMorphAlias(): void
    {
        $this->app->make(OwnerRegistry::class)->registerPackage('nvl-content-placement', ContentPlacement::class, package: 'content');
    }

    /** Native Content operation. */
    private function registerFieldTypes(ContentFieldTypeRegistry $registry): void
    {
        foreach (['text', 'textarea', 'date', 'date_time', 'url', 'uri', 'email', 'color', 'select'] as $type) {
            $registry->register(new StringFieldTypeAdapter($type));
        }
        $registry->register(new BooleanFieldTypeAdapter);
        $registry->register(new NumberFieldTypeAdapter(integer: true));
        $registry->register(new NumberFieldTypeAdapter(integer: false));
        $registry->register(new MultiSelectFieldTypeAdapter);
        $registry->register(new RichTextFieldTypeAdapter);
        $registry->register($this->app->make(JsonFieldTypeAdapter::class));
        $registry->register(new StructuredFieldTypeAdapter('object', list: false));
        $registry->register(new StructuredFieldTypeAdapter('list', list: true));
        $registry->register(new StructuredFieldTypeAdapter('repeater', list: true));
        $registry->register(new StructuredFieldTypeAdapter('table', list: true));
        $registry->register($this->app->make(MediaFieldTypeAdapter::class, ['multiple' => false]));
        $registry->register($this->app->make(MediaFieldTypeAdapter::class, ['multiple' => true]));
        $registry->register($this->app->make(ReferenceFieldTypeAdapter::class, ['multiple' => false]));
        $registry->register($this->app->make(ReferenceFieldTypeAdapter::class, ['multiple' => true]));
        $configured = config('nvl-content.field_types', []);
        if (! is_array($configured)) {
            throw new InvalidArgumentException('content.field_types must be an array.');
        }
        foreach ($configured as $alias => $class) {
            if (! is_string($alias) || ! is_string($class) || ! is_a($class, ContentFieldTypeAdapter::class, true)) {
                throw new InvalidArgumentException('Every configured content field type is invalid.');
            }
            $adapter = $this->app->make($class);
            if (! $adapter instanceof ContentFieldTypeAdapter || $adapter->alias() !== $alias) {
                throw new InvalidArgumentException("Configured content field adapter [{$class}] does not provide alias [{$alias}].");
            }
            $registry->register($adapter);
        }
    }

    /**
     * Register built-in and consumer-configured semantic field presets.
     */
    private function registerFieldPresets(ContentFieldPresetRegistry $registry): void
    {
        foreach ([new LinkContentFieldPreset, new ButtonContentFieldPreset, new ImageContentFieldPreset, new HeadingContentFieldPreset, new BannerContentFieldPreset] as $preset) {
            $registry->register($preset);
        }
        $configured = config('nvl-content.presets', []);
        if (! is_array($configured)) {
            throw new InvalidArgumentException('content.presets must be an array.');
        }
        foreach ($configured as $alias => $configuration) {
            if (! is_string($alias)) {
                throw new InvalidArgumentException('Every configured content field preset requires a string alias.');
            }
            if (is_string($configuration) && is_a($configuration, ContentFieldPreset::class, true)) {
                $preset = $this->app->make($configuration);
                if (! $preset instanceof ContentFieldPreset || $preset->alias() !== $alias) {
                    throw new InvalidArgumentException("Configured content field preset [{$configuration}] does not provide alias [{$alias}].");
                }
                $registry->register($preset);

                continue;
            }
            if (! is_array($configuration)) {
                throw new InvalidArgumentException("Configured content field preset [{$alias}] is invalid.");
            }
            $configuration = ContentArrays::stringMap($configuration, "content field preset {$alias}");
            $unknown = array_diff(array_keys($configuration), ['name', 'description', 'definition']);
            $name = $configuration['name'] ?? null;
            $description = $configuration['description'] ?? null;
            $definition = $configuration['definition'] ?? null;
            if ($unknown !== [] || ! is_string($name) || $description !== null && ! is_string($description) || ! is_array($definition)) {
                throw new InvalidArgumentException("Configured content field preset [{$alias}] has an invalid definition.");
            }
            $registry->register(new ConfiguredContentFieldPreset(presetAlias: $alias, presetName: $name, presetDescription: $description, fieldDefinition: ContentArrays::stringMap($definition, "content field preset {$alias} definition")));
        }
    }

    /** Permit only exact console maintenance commands to boot without source compilation. */
    private function isDefinitionMaintenanceCommand(): bool
    {
        if (! $this->app->runningInConsole() || PHP_SAPI !== 'cli') {
            return false;
        }

        return in_array((new ArgvInput)->getFirstArgument(), ['nvl:content:cache', 'nvl:content:clear', 'nvl:install', 'nvl:doctor', 'nvl:content:doctor', 'package:discover', 'config:cache', 'config:clear', 'optimize', 'optimize:clear'], true);
    }

    /** Native Content operation. */
    private function registerDefinitions(ContentDefinitionLoader $loader, ContentDefinitionRegistry $registry): void
    {
        foreach ($loader->load() as $definition) {
            $registry->register($definition);
        }
    }

    /** Native Content operation. */
    private function registerDefinitionMigrations(ContentDefinitionMigrationRegistry $registry): void
    {
        $configured = config('nvl-content.definition_migrations', []);
        if (! is_array($configured)) {
            throw new InvalidArgumentException('content.definition_migrations must be an array.');
        }
        foreach ($configured as $class) {
            if (! is_string($class) || ! is_a($class, ContentDefinitionMigration::class, true)) {
                throw new InvalidArgumentException('Every configured content definition migration is invalid.');
            }
            $migration = $this->app->make($class);
            if (! $migration instanceof ContentDefinitionMigration) {
                throw new InvalidArgumentException("Configured content definition migration [{$class}] is invalid.");
            }
            $registry->register($migration);
        }
    }

    /** Native Content operation. */
    private function registerOwners(ContentOwnerRegistry $registry): void
    {
        $configured = config('nvl-content.owners', []);
        if (! is_array($configured)) {
            throw new InvalidArgumentException('content.owners must be an array.');
        }
        foreach ($configured as $alias => $model) {
            if (is_int($alias) && is_string($model)) {
                $alias = $model;
            }
            if (! is_string($alias) || ! is_string($model)) {
                throw new InvalidArgumentException('Every configured content owner is invalid.');
            }
            $registry->register($alias, $model);
        }
    }

    /** Native Content operation. */
    private function registerReferences(ContentReferenceRegistry $registry): void
    {
        $configured = config('nvl-content.references', []);
        if (! is_array($configured)) {
            throw new InvalidArgumentException('content.references must be an array.');
        }
        foreach ($configured as $alias => $resolver) {
            if (! is_string($alias) || ! is_string($resolver) || ! is_a($resolver, ContentReferenceResolver::class, true)) {
                throw new InvalidArgumentException('Every configured content reference is invalid.');
            }
            $registry->register($alias, $resolver);
        }
    }
}
