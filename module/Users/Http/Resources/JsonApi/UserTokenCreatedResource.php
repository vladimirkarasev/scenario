<?php

declare(strict_types=1);

namespace Module\Users\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * @mixin NewAccessToken
 */
final class UserTokenCreatedResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->accessToken->id,
            'name' => $this->accessToken->name,
            'abilities' => $this->accessToken->abilities,
            'last_used_at' => null,
            'created_at' => $this->accessToken->created_at?->toIso8601String(),
            'expires_at' => $this->accessToken->expires_at?->toIso8601String(),
            'plain_text_token' => $this->plainTextToken,
        ];
    }
}
