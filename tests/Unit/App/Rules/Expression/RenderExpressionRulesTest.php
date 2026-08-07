<?php

declare(strict_types=1);

namespace Tests\Unit\App\Rules\Expression;

use App\Rules\Expression\RenderExpressionBatchRules;
use App\Rules\Expression\RenderExpressionRules;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class RenderExpressionRulesTest extends TestCase
{
    public function test_rejects_context_with_excessive_depth(): void
    {
        $context = ['value' => 'ok'];
        for ($level = 0; $level < 9; $level++) {
            $context = ['nested' => $context];
        }

        $validator = $this->validator([
            'template' => '{{ value }}',
            'context' => $context,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context', $validator->errors()->toArray());
    }

    public function test_rejects_context_without_named_keys(): void
    {
        $validator = $this->validator([
            'template' => '{{ value }}',
            'context' => ['first', 'second'],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context', $validator->errors()->toArray());
    }

    public function test_rejects_template_with_too_many_expressions(): void
    {
        $validator = $this->validator([
            'template' => str_repeat('{{ value }}', 51),
            'context' => ['value' => 'test'],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('template', $validator->errors()->toArray());
    }

    public function test_accepts_bounded_template_and_named_context(): void
    {
        $validator = $this->validator([
            'template' => '{{ implode(", ", values) }}',
            'context' => ['values' => ['one', 'two']],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_batch_accepts_one_template_and_contexts_with_unique_ids(): void
    {
        $validator = Validator::make([
            'item' => ['template' => '{{ city }}'],
            'context' => [
                ['id' => 1, 'data' => ['city' => 'Москва']],
                ['id' => 'spb', 'data' => ['city' => 'Санкт-Петербург']],
            ],
        ], RenderExpressionBatchRules::get());

        $this->assertTrue($validator->passes());
    }

    public function test_batch_rejects_context_without_id(): void
    {
        $validator = Validator::make([
            'item' => ['template' => '{{ city }}'],
            'context' => [
                ['data' => ['city' => 'Москва']],
            ],
        ], RenderExpressionBatchRules::get());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context.0.id', $validator->errors()->toArray());
    }

    public function test_batch_rejects_duplicate_ids(): void
    {
        $validator = Validator::make([
            'item' => ['template' => '{{ city }}'],
            'context' => [
                ['id' => 'duplicate', 'data' => ['city' => 'Москва']],
                ['id' => 'duplicate', 'data' => ['city' => 'Санкт-Петербург']],
            ],
        ], RenderExpressionBatchRules::get());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context.0.id', $validator->errors()->toArray());
        $this->assertArrayHasKey('context.1.id', $validator->errors()->toArray());
    }

    public function test_batch_rejects_context_data_without_named_keys(): void
    {
        $validator = Validator::make([
            'item' => ['template' => '{{ city }}'],
            'context' => [
                ['id' => 1, 'data' => ['Москва', 'Санкт-Петербург']],
            ],
        ], RenderExpressionBatchRules::get());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context.0.data', $validator->errors()->toArray());
    }

    public function test_batch_rejects_associative_context_collection(): void
    {
        $validator = Validator::make([
            'item' => ['template' => '{{ city }}'],
            'context' => [
                'first' => ['id' => 1, 'data' => ['city' => 'Москва']],
            ],
        ], RenderExpressionBatchRules::get());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context', $validator->errors()->toArray());
    }

    public function test_batch_rejects_dangerous_object_id(): void
    {
        $validator = Validator::make([
            'item' => ['template' => '{{ city }}'],
            'context' => [
                ['id' => '__proto__', 'data' => ['city' => 'Москва']],
            ],
        ], RenderExpressionBatchRules::get());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context.0.id', $validator->errors()->toArray());
    }

    /** @param array<string, mixed> $payload */
    private function validator(array $payload): ValidatorContract
    {
        return Validator::make($payload, RenderExpressionRules::get());
    }
}
