<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Nvl\Content\Schema\ContentSchema;
use Nvl\Content\Validation\ContentSchemaValidator;
use Nvl\Support\Doctor\DoctorCheck;
use Throwable;

/** Validates unused preset schemas through explicit read-only diagnostics. @internal */
final readonly class ContentPresetDiagnostics
{
    /** Retain the registered presets and their native validation collaborators. */
    public function __construct(
        private ContentFieldPresetRegistry $presets,
        private ContentSchemaCompiler $compiler,
        private ContentSchemaValidator $schemas,
    ) {}

    /**
     * Inspect every preset independently without stopping at the first invalid schema.
     *
     * @return list<DoctorCheck>
     */
    public function inspect(): array
    {
        $checks = [];
        foreach ($this->presets->all() as $preset) {
            try {
                $this->schemas->validate(new ContentSchema([$this->compiler->compilePreset($preset)]));
                $checks[] = new DoctorCheck('presets.'.$preset->alias().'.schema', 'error', true, 'The preset schema is valid.');
            } catch (Throwable $exception) {
                $checks[] = new DoctorCheck('presets.'.$preset->alias().'.schema', 'error', false, 'Review this preset definition: '.$exception->getMessage());
            }
        }

        return $checks;
    }
}
