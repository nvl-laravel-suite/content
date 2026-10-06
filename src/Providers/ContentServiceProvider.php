<?php

declare(strict_types=1);

namespace Nvl\Content\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Nvl\Content\Actions\ApplyContentDefinitionMigrationsAction;
use Nvl\Content\Actions\ArchiveContentBlockAction;
use Nvl\Content\Actions\CreateContentBlockAction;
use Nvl\Content\Actions\DeleteContentBlockAction;
use Nvl\Content\Actions\DeleteContentPlacementAction;
use Nvl\Content\Actions\ExportContentSnapshotForCopyAction;
use Nvl\Content\Actions\FindContentBlockByKeyAction;
use Nvl\Content\Actions\FindContentPlacementAction;
use Nvl\Content\Actions\GetContentBlockAction;
use Nvl\Content\Actions\GetOwnerContentEditorAction;
use Nvl\Content\Actions\ImportContentSnapshotAction;
use Nvl\Content\Actions\ListContentBlocksAction;
use Nvl\Content\Actions\ListContentDefinitionsAction;
use Nvl\Content\Actions\ListContentGroupsAction;
use Nvl\Content\Actions\ListContentPlacementsAction;
use Nvl\Content\Actions\ListContentPresetsAction;
use Nvl\Content\Actions\ListOwnerContentPlacementSummariesAction;
use Nvl\Content\Actions\PlaceContentBlockAction;
use Nvl\Content\Actions\PlanContentDefinitionMigrationsAction;
use Nvl\Content\Actions\PublishContentBlockAction;
use Nvl\Content\Actions\ReorderContentPlacementsAction;
use Nvl\Content\Actions\ReplaceContentPlacementAction;
use Nvl\Content\Actions\ResolveContentScopesAction;
use Nvl\Content\Actions\RestoreContentBlockAction;
use Nvl\Content\Actions\SyncContentDefinitionsAction;
use Nvl\Content\Actions\UpdateContentBlockAction;
use Nvl\Content\Actions\UpdateContentPlacementAction;
use Nvl\Content\Console\CacheContentDefinitionsCommand;
use Nvl\Content\Console\ClearContentDefinitionsCommand;
use Nvl\Content\Console\ContentDoctorCommand;
use Nvl\Content\Console\MigrateContentDefinitionsCommand;
use Nvl\Content\Console\PublishContentViewsCommand;
use Nvl\Content\Console\SyncContentDefinitionsCommand;
use Nvl\Content\Content;
use Nvl\Content\Contracts\ApplyContentDefinitionMigrationsContract;
use Nvl\Content\Contracts\ArchiveContentBlockContract;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Contracts\ContentContract;
use Nvl\Content\Contracts\ContentDefinitionMigration;
use Nvl\Content\Contracts\ContentFieldPreset;
use Nvl\Content\Contracts\ContentFieldTypeAdapter;
use Nvl\Content\Contracts\ContentOwnerRegistrar;
use Nvl\Content\Contracts\ContentReferenceResolver;
use Nvl\Content\Contracts\CreateContentBlockContract;
use Nvl\Content\Contracts\DeleteContentBlockContract;
use Nvl\Content\Contracts\DeleteContentPlacementContract;
use Nvl\Content\Contracts\ExportContentSnapshotForCopyContract;
use Nvl\Content\Contracts\FindContentBlockByKeyContract;
use Nvl\Content\Contracts\FindContentPlacementContract;
use Nvl\Content\Contracts\GetContentBlockContract;
use Nvl\Content\Contracts\GetOwnerContentEditorContract;
use Nvl\Content\Contracts\ImportContentSnapshotContract;
use Nvl\Content\Contracts\ListContentBlocksContract;
use Nvl\Content\Contracts\ListContentDefinitionsContract;
use Nvl\Content\Contracts\ListContentGroupsContract;
use Nvl\Content\Contracts\ListContentPlacementsContract;
use Nvl\Content\Contracts\ListContentPresetsContract;
use Nvl\Content\Contracts\ListOwnerContentPlacementSummariesContract;
use Nvl\Content\Contracts\PlaceContentBlockContract;
use Nvl\Content\Contracts\PlanContentDefinitionMigrationsContract;
use Nvl\Content\Contracts\PublishContentBlockContract;
use Nvl\Content\Contracts\ReorderContentPlacementsContract;
use Nvl\Content\Contracts\ReplaceContentPlacementContract;
use Nvl\Content\Contracts\ResolveContentScopesContract;
use Nvl\Content\Contracts\RestoreContentBlockContract;
use Nvl\Content\Contracts\SyncContentDefinitionsContract;
use Nvl\Content\Contracts\UpdateContentBlockContract;
use Nvl\Content\Contracts\UpdateContentPlacementContract;
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
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
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
use Nvl\Content\Services\ContentOwnerDeletion;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentReferenceRegistry;
use Nvl\Content\Services\ContentSchemaCompiler;
use Nvl\Content\Support\ContentArrays;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Support\ContentOwnerDeletionBridge;
use Nvl\Content\Support\ContentUriSchemePolicy;
use Nvl\Content\Tenancy\ContentResourceRegistrar;
use Nvl\Data\Services\TypeScriptSourceRegistry;
use Nvl\Support\Doctor\PackageDoctorContributor;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Support\Providers\TenantServiceProvider;
use Nvl\Support\Tenancy\Services\TenantResourceRegistry;
use Nvl\Support\Traits\MergesPackageConfiguration;
use Nvl\Support\Traits\RegistersNamespacedResources;
use Nvl\Tenancy\Services\TenantAdoptionRegistry;
use Nvl\Translatable\Services\TranslationResourceRegistry;
use Opis\JsonSchema\Validator;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Registers the standalone, headless Content package.
 */
final class ContentServiceProvider extends ServiceProvider
{
    use MergesPackageConfiguration;
    use RegistersNamespacedResources;

    public function register(): void
    {
        $this->app->bindIf(ApplyContentDefinitionMigrationsContract::class, ApplyContentDefinitionMigrationsAction::class);
        $this->app->bindIf(ArchiveContentBlockContract::class, ArchiveContentBlockAction::class);
        $this->app->bindIf(CreateContentBlockContract::class, CreateContentBlockAction::class);
        $this->app->bindIf(DeleteContentBlockContract::class, DeleteContentBlockAction::class);
        $this->app->bindIf(DeleteContentPlacementContract::class, DeleteContentPlacementAction::class);
        $this->app->bindIf(ExportContentSnapshotForCopyContract::class, ExportContentSnapshotForCopyAction::class);
        $this->app->bindIf(FindContentBlockByKeyContract::class, FindContentBlockByKeyAction::class);
        $this->app->bindIf(FindContentPlacementContract::class, FindContentPlacementAction::class);
        $this->app->bindIf(GetContentBlockContract::class, GetContentBlockAction::class);
        $this->app->bindIf(GetOwnerContentEditorContract::class, GetOwnerContentEditorAction::class);
        $this->app->bindIf(ImportContentSnapshotContract::class, ImportContentSnapshotAction::class);
        $this->app->bindIf(ListContentBlocksContract::class, ListContentBlocksAction::class);
        $this->app->bindIf(ListContentDefinitionsContract::class, ListContentDefinitionsAction::class);
        $this->app->bindIf(ListContentGroupsContract::class, ListContentGroupsAction::class);
        $this->app->bindIf(ListContentPlacementsContract::class, ListContentPlacementsAction::class);
        $this->app->bindIf(ListContentPresetsContract::class, ListContentPresetsAction::class);
        $this->app->bindIf(ListOwnerContentPlacementSummariesContract::class, ListOwnerContentPlacementSummariesAction::class);
        $this->app->bindIf(PlaceContentBlockContract::class, PlaceContentBlockAction::class);
        $this->app->bindIf(PlanContentDefinitionMigrationsContract::class, PlanContentDefinitionMigrationsAction::class);
        $this->app->bindIf(PublishContentBlockContract::class, PublishContentBlockAction::class);
        $this->app->bindIf(ReorderContentPlacementsContract::class, ReorderContentPlacementsAction::class);
        $this->app->bindIf(ReplaceContentPlacementContract::class, ReplaceContentPlacementAction::class);
        $this->app->bindIf(ResolveContentScopesContract::class, ResolveContentScopesAction::class);
        $this->app->bindIf(RestoreContentBlockContract::class, RestoreContentBlockAction::class);
        $this->app->bindIf(SyncContentDefinitionsContract::class, SyncContentDefinitionsAction::class);
        $this->app->bindIf(UpdateContentBlockContract::class, UpdateContentBlockAction::class);
        $this->app->bindIf(UpdateContentPlacementContract::class, UpdateContentPlacementAction::class);

        $this->app->register(SupportServiceProvider::class);
        PackageDoctorContributor::register($this->app, 'nvl/content', fn (): array => PackageDoctorContributor::reportChecks($this->app->make(ContentDoctor::class)->inspect(), 'nvl:content:doctor'));

        ContentOwnerDeletionBridge::clear();
        $this->app->register(TenantServiceProvider::class);
        $this->mergePackageConfiguration(__DIR__.'/../../config/nvl-content.php', 'content');
        (new ContentResourceRegistrar)->register($this->app->make(TenantResourceRegistry::class));
        $this->app->booted(function (): void {
            if ($this->app->bound(TenantAdoptionRegistry::class)) {
                (new ContentResourceRegistrar)->register($this->app->make(TenantResourceRegistry::class), $this->app->make(TenantAdoptionRegistry::class));
            }
        });
        $this->validateUriSchemeConfiguration();
        $authorization = config(
            'nvl-content.authorization.class',
            ConfiguredContentAuthorization::class,
        );

        if (! is_string($authorization)
            || ! is_a($authorization, ContentAuthorization::class, true)) {
            throw new InvalidArgumentException(
                'content.authorization.class must implement ContentAuthorization.',
            );
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
        $this->app->alias(ContentOwnerRegistry::class, ContentOwnerRegistrar::class);
        $this->app->singleton(ContentReferenceRegistry::class);
        $this->app->singleton(ContentSchemaCompiler::class);
        $this->app->scopedIf(Content::class);
        $this->app->scopedIf(ContentContract::class, static fn (Container $container): ContentContract => $container->make(Content::class));
        $this->app->when(Content::class)
            ->needs(GetOwnerContentEditorAction::class)
            ->give(static fn (Container $container): GetOwnerContentEditorAction => $container->make(
                GetOwnerContentEditorAction::class,
            ));
        $this->app->singleton(Validator::class);
    }

    private function validateUriSchemeConfiguration(): void
    {
        foreach ([
            'nvl-content.links.allowed_schemes',
            'nvl-content.validation.url_schemes',
            'nvl-content.rich_text.allowed_link_schemes',
        ] as $key) {
            ContentUriSchemePolicy::validateAllowedSchemes(
                ContentConfiguration::stringList($key),
                $key,
            );
        }
    }

    public function boot(
        TypeScriptSourceRegistry $typeScriptSources,
        TranslationResourceRegistry $translationResources,
        ContentDefinitionLoader $loader,
        ContentDefinitionRegistry $definitions,
        ContentDefinitionMigrationRegistry $definitionMigrations,
        ContentFieldPresetRegistry $presets,
        ContentFieldTypeRegistry $fieldTypes,
        ContentOwnerRegistry $owners,
        ContentReferenceRegistry $references,
        ContentOwnerDeletion $ownerDeletion,
    ): void {
        $this->app->make(GlobalNames::class)->translations('content', __DIR__.'/../../lang', $this->app->make('translation.loader'));
        $this->publishes([
            __DIR__.'/../../lang' => lang_path('vendor/nvl-content'),
        ], 'nvl-content-translations');
        $typeScriptSources->register(__DIR__.'/..', 'nvl/content');
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
            if (! is_bool($enabled) || ! is_bool($required) || ($required && ! $enabled)) {
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

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'nvl-content');

        $translationResources->register(
            key: 'content.blocks',
            modelClass: ContentBlock::class,
            label: 'Content blocks',
            searchableColumns: ['key', 'scope', 'scope_key', 'status'],
            displayColumns: ['key', 'scope', 'scope_key', 'status', 'revision'],
            orderColumn: 'updated_at',
        );

        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheContentDefinitionsCommand::class,
                ClearContentDefinitionsCommand::class,
                ContentDoctorCommand::class,
                MigrateContentDefinitionsCommand::class,
                PublishContentViewsCommand::class,
                SyncContentDefinitionsCommand::class,
            ]);
        }

        $this->optimizes('nvl:content:cache', 'nvl:content:clear', 'nvl-content');

        $this->publishes([
            __DIR__.'/../../config/nvl-content.php' => config_path('nvl-content.php'),
        ], 'content-config');
        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'content-migrations');
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/nvl-content'),
        ], 'content-views');
        $this->publishes([
            __DIR__.'/../../resources/boost/skills' => base_path('.agents/skills'),
        ], 'content-skills');
    }

    /**
     * Register the package-owned placement alias while retaining explicit consumer mappings.
     */
    private function registerPlacementMorphAlias(): void
    {
        if (in_array(ContentPlacement::class, Relation::morphMap(), true)) {
            return;
        }

        $alias = 'nvl-content-placement';
        $existing = Relation::getMorphedModel($alias);

        if ($existing !== null && $existing !== ContentPlacement::class) {
            throw new InvalidArgumentException("Content placement morph alias [{$alias}] is already in use.");
        }

        Relation::morphMap([$alias => ContentPlacement::class], merge: true);
    }

    private function registerFieldTypes(ContentFieldTypeRegistry $registry): void
    {
        foreach ([
            'text',
            'textarea',
            'date',
            'date_time',
            'url',
            'uri',
            'email',
            'color',
            'select',
        ] as $type) {
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
            if (! is_string($alias)
                || ! is_string($class)
                || ! is_a($class, ContentFieldTypeAdapter::class, true)) {
                throw new InvalidArgumentException('Every configured content field type is invalid.');
            }

            $adapter = $this->app->make($class);

            if (! $adapter instanceof ContentFieldTypeAdapter || $adapter->alias() !== $alias) {
                throw new InvalidArgumentException(
                    "Configured content field adapter [{$class}] does not provide alias [{$alias}].",
                );
            }

            $registry->register($adapter);
        }
    }

    /**
     * Register built-in and consumer-configured semantic field presets.
     */
    private function registerFieldPresets(ContentFieldPresetRegistry $registry): void
    {
        foreach ([
            new LinkContentFieldPreset,
            new ButtonContentFieldPreset,
            new ImageContentFieldPreset,
            new HeadingContentFieldPreset,
            new BannerContentFieldPreset,
        ] as $preset) {
            $registry->register($preset);
        }

        $configured = config('nvl-content.presets', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException('content.presets must be an array.');
        }

        foreach ($configured as $alias => $configuration) {
            if (! is_string($alias)) {
                throw new InvalidArgumentException(
                    'Every configured content field preset requires a string alias.',
                );
            }

            if (is_string($configuration)
                && is_a($configuration, ContentFieldPreset::class, true)) {
                $preset = $this->app->make($configuration);

                if (! $preset instanceof ContentFieldPreset || $preset->alias() !== $alias) {
                    throw new InvalidArgumentException(
                        "Configured content field preset [{$configuration}] does not provide alias [{$alias}].",
                    );
                }

                $registry->register($preset);

                continue;
            }

            if (! is_array($configuration)) {
                throw new InvalidArgumentException(
                    "Configured content field preset [{$alias}] is invalid.",
                );
            }

            $configuration = ContentArrays::stringMap(
                $configuration,
                "content field preset {$alias}",
            );
            $unknown = array_diff(
                array_keys($configuration),
                ['name', 'description', 'definition'],
            );
            $name = $configuration['name'] ?? null;
            $description = $configuration['description'] ?? null;
            $definition = $configuration['definition'] ?? null;

            if ($unknown !== []
                || ! is_string($name)
                || $description !== null && ! is_string($description)
                || ! is_array($definition)) {
                throw new InvalidArgumentException(
                    "Configured content field preset [{$alias}] has an invalid definition.",
                );
            }

            $registry->register(new ConfiguredContentFieldPreset(
                presetAlias: $alias,
                presetName: $name,
                presetDescription: $description,
                fieldDefinition: ContentArrays::stringMap(
                    $definition,
                    "content field preset {$alias} definition",
                ),
            ));
        }
    }

    /** Permit only exact console maintenance commands to boot without source compilation. */
    private function isDefinitionMaintenanceCommand(): bool
    {
        if (! $this->app->runningInConsole() || PHP_SAPI !== 'cli') {
            return false;
        }

        return in_array((new ArgvInput)->getFirstArgument(), [
            'nvl:content:cache', 'nvl:content:clear', 'nvl:install', 'nvl:doctor',
            'nvl:content:doctor', 'package:discover', 'config:cache', 'config:clear',
            'optimize', 'optimize:clear',
        ], true);
    }

    private function registerDefinitions(
        ContentDefinitionLoader $loader,
        ContentDefinitionRegistry $registry,
    ): void {
        foreach ($loader->load() as $definition) {
            $registry->register($definition);
        }
    }

    private function registerDefinitionMigrations(
        ContentDefinitionMigrationRegistry $registry,
    ): void {
        $configured = config('nvl-content.definition_migrations', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException(
                'content.definition_migrations must be an array.',
            );
        }

        foreach ($configured as $class) {
            if (! is_string($class)
                || ! is_a($class, ContentDefinitionMigration::class, true)) {
                throw new InvalidArgumentException(
                    'Every configured content definition migration is invalid.',
                );
            }

            $migration = $this->app->make($class);

            if (! $migration instanceof ContentDefinitionMigration) {
                throw new InvalidArgumentException(
                    "Configured content definition migration [{$class}] is invalid.",
                );
            }

            $registry->register($migration);
        }
    }

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
            if (! is_string($alias)
                || ! is_string($model)) {
                throw new InvalidArgumentException('Every configured content owner is invalid.');
            }

            $registry->register($alias, $model);
        }
    }

    private function registerReferences(ContentReferenceRegistry $registry): void
    {
        $configured = config('nvl-content.references', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException('content.references must be an array.');
        }

        foreach ($configured as $alias => $resolver) {
            if (! is_string($alias)
                || ! is_string($resolver)
                || ! is_a($resolver, ContentReferenceResolver::class, true)) {
                throw new InvalidArgumentException('Every configured content reference is invalid.');
            }

            $registry->register($alias, $resolver);
        }
    }
}
