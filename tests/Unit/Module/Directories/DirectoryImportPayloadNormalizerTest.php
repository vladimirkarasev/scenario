<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Module\Directories\Services\Importing\DirectoryImportPayloadNormalizer;
use Tests\TestCase;

final class DirectoryImportPayloadNormalizerTest extends TestCase
{
    public function test_numeric_excel_column_keys_are_normalized_without_type_error(): void
    {
        $normalizer = app(DirectoryImportPayloadNormalizer::class);

        $mapping = $normalizer->normalizeMapping([0 => 'name']);
        $prepared = $normalizer->prepareRow(
            [0 => 'Test'],
            collect($mapping),
            collect([['key' => 'name']])->keyBy('key'),
        );

        $this->assertSame([0 => 'name'], $mapping);
        $this->assertSame(['name' => 'Test'], $prepared);
    }
}
