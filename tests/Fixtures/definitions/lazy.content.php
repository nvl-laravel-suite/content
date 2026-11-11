<?php

declare(strict_types=1);

use Nvl\Content\Tests\Fixtures\LazyBootContentTestCase;

LazyBootContentTestCase::$definitionReads++;

return ['key' => 'lazy', 'name' => 'Lazy', 'allowed_scopes' => ['site'], 'schema' => ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Title']]]];
