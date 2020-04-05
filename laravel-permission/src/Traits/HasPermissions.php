<?php

namespace Spatie\Permission\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Exceptions\PermissionRequiresCompany;
use Spatie\Permission\Guard;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\WildcardPermission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\WildcardPermissionInvalidArgument;
use Spatie\Permission\Models\Permission as PermissionModel;

trait HasPermissions
{
    private $permissionClass;

    public static function bootHasPermissions()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->permissions()->detach();
        });
    }

    /*public function getPermissionClass()
    {
        if (! isset($this->permissionClass)) {
            $this->permissionClass = app(PermissionRegistrar::class)->getPermissionClass();
        }

        return $this->permissionClass;
    }*/

    /**
     * A model may have multiple direct permissions.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            PermissionModel::class,
            'user_has_permissions'
        )->withPivot('company_id')->withTimestamps();
    }

    /**
     * Scope the model query to certain permissions only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePermission(Builder $query, $permissions): Builder
    {
        $permissions = $this->convertToPermissionModels($permissions);

        $rolesWithPermissions = array_unique(array_reduce($permissions, function ($result, $permission) {
            return array_merge($result, $permission->roles->all());
        }, []));

        return $query->where(function (Builder $query) use ($permissions, $rolesWithPermissions) {
            $query->whereHas('permissions', function (Builder $subQuery) use ($permissions) {
                $subQuery->whereIn(config('permission.table_names.permissions').'.id', \array_column($permissions, 'id'));
            });
            if (count($rolesWithPermissions) > 0) {
                $query->orWhereHas('roles', function (Builder $subQuery) use ($rolesWithPermissions) {
                    $subQuery->whereIn(config('permission.table_names.roles').'.id', \array_column($rolesWithPermissions, 'id'));
                });
            }
        });
    }

    /**
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return array
     */
    protected function convertToPermissionModels($permissions): array
    {
        if ($permissions instanceof Collection) {
            $permissions = $permissions->all();
        }

        $permissions = is_array($permissions) ? $permissions : [$permissions];

        return array_map(function ($permission) {
            if ($permission instanceof Permission) {
                return $permission;
            }

            return Permission::findByName($permission, $this->getDefaultGuardName());
        }, $permissions);
    }

    /**
     * Determine if the model may perform the given permission.
     *
     * @param string|int|\Spatie\Permission\Contracts\Permission $permission
     * @param string|null $guardName
     *
     * @return bool
     * @throws PermissionDoesNotExist
     */
    public function hasPermissionTo($permission, int $company_id = 0, $guardName = null): bool
    {
        /*if (config('permission.enable_wildcard_permission', false)) {
            return $this->hasWildcardPermission($permission, $guardName);
        }*/

        if (is_string($permission)) {
            $permission = PermissionModel::findByName(
                $permission,
                $guardName ?? $this->getDefaultGuardName()
            );
        }

        if (is_int($permission)) {
            $permission = PermissionModel::findById(
                $permission,
                $guardName ?? $this->getDefaultGuardName()
            );
        }

        if (! $permission instanceof Permission) {
            throw new PermissionDoesNotExist;
        }

        return $this->hasDirectPermission($permission, $company_id) || $this->hasPermissionViaRole($permission, $company_id);
    }

    /**
     * Validates a wildcard permission against all permissions of a user.
     *
     * @param string|int|\Spatie\Permission\Contracts\Permission $permission
     * @param string|null $guardName
     *
     * @return bool
     */
    /*protected function hasWildcardPermission($permission, $guardName = null): bool
    {
        $guardName = $guardName ?? $this->getDefaultGuardName();

        if (is_int($permission)) {
            $permission = $this->getPermissionClass()->findById($permission, $guardName);
        }

        if ($permission instanceof Permission) {
            $permission = $permission->name;
        }

        if (! is_string($permission)) {
            throw WildcardPermissionInvalidArgument::create();
        }

        foreach ($this->getAllPermissions() as $userPermission) {
            if ($guardName !== $userPermission->guard_name) {
                continue;
            }

            $userPermission = new WildcardPermission($userPermission->name);

            if ($userPermission->implies($permission)) {
                return true;
            }
        }

        return false;
    }*/

    /**
     * @deprecated since 2.35.0
     * @alias of hasPermissionTo()
     */
    public function hasUncachedPermissionTo($permission, $guardName = null): bool
    {
        return $this->hasPermissionTo($permission, $guardName);
    }

    /**
     * An alias to hasPermissionTo(), but avoids throwing an exception.
     *
     * @param string|int|\Spatie\Permission\Contracts\Permission $permission
     * @param string|null $guardName
     *
     * @return bool
     */
    public function checkPermissionTo($permission, $guardName = null): bool
    {
        try {
            return $this->hasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Determine if the model has any of the given permissions.
     *
     * @param array ...$permissions
     *
     * @return bool
     * @throws \Exception
     */
    public function hasAnyPermission(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if ($this->checkPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the model has all of the given permissions.
     *
     * @param array ...$permissions
     *
     * @return bool
     * @throws \Exception
     */
    public function hasAllPermissions(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if (! $this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the model has, via roles, the given permission.
     *
     * @param \Spatie\Permission\Contracts\Permission $permission
     *
     * @return bool
     */
    protected function hasPermissionViaRole(Permission $permission, int $company_id = 0): bool
    {
        return $this->hasRole($permission->roles, $company_id);
    }

    /**
     * Determine if the model has the given permission.
     *
     * @param string|int|\Spatie\Permission\Contracts\Permission $permission
     *
     * @return bool
     * @throws PermissionDoesNotExist
     */
    public function hasDirectPermission($permission, int $company_id): bool
    {
        if (is_string($permission)) {
            $permission = PermissionModel::findByName($permission, $this->getDefaultGuardName());
        }

        if (is_int($permission)) {
            $permission = PermissionModel::findById($permission, $this->getDefaultGuardName());
        }

        if (! $permission instanceof Permission) {
            throw new PermissionDoesNotExist;
        }

        return $this->permissions->contains(function($value) use ($permission, $company_id){
            if ($value->company_required)
                return ($company_id == $value->pivot->company_id)
                    && ($value->id == $permission->id);
                return $value->id == $permission->id;
        });
    }

    /**
     * Return all the permissions the model has via roles.
     */
    public function getPermissionsViaRoles(): Collection
    {
        return $this->loadMissing('roles', 'roles.permissions')
            ->roles->flatMap(function ($role) {
                return $role->permissions;
            })->sort()->values();
    }

    /**
     * Return all the permissions the model has, both directly and via roles.
     */
    public function getAllPermissions(): Collection
    {
        /** @var Collection $permissions */
        $permissions = $this->permissions;

        if ($this->roles) {
            $permissions = $permissions->merge($this->getPermissionsViaRoles());
        }

        return $permissions->sort()->values();
    }

    /**
     * Grant the given permission(s) to a role.
     *
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return $this
     */
    public function givePermissionTo($permissions, int $company_id = 0)
    {
        $permissions = collect($permissions)
            ->flatten()
            ->map(function ($permission) {
                if (empty($permission)) {
                    return false;
                }

                return $this->getStoredPermission($permission);
            })
            ->filter(function ($permission) {
                return $permission instanceof Permission;
            })
            ->each(function ($permission) {
                $this->ensureModelSharesGuard($permission);
            })

            //While assinging permissions to roles no need for company
            ->when(get_class($this) != Role::class, function($permissions) use ($company_id){
                //Checking if Permission required company
                $permissions->each(function ($permission) use ($company_id) {
                    if($permission->company_required && $company_id == 0)
                        throw PermissionRequiresCompany::create($permission->name);
                });

                return $permissions->mapWithKeys(function($permission, $key) use ($company_id){
                    if($permission->company_required)
                        return [$permission->id => ['company_id' => $company_id]];
                    return  [$permission->id];
                });
            })

            //When assinging persmission to roles
            ->when(get_class($this) == Role::class, function($permissions) use ($company_id){

                return $permissions->map->id;
            })
            ->all();

        $model = $this->getModel();

        if ($model->exists) {
            $this->permissions()->sync($permissions, false);
            $model->load('permissions');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($permissions, $model) {
                    static $modelLastFiredOn;
                    if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->permissions()->sync($permissions, false);
                    $object->load('permissions');
                    $modelLastFiredOn = $object;
                }
            );
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove all current permissions and set the given ones.
     *
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return $this
     */
    public function syncPermissions(...$permissions)
    {
        $this->permissions()->detach();

        return $this->givePermissionTo($permissions);
    }

    /**
     * Revoke the given permission.
     *
     * @param \Spatie\Permission\Contracts\Permission|\Spatie\Permission\Contracts\Permission[]|string|string[] $permission
     *
     * @return $this
     */
    public function revokePermissionTo($permission, int $company_id = 0)
    {
        $this->permissions()->detach($this->getStoredPermission($permission, $company_id));

        $this->forgetCachedPermissions();

        $this->load('permissions');

        return $this;
    }

    public function getPermissionNames(): Collection
    {
        return $this->permissions->pluck('name');
    }

    /**
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return \Spatie\Permission\Contracts\Permission|\Spatie\Permission\Contracts\Permission[]|\Illuminate\Support\Collection
     */
    protected function getStoredPermission($permissions)
    {
        if (is_numeric($permissions)) {
            return PermissionModel::findById($permissions, $this->getDefaultGuardName());
        }

        if (is_string($permissions)) {
            return PermissionModel::findByName($permissions, $this->getDefaultGuardName());
        }

        if (is_array($permissions)) {
            return PermissionModel::whereIn('name', $permissions)
                ->whereIn('guard_name', $this->getGuardNames())
                ->get();
        }

        return $permissions;
    }

    /**
     * @param \Spatie\Permission\Contracts\Permission|\Spatie\Permission\Contracts\Role $roleOrPermission
     *
     * @throws \Spatie\Permission\Exceptions\GuardDoesNotMatch
     */
    protected function ensureModelSharesGuard($roleOrPermission)
    {
        if (! $this->getGuardNames()->contains($roleOrPermission->guard_name)) {
            throw GuardDoesNotMatch::create($roleOrPermission->guard_name, $this->getGuardNames());
        }
    }

    protected function getGuardNames(): Collection
    {
        return Guard::getNames($this);
    }

    protected function getDefaultGuardName(): string
    {
        return Guard::getDefaultName($this);
    }

    /**
     * Forget the cached permissions.
     */
    public function forgetCachedPermissions()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Check if the model has All of the requested Direct permissions.
     * @param array ...$permissions
     * @return bool
     */
    public function hasAllDirectPermissions(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if (! $this->hasDirectPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the model has Any of the requested Direct permissions.
     * @param array ...$permissions
     * @return bool
     */
    public function hasAnyDirectPermission(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if ($this->hasDirectPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
