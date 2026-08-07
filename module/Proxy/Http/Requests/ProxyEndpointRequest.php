<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Proxy\Enums\ProxyEndpointType;
use Module\Proxy\Proxies\HandlerCatalog;

final class ProxyEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $allowedHandlersRaw = config('proxy.allowed_handlers', []);
        $allowedHandlers = is_array($allowedHandlersRaw) ? $allowedHandlersRaw : [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(ProxyEndpointType::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'handler_class' => [
                'required',
                'string',
                [...HandlerCatalog::classes(), ...$allowedHandlers] |> Rule::in(...),
            ],
            'connection_id' => ['nullable', 'integer', 'exists:proxy_connections,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['required', 'uuid', 'exists:categories,id'],
            'credentials' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'is_mocked' => ['sometimes', 'boolean'],
            'mock_responses' => ['nullable', 'array'],
            'mock_responses.*.name' => ['nullable', 'string', 'max:255'],
            'mock_responses.*.status' => ['required_with:mock_responses.*', 'integer', 'between:100,599'],
            'mock_responses.*.body' => ['nullable', 'array'],
            'mock_responses.*.headers' => ['nullable', 'array'],
            'mock_responses.*.is_active' => ['boolean'],
        ];
    }
}
