<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nvl\Content\Services\ContentLocalePolicy;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentRenderer;
use Nvl\Content\Tests\Fixtures\Consumer\ContentActorFactory;
use Nvl\Content\Tests\Fixtures\Consumer\Data\Queries\ContentLocaleQueryData;
use Nvl\Content\Tests\Fixtures\Consumer\Http\ContentResponseData;

/**
 * Optional public-safe headless composition endpoint.
 */
final class ContentCompositionController extends ContentController
{
    public function show(Request $request, string $ownerType, string $ownerId, string $group, ContentActorFactory $actors, ContentOwnerRegistry $owners, ContentLocalePolicy $locales): JsonResponse
    {
        $request->validate(ContentLocaleQueryData::rules());
        $locale = $request->input('locale') === null ? $locales->current() : $request->string('locale')->toString();
        $owner = $this->content(fn () => $owners->resolve($ownerType, $ownerId));
        $composition = $this->content(fn () => app(ContentRenderer::class)->render($owner, ['group' => $group, 'locale' => $locale, 'actor' => $actors->fromRequest($request, true), 'publicOnly' => true]));

        return response()->json(['data' => ContentResponseData::composition($composition)]);
    }

    public function preview(Request $request, string $ownerType, string $ownerId, string $group, ContentActorFactory $actors, ContentOwnerRegistry $owners, ContentLocalePolicy $locales): JsonResponse
    {
        $request->validate(ContentLocaleQueryData::rules());
        $locale = $request->input('locale') === null ? $locales->current() : $request->string('locale')->toString();
        $owner = $this->content(fn () => $owners->resolve($ownerType, $ownerId));
        $composition = $this->content(fn () => app(ContentRenderer::class)->render($owner, ['group' => $group, 'locale' => $locale, 'actor' => $actors->fromRequest($request, true), 'publicOnly' => false]));

        return response()->json(['data' => ContentResponseData::composition($composition)]);
    }
}
