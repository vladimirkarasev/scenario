<?php

declare(strict_types=1);

namespace Module\Users\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Module\Users\QueryBuilders\RoleBuilder;

/**
 * @property string|null $title
 * @property string|null $description
 * @property bool $is_system
 */
#[UseEloquentBuilder(RoleBuilder::class)]
final class Role extends SpatieRole
{
    /** @var list<string> */
    protected $fillable = ['name', 'guard_name', 'title', 'description', 'is_system'];

    /** @return MorphToMany<User, $this> */
    #[\Override]
    public function users(): MorphToMany
    {
        $table = $this->permissionConfig('table_names.model_has_roles', 'model_has_roles');
        $morphKey = $this->permissionConfig('column_names.model_morph_key', 'model_id');
        $pivotRole = $this->permissionConfig('column_names.role_pivot_key', 'role_id');

        return $this->morphedByMany(
            User::class,
            'model',
            $table,
            $pivotRole,
            $morphKey,
        );
    }

    private function permissionConfig(string $key, string $default): string
    {
        $value = config("permission.{$key}");

        return is_string($value) ? $value : $default;
    }
}
