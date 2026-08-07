<?php

declare(strict_types=1);

namespace Tests\Unit\App\Http\Controllers;

use App\Http\Controllers\ExpressionRenderBatchController;
use App\Http\Requests\Expression\RenderExpressionBatchRequest;
use App\Services\Expression\ExpressionRenderService;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ExpressionRenderBatchControllerTest extends TestCase
{
    public function test_renders_one_template_for_each_context_and_keys_results_by_id(): void
    {
        $payload = [
            'item' => ['template' => '{{ Регион ?: Город ?: Категория ?: Оператор }}'],
            'context' => [
                [
                    'id' => 1,
                    'data' => ['Город' => 'Москва', 'Регион' => ''],
                ],
                [
                    'id' => 2,
                    'data' => ['Категория' => 'СПБ', 'Оператор' => 'Лен-область'],
                ],
            ],
        ];
        $request = RenderExpressionBatchRequest::create('/api/expression/render-batch', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules());
        $this->assertTrue($validator->passes(), json_encode($validator->errors()->toArray()) ?: 'Validation failed.');
        $request->setValidator($validator);

        $response = new ExpressionRenderBatchController(app(ExpressionRenderService::class))($request);

        $this->assertSame([
            'data' => [
                1 => 'Москва',
                2 => 'СПБ',
            ],
        ], $response->getData(true));
    }

    public function test_exposes_original_row_through_item_alias(): void
    {
        $payload = [
            'item' => ['template' => '{{ item["city-name"] }}'],
            'context' => [
                ['id' => 'msk', 'data' => ['city-name' => 'Москва']],
            ],
        ];
        $request = RenderExpressionBatchRequest::create('/api/expression/render-batch', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules());
        $this->assertTrue($validator->passes(), json_encode($validator->errors()->toArray()) ?: 'Validation failed.');
        $request->setValidator($validator);

        $response = new ExpressionRenderBatchController(app(ExpressionRenderService::class))($request);

        $this->assertSame(['data' => ['msk' => 'Москва']], $response->getData(true));
    }
}
