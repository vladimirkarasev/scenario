<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Support;

use Module\Scenario\Support\DirectoryListShape;
use PHPUnit\Framework\TestCase;

final class DirectoryListShapeTest extends TestCase
{
    public function test_is_other_detects_default_sentinel_key(): void
    {
        $this->assertTrue(DirectoryListShape::isOther($this->otherShape()));
    }

    public function test_is_other_honors_custom_key(): void
    {
        $shape = $this->otherShape(externalKey: 'other_code');

        $this->assertTrue(DirectoryListShape::isOther($shape, 'other_code'));
        $this->assertFalse(DirectoryListShape::isOther($shape));
    }

    public function test_is_other_false_for_regular_item(): void
    {
        $regular = [
            'id' => '42',
            'label' => 'Москва',
            'data' => ['name' => 'Москва'],
            'parent_id' => null,
            'external_key' => 'msk',
        ];

        $this->assertFalse(DirectoryListShape::isOther($regular));
    }

    public function test_other_text_returns_typed_value(): void
    {
        $this->assertSame('Своё значение', DirectoryListShape::otherText($this->otherShape('Своё значение')));
    }

    public function test_other_text_null_when_empty_or_absent(): void
    {
        $this->assertNull(DirectoryListShape::otherText($this->otherShape('')));
        $this->assertNull(DirectoryListShape::otherText($this->otherShape(null)));
    }

    public function test_other_text_null_for_non_shape(): void
    {
        $this->assertNull(DirectoryListShape::otherText('not-a-shape'));
        $this->assertNull(DirectoryListShape::otherText(null));
    }

    /** @return array<string, mixed> */
    private function otherShape(?string $otherText = null, string $externalKey = '__other__'): array
    {
        return [
            'id' => '-1',
            'label' => $otherText ?? 'Другой',
            'data' => ['name' => 'Другой'],
            'parent_id' => null,
            'external_key' => $externalKey,
            'other_text' => $otherText,
        ];
    }
}
