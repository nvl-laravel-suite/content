<?php

declare(strict_types=1);
use Nvl\Content\Tests\Fixtures\ContentPrefixedOwnershipTestCase;
use Nvl\Content\Tests\Fixtures\OwnershipActor;
use Nvl\Content\Tests\Fixtures\OwnershipCompany;
use Nvl\Ownership\Services\OwnershipRunner;

uses(ContentPrefixedOwnershipTestCase::class);
it('prepares backfills and activates prefixed content storage without duplicating constraints', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        expect(OwnershipCompany::findOrFail(7)->getConnection()->getTablePrefix())->toBe('review_');
    });
});
