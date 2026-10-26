<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Schema\ContentSchema;

/**
 * Stable JSON encoding used for definition hashes and revision snapshots.
 */
final class CanonicalJson
{
    /** Native Content operation. */
    public function encode(mixed $value): string
    {
        return json_encode($this->sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Native Content operation. */
    public function hash(mixed $value): string
    {
        return hash('sha256', $this->encode($value));
    }

    /** Native Content operation. */
    private function sort(mixed $value): mixed
    {
        if ($value instanceof ContentSchema) {
            $value = ['fields' => $value->fields];
        } elseif ($value instanceof ContentFieldDefinition) {
            $value = ['key' => $value->key, 'type' => $value->type, 'label' => $value->label, 'required' => $value->required, 'localized' => $value->localized, 'default' => $value->default, 'fields' => $value->fields, 'item' => $value->item, 'settings' => $value->settings, 'preset' => $value->preset];
        }

        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sort($item), $value);
        }
        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->sort($item), $value);
    }
}
