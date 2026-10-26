<?php

declare(strict_types=1);
use Nvl\Content\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature', __DIR__.'/Unit');
/** Read the native rendered tree as a consumer would. */
function contentRenderedValue(array $composition, string $path, bool $first = false): mixed
{
    $flattened = [];
    $append = function (array $block) use (&$append, &$flattened): void {
        $flattened[] = $block;
        foreach ($block['children'] as $child) {
            $append($child);
        }
    };
    foreach ($composition['blocks'] as $block) {
        $append($block);
    }
    [$key, $field] = array_pad(explode('.', $path, 2), 2, null);
    if (! $first && $field !== null) {
        foreach ($flattened as $block) {
            if ($block['key'] === $key) {
                return data_get($block['values'], $field);
            }
        }
    }
    foreach ($flattened as $block) {
        $value = data_get($block['values'], $path);
        if ($value !== null) {
            return $value;
        }
    }

    return null;
}
