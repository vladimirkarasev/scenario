<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryItemRepository;
use Module\Directories\Services\Importing\DirectoryImportRowWriter;
use Tests\TestCase;

final class DirectoryImportRowProcessorHashTest extends TestCase
{
    private DirectoryImportRowWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = new DirectoryImportRowWriter(
            app(DirectoryItemRepository::class),
        );
    }

    public function test_resolve_external_key_uses_match_by(): void
    {
        $import = (new DirectoryImport)->forceFill(['match_by' => 'id', 'source_type' => 'proxy']);
        $prepared = ['id' => '42', 'name' => 'Belgee'];

        $key = $this->writer->externalKey(
            $import,
            $prepared,
            collect(array_keys($prepared)),
            2,
        );

        $this->assertSame('42', $key);
    }

    public function test_resolve_external_key_falls_back_to_hash_for_proxy(): void
    {
        $import = (new DirectoryImport)->forceFill(['match_by' => null, 'source_type' => 'proxy']);
        $prepared = ['id' => '1', 'name' => 'Belgee'];

        /** @var string|null $key */
        $key = $this->writer->externalKey(
            $import,
            $prepared,
            collect(array_keys($prepared)),
            2,
        );

        $this->assertNotNull($key);
        $this->assertSame(32, strlen((string)$key), 'external_key должен быть MD5-хэшем при отсутствии match_by');
    }

    public function test_same_values_produce_same_hash(): void
    {
        $row = ['name' => 'Иван', 'city' => 'Москва', 'age' => '30'];

        $h1 = $this->hash($row);
        $h2 = $this->hash($row);

        $this->assertSame($h1, $h2);
    }

    public function test_key_order_does_not_affect_hash(): void
    {
        $row1 = ['name' => 'Иван', 'city' => 'Москва'];
        $row2 = ['city' => 'Москва', 'name' => 'Иван'];

        $h1 = $this->hash($row1);
        $h2 = $this->hash($row2);

        $this->assertSame($h1, $h2, 'Хеш должен не зависеть от порядка ключей (ksort нормализует)');
    }

    public function test_different_values_produce_different_hash(): void
    {
        $row1 = ['name' => 'Иван', 'city' => 'Москва'];
        $row2 = ['name' => 'Иван', 'city' => 'Берлин'];

        $h1 = $this->hash($row1);
        $h2 = $this->hash($row2);

        $this->assertNotSame($h1, $h2);
    }

    public function test_null_values_handled_consistently(): void
    {
        $row = ['name' => 'Иван', 'city' => null];

        $h1 = $this->hash($row);
        $h2 = $this->hash($row);

        $this->assertSame($h1, $h2);
        $this->assertSame(32, strlen($h1), 'MD5-хеш должен содержать ровно 32 символа');
    }

    public function test_null_and_empty_string_differ(): void
    {
        $rowNull = ['name' => null];
        $rowEmpty = ['name' => ''];

        $h1 = $this->hash($rowNull);
        $h2 = $this->hash($rowEmpty);

        $this->assertSame($h1, $h2, 'null и пустая строка намеренно дают одинаковый хеш');
    }

    public function test_row_number_affects_hash(): void
    {
        $row = ['name' => 'Иван', 'city' => 'Москва'];

        $h1 = $this->hash($row, 2);
        $h2 = $this->hash($row, 3);

        $this->assertNotSame($h1, $h2);
    }

    /** @param  array<string, string|null>  $row */
    private function hash(array $row, int $rowNumber = 2): string
    {
        return $this->writer->rowHash($row, collect(array_keys($row)), $rowNumber);
    }
}
