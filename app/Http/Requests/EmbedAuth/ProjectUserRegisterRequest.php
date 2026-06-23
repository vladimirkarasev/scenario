<?php

declare(strict_types=1);

namespace App\Http\Requests\EmbedAuth;

use App\DTO\EmbedAuth\ProjectUserRegisterData;
use Illuminate\Foundation\Http\FormRequest;
use Module\Groups\DTO\GroupRegistrationData;

final class ProjectUserRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'max:100'],
            'group' => ['nullable', 'array'],
            'group.slug' => ['required_with:group', 'string', 'max:255', 'alpha_dash'],
            'group.name' => ['required_with:group', 'string', 'max:255'],
            'group.ext_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): ProjectUserRegisterData
    {
        $groupData = null;
        if ($this->filled('group')) {
            $groupData = new GroupRegistrationData(
                slug: $this->string('group.slug')->toString(),
                name: $this->string('group.name')->toString(),
                extId: $this->filled('group.ext_id') ? $this->string('group.ext_id')->toString() : null,
            );
        }

        return new ProjectUserRegisterData(
            login: $this->string('login')->toString(),
            email: $this->filled('email') ? $this->string('email')->toString() : null,
            name: $this->string('name')->toString(),
            externalId: $this->filled('external_id') ? $this->string('external_id')->toString() : null,
            roles: array_values(array_map(static fn (mixed $r): string => is_string($r) ? $r : '', $this->array('roles'))),
            group: $groupData,
        );
    }
}
