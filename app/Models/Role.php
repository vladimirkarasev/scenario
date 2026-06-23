<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property string|null $title
 * @property string|null $description
 * @property bool $is_system
 */
final class Role extends SpatieRole
{
    /** @var list<string> */
    protected $fillable = ['name', 'guard_name', 'title', 'description', 'is_system'];

    /** @return MorphToMany<User, $this> */
    public function users(): MorphToMany
    {
        $table = $this->permissionConfig('table_names.model_has_roles', 'model_has_roles');
        $morphKey = $this->permissionConfig('column_names.model_morph_key', 'model_id');

        return $this->morphedByMany(
            User::class,
            'model',
            $table,
            app(PermissionRegistrar::class)->pivotRole,
            $morphKey,
        );
    }

    private function permissionConfig(string $key, string $default): string
    {
        $value = config("permission.{$key}");

        return is_string($value) ? $value : $default;
    }
}
