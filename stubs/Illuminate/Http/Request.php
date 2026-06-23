<?php

namespace Illuminate\Http;

final class Request
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $messages
     * @param  array<string, string>  $attributes
     * @return array<string, mixed>
     */
    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $messages
     * @param  array<string, string>  $attributes
     * @return array<string, mixed>
     */
    public function validateWithBag(string $errorBag, array $rules, array $messages = [], array $attributes = []): array
    {
        return [];
    }
}
