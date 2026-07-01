<?php

declare(strict_types=1);

namespace Module\Users\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Module\Groups\Models\UserGroup;
use Module\Projects\Models\Project;
use Module\Users\QueryBuilders\UserBuilder;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $fio
 * @property string $email
 * @property string|null $sitekey
 * @property string|null $host
 * @property string|null $login
 * @property string|null $external_id
 * @property string|null $project_id
 * @property bool $is_system
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 * @property-read Collection<int, UserGroup> $groups
 * @property-read Collection<int, Role> $roles
 */
#[Table('users')]
#[UseEloquentBuilder(UserBuilder::class)]
#[UseFactory(UserFactory::class)]
#[Fillable(['name', 'fio', 'email', 'password', 'sitekey', 'host', 'login', 'external_id', 'project_id', 'is_system'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** @param  string[]  $abilities */
    public function createToken(
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ): NewAccessToken {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new NewAccessToken($token, $plainTextToken);
    }

    /** @return BelongsToMany<UserGroup, User> */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_members', 'user_id', 'user_group_id')
            ->withTimestamps();
    }

    /** @return BelongsTo<Project, User> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_system' => 'boolean',
        ];
    }
}
