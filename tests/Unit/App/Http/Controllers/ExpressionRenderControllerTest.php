<?php

declare(strict_types=1);

namespace Tests\Unit\App\Http\Controllers;

use App\Http\Controllers\ExpressionRenderController;
use App\Http\Requests\Expression\RenderExpressionRequest;
use App\Services\Expression\ExpressionRenderService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ExpressionRenderControllerTest extends TestCase
{
    public function test_renders_template_with_context_through_application_expression_service(): void
    {
        $request = $this->validatedRequest([
            'template' => '{{ implode(", ", Категории) }}',
            'context' => [
                'Категории' => ['Первый', 'Второй', 'Третий'],
            ],
        ]);

        $response = new ExpressionRenderController(app(ExpressionRenderService::class))($request);

        $this->assertSame([
            'data' => [
                'rendered' => 'Первый, Второй, Третий',
            ],
        ], $response->getData(true));
    }

    /** @param array<string, mixed> $payload */
    private function validatedRequest(array $payload): RenderExpressionRequest
    {
        $request = RenderExpressionRequest::create('/api/expression/render', 'POST', $payload);
        $validator = $this->validator($payload, $request);
        $this->assertTrue($validator->passes(), json_encode($validator->errors()->toArray()) ?: 'Validation failed.');
        $request->setValidator($validator);

        return $request;
    }

    /** @param array<string, mixed> $payload */
    private function validator(
        array $payload,
        ?RenderExpressionRequest $request = null,
    ): ValidatorContract {
        $request ??= RenderExpressionRequest::create('/api/expression/render', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules());

        return $validator;
    }
}
