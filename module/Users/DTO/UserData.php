<?php

declare(strict_types=1);

namespace Module\Users\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UserData
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $groupIds
     */
    public function __construct(
        public string $name,
        public ?string $fio,
        public string $email,
        public string $login,
        public ?string $externalId,
        public ?string $password,
        public array $roles = [],
        public array $groupIds = [],
        public bool $rolesProvided = false,
        public bool $groupIdsProvided = false,
    ) {
    }

    public static function fromRequest(FormRequest $request): self
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return new self(
            name: self::requiredString($data, 'name'),
            fio: self::nullableString($data['fio'] ?? null),
            email: self::requiredString($data, 'email'),
            login: self::requiredString($data, 'login'),
            externalId: self::nullableString($data['external_id'] ?? null),
            password: self::nullableString($data['password'] ?? null),
            roles: array_values(array_filter(
                is_array($data['roles'] ?? null) ? $data['roles'] : [],
                is_string(...),
            )),
            groupIds: array_values(array_filter(
                is_array($data['group_ids'] ?? null) ? $data['group_ids'] : [],
                is_string(...),
            )),
            rolesProvided: array_key_exists('roles', $data),
            groupIdsProvided: array_key_exists('group_ids', $data),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param  array<string, mixed>  $data */
    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value)) {
            throw new \UnexpectedValueException("Validated field [{$key}] must be a string.");
        }

        return $value;
    }
}
