<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data\Queries;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class ContentLocaleQueryData extends Data
{
    use DataTransform;

    public function __construct(public readonly ?string $locale = null) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['locale' => ['nullable', 'string', 'max:35']];
    }
}
