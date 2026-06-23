<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class DashboardUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'sitekey' => $this->sitekey,
            'host' => $this->host,
            'login' => $this->login,
            'roles' => $this->getRoleNames()->values()->all(),
        ];
    }
}
