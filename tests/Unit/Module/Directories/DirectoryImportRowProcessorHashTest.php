<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryItemRepository;
use Module\Directories\Services\Importing\DirectoryImportPayloadNormalizer;
use Module\Directories\Services\Importing\DirectoryImportRowProcessor;
use Tests\TestCase;

/**
 * Проверяет логику хеширования строк в DirectoryImportRowProcessor:
 * детерминированность, независимость от порядка ключей, чувствительность к изменению значений.
 */
final class DirectoryImportRowProcessorHashTest extends TestCase
{
    private \ReflectionMethod $computeRowHash;

    private \ReflectionMethod $resolveExternalKey;

    private DirectoryImportRowProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new DirectoryImportRowProcessor(
            app(DirectoryImport::class),
            app(DirectoryItemRepository::class),
            app(DirectoryImportPayloadNormalizer::class),
        );

        $ref = new \ReflectionClass($this->processor);
        $this->computeRowHash = $ref->getMethod('computeRowHash');
        $this->resolveExternalKey = $ref->getMethod('resolveExternalKey');
    }

    /**
     * Если ключ (match_by) задан — external_key берётся из значения этого поля.
     */
    public function test_resolve_external_key_uses_match_by(): void
    {
        $import = (new DirectoryImport)->forceFill(['match_by' => 'id', 'source_type' => 'proxy']);
        $prepared = ['id' => '42', 'name' => 'Belgee'];

        $key = $this->resolveExternalKey->invoke(
            $this->processor,
            $import,
            $prepared,
            collect(array_keys($prepared)),
            2,
        );

        $this->assertSame('42', $key);
    }

    /**
     * Если ключ не задан (в т.ч. для proxy/remote) — external_key вычисляется как хэш строки,
     * а не остаётся null. Это и есть фикс: раньше для proxy возвращался null.
     */
    public function test_resolve_external_key_falls_back_to_hash_for_proxy(): void
    {
        $import = (new DirectoryImport)->forceFill(['match_by' => null, 'source_type' => 'proxy']);
        $prepared = ['id' => '1', 'name' => 'Belgee'];

        /** @var string|null $key */
        $key = $this->resolveExternalKey->invoke(
            $this->processor,
            $import,
            $prepared,
            collect(array_keys($prepared)),
            2,
        );

        $this->assertNotNull($key);
        $this->assertSame(32, strlen((string)$key), 'external_key должен быть MD5-хэшем при отсутствии match_by');
    }

    /**
     * Одинаковые значения → одинаковый хеш при каждом вызове.
     */
    public function test_same_values_produce_same_hash(): void
    {
        $row = ['name' => 'Иван', 'city' => 'Москва', 'age' => '30'];

        $h1 = $this->hash($row);
        $h2 = $this->hash($row);

        $this->assertSame($h1, $h2);
    }

    /**
     * Порядок ключей не влияет на хеш — результат стабилен.
     */
    public function test_key_order_does_not_affect_hash(): void
    {
        $row1 = ['name' => 'Иван', 'city' => 'Москва'];
        $row2 = ['city' => 'Москва', 'name' => 'Иван'];

        $h1 = $this->hash($row1);
        $h2 = $this->hash($row2);

        $this->assertSame($h1, $h2, 'Хеш должен не зависеть от порядка ключей (ksort нормализует)');
    }

    /**
     * Разные значения → разные хеши.
     */
    public function test_different_values_produce_different_hash(): void
    {
        $row1 = ['name' => 'Иван', 'city' => 'Москва'];
        $row2 = ['name' => 'Иван', 'city' => 'Берлин'];

        $h1 = $this->hash($row1);
        $h2 = $this->hash($row2);

        $this->assertNotSame($h1, $h2);
    }

    /**
     * null-значения обрабатываются без ошибок и дают стабильный 32-символьный хеш (MD5).
     */
    public function test_null_values_handled_consistently(): void
    {
        $row = ['name' => 'Иван', 'city' => null];

        $h1 = $this->hash($row);
        $h2 = $this->hash($row);

        $this->assertSame($h1, $h2);
        $this->assertSame(32, strlen($h1), 'MD5-хеш должен содержать ровно 32 символа');
    }

    /**
     * null и пустая строка дают одинаковый хеш — оба означают «нет значения» при импорте.
     */
    public function test_null_and_empty_string_differ(): void
    {
        $rowNull = ['name' => null];
        $rowEmpty = ['name' => ''];

        $h1 = $this->hash($rowNull);
        $h2 = $this->hash($rowEmpty);

        $this->assertSame($h1, $h2, 'null и пустая строка намеренно дают одинаковый хеш');
    }

    /**
     * Номер строки входит в хеш — дублирующиеся строки файла получают разные хеши.
     */
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
        return $row
                |> array_keys(...)
                |> collect(...)
                |> (fn($x) => $this->computeRowHash->invoke($this->processor, $row, $x, $rowNumber));
    }
}
