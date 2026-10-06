<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Config\Repository;

/** Identifies compiler inputs without scanning files or retaining request context. @internal */
final readonly class ContentCompilationSignature
{
    public const string ABI = 'nvl-content-compiled-v1';

    /** Retain effective configuration and declared extension identities. */
    public function __construct(
        private Repository $configuration,
        private ContentFieldTypeRegistry $fieldTypes,
        private ContentFieldPresetRegistry $presets,
        private ContentOwnerRegistry $owners,
        private ContentReferenceRegistry $references,
        private CanonicalJson $json,
        private ContentPayloadGuard $guard,
    ) {}

    /** Hash all compilation-relevant configuration and registered class identities. */
    public function current(): string
    {
        $inputs = ['compiler_abi' => self::ABI];
        foreach (['definitions', 'definition_paths', 'required_definition_paths', 'allowed_definition_roots', 'definition_limits', 'scopes', 'owners', 'references', 'field_types', 'presets', 'links', 'locales', 'validation', 'rich_text', 'media'] as $key) {
            $inputs[$key] = $this->configuration->get('nvl-content.'.$key);
        }
        $inputs['registered_field_types'] = $this->fieldTypes->compilationIdentities();
        $inputs['registered_presets'] = $this->presets->compilationIdentities();
        $inputs['registered_owners'] = $this->owners->compilationIdentities();
        $inputs['registered_references'] = $this->references->compilationIdentities();
        $this->guard->json($inputs, 'Content compilation signature', 16_777_216, 64);

        return $this->json->hash($inputs);
    }
}
