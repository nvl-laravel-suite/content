<?php

declare(strict_types=1);
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Events\ContentBlockChanged;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Content\Services\ContentPlacements;
use Nvl\Content\Services\ContentReader;
use Nvl\Content\Services\ContentReferenceRegistry;
use Nvl\Content\Services\ContentSnapshotService;
use Nvl\Content\Tests\Fixtures\OwnershipActor;
use Nvl\Content\Tests\Fixtures\OwnershipCompany;
use Nvl\Content\Tests\Fixtures\OwnershipContentOwner;
use Nvl\Content\Tests\Fixtures\OwnershipContentScenario as Scenario;
use Nvl\Content\Tests\Fixtures\OwnershipReferenceResolver;
use Nvl\Content\Tests\OwnershipTestCase;
use Nvl\Content\Validation\ContentValidationContext;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\Services\OwnershipWriter;
use Nvl\Ownership\Tests\Fixtures\OptsIntoAdoptedSchemaReuse;
use Nvl\Ownership\ValueObjects\OwnershipAssignment;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Contracts\OwnershipQueuedJob;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

uses(OwnershipTestCase::class, OptsIntoAdoptedSchemaReuse::class);
it('matches canonical UUID content owner keys as exact text without accepting alternate spellings', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = app(OwnershipWriter::class)->create(OwnershipContentOwner::class, static function (OwnershipAssignment $assignment): OwnershipContentOwner {
            $host = new OwnershipContentOwner;
            $host->forceFill([...$assignment->attributes(), 'id' => 'a1111111-b222-7c33-8d44-e55555555555', 'name' => 'Canonical UUID host'])->save();

            return $host;
        });
        $connection = $host->getConnection();
        $placement = Scenario::place(Scenario::block(), $host);
        expect($placement->owner_id)->toBe($host->getKey());
        foreach ([strtoupper($host->getKey()), $host->getKey().' '] as $index => $forgedKey) {
            $row = [...$placement->getRawOriginal(), 'id' => (string) Str::uuid(), 'key' => 'forged-'.$index, 'owner_id' => $forgedKey];
            expect(fn () => $connection->transaction(fn () => $connection->table($placement->getTable())->insert($row)))->toThrow(QueryException::class);
        }
        expect(ContentPlacement::query()->count())->toBe(1);
    });
});
it('keeps mutable content locale and placement graphs inside one native owner', function (): void {
    $foreign = null;
    $foreignHost = null;
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(8), OwnershipActor::firstOrFail(), function () use (&$foreign, &$foreignHost): void {
        $foreign = Scenario::block();
        $foreignHost = Scenario::host();
        Scenario::place($foreign, $foreignHost);
    });
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function () use ($foreign, $foreignHost): void {
        $block = Scenario::block();
        $host = Scenario::host();
        $placement = Scenario::place($block, $host);
        expect(ContentModelRegistry::query()->count())->toBe(1)->and(ContentModelRegistry::query()->find($foreign->id))->toBeNull();
        $row = $placement->getRawOriginal();
        $row['id'] = (string) Str::uuid();
        $row['key'] = 'foreign';
        $row['content_block_id'] = $foreign->id;
        expect(fn () => DB::transaction(fn () => DB::table($placement->getTable())->insert($row)))->toThrow(QueryException::class);
        $row['content_block_id'] = $block->id;
        $row['owner_id'] = $foreignHost->id;
        expect(fn () => DB::transaction(fn () => DB::table($placement->getTable())->insert($row)))->toThrow(QueryException::class);
    });
});
it('binds immutable snapshots to the full native owner and rejects foreign authority before rendering', function (): void {
    $snapshot = null;
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(8), OwnershipActor::firstOrFail(), function () use (&$snapshot): void {
        $host = Scenario::host();
        Scenario::place(Scenario::block(), $host);
        $snapshot = app(ContentSnapshotService::class)->capture($host, 'main', SystemActor::Background);
        expect($snapshot['formatVersion'])->toBe(3)->and($snapshot['ownershipContext']['owner']['key'])->toBe('8');
    });
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function () use ($snapshot): void {
        expect(fn () => app(ContentSnapshotService::class)->render($snapshot, 'en', SystemActor::Background))->toThrow(OwnershipViolation::class);
    });
});
it('deletes an owned host and its canonical placements atomically through composed traits', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = Scenario::host();
        Scenario::place(Scenario::block(), $host);
        expect($host->delete())->toBeTrue()->and(ContentPlacement::query()->count())->toBe(0);
    });
});
it('preserves cancellation and rolls placement cleanup back with native host deletion', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = Scenario::host();
        Scenario::place(Scenario::block(), $host);
        $host::deleting(static fn (): bool => false);
        expect($host->delete())->toBeFalse()->and($host->exists)->toBeTrue()->and(ContentPlacement::query()->count())->toBe(1);
    });
});
it('captures native owner authority on scalar committed Content events', function (): void {
    $events = [];
    Event::listen(ContentBlockChanged::class, function ($event) use (&$events): void {
        $events[] = $event;
    });
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), fn () => Scenario::block());
    expect($events)->toHaveCount(1)->and($events[0])->toBeInstanceOf(OwnershipQueuedJob::class)->and($events[0]->ownershipJobEnvelope()->data['owner']['key'])->toBe('7')->and(serialize($events))->not->toContain('Private copy');
});
it('keeps native Content summaries bounded for one and twenty five canonical hosts', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $block = Scenario::block();
        $host = Scenario::host();
        $placement = Scenario::place($block, $host);
        $hostIds = [$host->getKey()];
        $hostRows = $placementRows = [];
        for ($index = 1; $index < 25; $index++) {
            $hostId = $host->newUniqueId();
            $hostIds[] = $hostId;
            $hostRows[] = [...$host->getRawOriginal(), 'id' => $hostId];
            $placementRows[] = [...$placement->getRawOriginal(), 'id' => $placement->newUniqueId(), 'owner_id' => $hostId];
        }
        $connection = $host->getConnection();
        $connection->transaction(static function () use ($connection, $host, $placement, $hostRows, $placementRows): void {
            $connection->table($host->getTable())->insert($hostRows);
            $connection->table($placement->getTable())->insert($placementRows);
        });
        $hostsById = $host->newQuery()->findMany($hostIds)->keyBy('id');
        $hosts = array_map(static fn (string $id) => $hostsById->get($id), $hostIds);

        $measure = function (array $hosts): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $result = app(ContentReader::class)->summaries($hosts, 'main', SystemActor::Background);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            expect($result)->toHaveCount(count($hosts));

            return $count;
        };
        $one = $measure([$hosts[0]]);
        expect($measure($hosts))->toBeLessThanOrEqual($one + 2);
    });
});
it('authorizes canonical host facts rather than forged batch handles', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = Scenario::host();
        Scenario::place(Scenario::block(), $host);
        $host->name = 'private';
        $host->save();
        $host->name = 'public';
        app()->instance(ContentAuthorization::class, new class implements ContentAuthorization
        {
            public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
            {
                if ($ability === ContentAbility::ListPlacements && $owner?->getAttribute('name') === 'private') {
                    throw new AuthorizationException('Private host');
                }
            }
        });
        expect(fn () => app(ContentReader::class)->summaries([$host], 'main', SystemActor::Background))->toThrow(AuthorizationException::class);
    });
});
it('retains canonical host and composition identity in raw placement parent writes', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $block = Scenario::block();
        $first = Scenario::place($block, Scenario::host());
        $second = Scenario::place($block, Scenario::host());
        expect(fn () => DB::transaction(fn () => DB::table($first->getTable())->where('id', $first->id)->update(['parent_id' => $second->id])))->toThrow(QueryException::class);
    });
});
it('deletes owned placement trees from leaves before removing their canonical host', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $block = Scenario::block();
        $host = Scenario::host();
        $parent = Scenario::place($block, $host);
        app(ContentPlacements::class)->place($block, $host, 'main', ['key' => 'child', 'region' => 'main', 'parentId' => $parent->id], SystemActor::Background);
        expect($host->delete())->toBeTrue()->and(ContentPlacement::query()->count())->toBe(0);
    });
});
it('requires reference adapters to return the exact requested persisted key', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = Scenario::host();
        OwnershipReferenceResolver::$record = $host;
        $registry = app(ContentReferenceRegistry::class);
        $registry->register('owned-reference', OwnershipReferenceResolver::class);
        $context = new ContentValidationContext(SystemActor::Background, 'en', 'reference', ContentVisibility::Private);
        expect(fn () => $registry->display('owned-reference', (string) Str::uuid(), $context))->toThrow(OwnershipViolation::class);
    });
});
