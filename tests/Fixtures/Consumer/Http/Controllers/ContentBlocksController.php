<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Services\ContentBlocks;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentPlacements;
use Nvl\Content\Services\ContentReader;
use Nvl\Content\Support\ContentArrays;
use Nvl\Content\Tests\Fixtures\Consumer\ContentActorFactory;
use Nvl\Content\Tests\Fixtures\Consumer\Data\ContentBlockData;
use Nvl\Content\Tests\Fixtures\Consumer\Data\ContentPlacementData;
use Nvl\Content\Tests\Fixtures\Consumer\Data\Mutations\CreateContentBlockData;
use Nvl\Content\Tests\Fixtures\Consumer\Data\Mutations\PlaceContentBlockData;
use Nvl\Content\Tests\Fixtures\Consumer\Data\Mutations\UpdateContentBlockData;
use Nvl\Content\Tests\Fixtures\Consumer\Data\Mutations\UpdateContentPlacementData;
use Nvl\Content\Tests\Fixtures\Consumer\Data\Queries\ExpectedRevisionData;
use Nvl\Content\Tests\Fixtures\Consumer\GetOwnerContentEditorAction;
use Nvl\Content\Tests\Fixtures\Consumer\Http\ContentResponseData;

/**
 * Thin opt-in management API for blocks, publication, and placements.
 */
final class ContentBlocksController extends ContentController
{
    /**
     * Return every reusable semantic field preset available to the editor.
     */
    public function presets(Request $request, ContentActorFactory $actors): JsonResponse
    {
        return response()->json(['data' => $this->content(fn () => app(ContentDefinitions::class)->presets($actors->fromRequest($request)))->map(ContentResponseData::preset(...))->all()]);
    }

    public function definitions(Request $request, ContentActorFactory $actors): JsonResponse
    {
        return response()->json(['data' => $this->content(fn () => app(ContentDefinitions::class)->all($actors->fromRequest($request)))->map(ContentResponseData::definition(...))->all()]);
    }

    public function index(Request $request, ContentActorFactory $actors): JsonResponse
    {
        $validated = Validator::make(['filters' => array_diff_key($request->query(), array_flip(['page', 'per_page']))], [
            'filters' => ['array:key,scope,scope_key,status,visibility,definition_id'],
            'filters.key' => ['sometimes', 'string', 'max:191'],
            'filters.scope' => ['sometimes', 'string', 'max:100'],
            'filters.scope_key' => ['sometimes', 'string', 'max:191'],
            'filters.status' => ['sometimes', 'string'],
            'filters.visibility' => ['sometimes', 'string'],
            'filters.definition_id' => ['sometimes', 'uuid'],
        ])->validate();
        /** @var array{key?:string,scope?:string,scope_key?:string,status?:string,visibility?:string,definition_id?:string} $filters */
        $filters = $validated['filters'];
        $blocks = $this->content(fn () => app(ContentReader::class)->paginate($filters, $actors->fromRequest($request), $request->integer('per_page', 25)));

        return response()->json(['data' => array_map(static fn (ContentBlock $block): array => ContentResponseData::block(ContentBlockData::fromModel($block)), $blocks->items()), 'meta' => ['current_page' => $blocks->currentPage(), 'last_page' => $blocks->lastPage(), 'per_page' => $blocks->perPage(), 'total' => $blocks->total()]]);
    }

    public function groups(Request $request, string $ownerType, string $ownerId, ContentActorFactory $actors, ContentOwnerRegistry $owners): JsonResponse
    {
        $owner = $this->content(fn () => $owners->resolve($ownerType, $ownerId));

        return response()->json(['data' => $this->content(fn () => app(ContentReader::class)->groups($owner, $actors->fromRequest($request)))->all()]);
    }

    public function placements(Request $request, string $ownerType, string $ownerId, string $group, ContentActorFactory $actors, ContentOwnerRegistry $owners): JsonResponse
    {
        $owner = $this->content(fn () => $owners->resolve($ownerType, $ownerId));

        return response()->json(['data' => $this->content(fn () => app(ContentReader::class)->placements($owner, $group, $actors->fromRequest($request)))->map(static fn (ContentPlacement $placement): array => ContentResponseData::placement(ContentPlacementData::fromModel($placement)))->all()]);
    }

    public function editor(Request $request, string $ownerType, string $ownerId, string $group, ContentActorFactory $actors, ContentOwnerRegistry $owners): JsonResponse
    {
        $owner = $this->content(fn () => $owners->resolve($ownerType, $ownerId));

        return response()->json(['data' => ContentResponseData::editor($this->content(fn () => app(GetOwnerContentEditorAction::class)->execute($owner, $group, $actors->fromRequest($request))))]);
    }

    public function store(Request $request, ContentActorFactory $actors): JsonResponse
    {
        $request->validate(CreateContentBlockData::rules());
        $data = ['definition' => $request->string('definition')->toString(), 'key' => $request->string('key')->toString()];
        if ($request->has('scope')) {
            $data['scope'] = $request->string('scope')->toString();
        }
        if ($request->has('scopeKey')) {
            $data['scopeKey'] = $request->string('scopeKey')->toString();
        }
        if ($request->has('visibility')) {
            $data['visibility'] = $request->string('visibility')->toString();
        }
        if ($request->has('values')) {
            $data['values'] = ContentArrays::stringMap($request->array('values'), 'content block values');
        }
        if ($request->has('translations')) {
            $data['translations'] = ContentArrays::translations($request->array('translations'), 'content block translations');
        }
        if ($request->has('metadata')) {
            $data['metadata'] = ContentArrays::stringMap($request->array('metadata'), 'content block metadata');
        }
        $actor = $actors->fromRequest($request);
        $block = $this->content(fn () => app(ContentBlocks::class)->create($data, $actor));

        return response()->json(['data' => ContentResponseData::block(ContentBlockData::fromModel($block))], 201);
    }

    public function show(Request $request, string $block, ContentActorFactory $actors): JsonResponse
    {
        return response()->json(['data' => ContentResponseData::block(ContentBlockData::fromModel($this->content(fn () => app(ContentReader::class)->get($block, $actors->fromRequest($request)))))]);
    }

    public function update(Request $request, string $block, ContentActorFactory $actors): JsonResponse
    {
        $request->validate(UpdateContentBlockData::rules());
        $data = ['expected_revision' => $request->integer('expected_revision')];
        if ($request->has('mode')) {
            $data['mode'] = $request->string('mode')->toString();
        }
        if ($request->has('visibility')) {
            $data['visibility'] = $request->input('visibility') === null ? null : $request->string('visibility')->toString();
        }
        if ($request->has('values')) {
            $data['values'] = ContentArrays::stringMap($request->array('values'), 'content block values');
        }
        if ($request->has('translations')) {
            $data['translations'] = ContentArrays::translations($request->array('translations'), 'content block translations');
        }
        if ($request->has('metadata')) {
            $data['metadata'] = ContentArrays::stringMap($request->array('metadata'), 'content block metadata');
        }
        $actor = $actors->fromRequest($request);
        $updated = $this->content(fn () => app(ContentBlocks::class)->update($block, $data, $actor));

        return response()->json(['data' => ContentResponseData::block(ContentBlockData::fromModel($updated))]);
    }

    public function publish(Request $request, string $block, ContentActorFactory $actors): JsonResponse
    {
        $request->validate(ExpectedRevisionData::rules());
        $expectedRevision = $request->integer('expected_revision');
        $actor = $actors->fromRequest($request);
        $published = $this->content(fn () => app(ContentBlocks::class)->publish($block, $expectedRevision, $actor));

        return response()->json(['data' => ContentResponseData::block(ContentBlockData::fromModel($published))]);
    }

    public function archive(Request $request, string $block, ContentActorFactory $actors): JsonResponse
    {
        $request->validate(ExpectedRevisionData::rules());
        $expectedRevision = $request->integer('expected_revision');
        $actor = $actors->fromRequest($request);
        $archived = $this->content(fn () => app(ContentBlocks::class)->archive($block, $expectedRevision, $actor));

        return response()->json(['data' => ContentResponseData::block(ContentBlockData::fromModel($archived))]);
    }

    public function destroy(Request $request, string $block, ContentActorFactory $actors): JsonResponse
    {
        $request->validate(ExpectedRevisionData::rules());
        $expectedRevision = $request->integer('expected_revision');
        $actor = $actors->fromRequest($request);
        $this->content(function () use ($actor, $block, $expectedRevision): bool {
            app(ContentBlocks::class)->delete($block, $expectedRevision, $actor);

            return true;
        });

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function restore(Request $request, string $block, ContentActorFactory $actors): JsonResponse
    {
        $request->validate(ExpectedRevisionData::rules());
        $expectedRevision = $request->integer('expected_revision');
        $actor = $actors->fromRequest($request);
        $restored = $this->content(fn () => app(ContentBlocks::class)->restore($block, $expectedRevision, $actor));

        return response()->json(['data' => ContentResponseData::block(ContentBlockData::fromModel($restored))]);
    }

    public function place(Request $request, string $ownerType, string $ownerId, string $group, string $block, ContentActorFactory $actors, ContentOwnerRegistry $owners): JsonResponse
    {
        $request->validate(PlaceContentBlockData::rules());
        $data = ['key' => $request->string('key')->toString()];
        if ($request->has('region')) {
            $data['region'] = $request->string('region')->toString();
        }
        if ($request->has('parentId')) {
            $data['parentId'] = $request->input('parentId') === null ? null : $request->string('parentId')->toString();
        }
        if ($request->has('sortOrder')) {
            $data['sortOrder'] = $request->integer('sortOrder');
        }
        if ($request->has('isVisible')) {
            $data['isVisible'] = $request->boolean('isVisible');
        }
        if ($request->has('overrides')) {
            $data['overrides'] = ContentArrays::stringMap($request->array('overrides'), 'content placement overrides');
        }
        $actor = $actors->fromRequest($request);
        $owner = $this->content(fn () => $owners->resolve($ownerType, $ownerId));
        $placement = $this->content(fn () => app(ContentPlacements::class)->place($block, $owner, $group, $data, $actor));

        return response()->json(['data' => ContentResponseData::placement(ContentPlacementData::fromModel($placement))], 201);
    }

    public function updatePlacement(Request $request, string $placement, ContentActorFactory $actors): JsonResponse
    {
        $request->validate(UpdateContentPlacementData::rules());
        $data = ['expected_revision' => $request->integer('expected_revision'), 'region' => $request->string('region')->toString(), 'sortOrder' => $request->integer('sortOrder'), 'isVisible' => $request->boolean('isVisible')];
        if ($request->has('parentId')) {
            $data['parentId'] = $request->input('parentId') === null ? null : $request->string('parentId')->toString();
        }
        if ($request->has('overrides')) {
            $data['overrides'] = ContentArrays::stringMap($request->array('overrides'), 'content placement overrides');
        }
        $actor = $actors->fromRequest($request);
        $updated = $this->content(fn () => app(ContentPlacements::class)->update($placement, $data, $actor));

        return response()->json(['data' => ContentResponseData::placement(ContentPlacementData::fromModel($updated))]);
    }

    public function destroyPlacement(Request $request, string $placement, ContentActorFactory $actors): JsonResponse
    {
        $request->validate(ExpectedRevisionData::rules());
        $expectedRevision = $request->integer('expected_revision');
        $actor = $actors->fromRequest($request);
        $this->content(function () use ($actor, $expectedRevision, $placement): bool {
            app(ContentPlacements::class)->delete($placement, $expectedRevision, $actor);

            return true;
        });

        return response()->json(['data' => ['deleted' => true]]);
    }
}
