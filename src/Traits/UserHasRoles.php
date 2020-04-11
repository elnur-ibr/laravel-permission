<?php

namespace Spatie\Permission\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Contracts\CompanyRole as CompanyRoleContract;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\CompanyRole;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait UserHasRoles
{
    use UserHasPermissions;

    public static function bootHasCompanyRoles()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->companyRoles()->detach();
        });
    }

    /**
     * A model may have multiple roles.
     */
    public function companyRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            CompanyRole::class,
            'user_has_roles'
        )->withTimestamps();
    }

    /**
     * Scope the model query to certain roles only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @param string $guard
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    /*public function scopeRole(Builder $query, $roles, $guard = null): Builder
    {
        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (! is_array($roles)) {
            $roles = [$roles];
        }

        $roles = array_map(function ($role) use ($guard) {
            if ($role instanceof Role) {
                return $role;
            }

            $method = is_numeric($role) ? 'findById' : 'findByName';
            $guard = $guard ?: $this->getDefaultGuardName();

            return CompanyRole::{$method}($role, $guard);
        }, $roles);

        return $query->whereHas('companyRoles', function (Builder $subQuery) use ($roles) {
            $subQuery->whereIn(config('permission.table_names.roles').'.id', \array_column($roles, 'id'));
        });
    }*/

    /**
     * Assign the given role to the model.
     *
     * @param array|string|\Spatie\Permission\Contracts\Role ...$roles
     *
     * @return $this
     */
    public function assignRole(int $company_id, $roles)
    {
        $roles = collect($roles)
            ->flatten()
            ->map(function ($role) use ($company_id) {
                if (empty($role)) {
                    return false;
                }

                return $this->getStoredRole($company_id, $role);
            })
            ->filter(function ($role) {
                return $role instanceof CompanyRole;
            })
            ->each(function ($role) {
                $this->ensureModelSharesGuard($role);
            })
            ->map->id
            ->all();

        $model = $this->getModel();

        if ($model->exists) {
            $this->companyRoles()->sync($roles, false);
            $model->load('companyRoles');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($roles, $model) {
                    static $modelLastFiredOn;
                    if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->companyRoles()->sync($roles, false);
                    $object->load('companyRoles');
                    $modelLastFiredOn = $object;
                });
        }

        $this->forgetCachedCompanyPermissions();

        return $this;
    }

    /**
     * Revoke the given role from the model.
     *
     * @param string|\Spatie\Permission\Contracts\Role $role
     */
    public function removeRole(int $company_id, $role)
    {
        $this->companyRoles()->detach($this->getStoredRole($company_id, $role));

        $this->load('companyRoles');

        $this->forgetCachedCompanyPermissions();

        return $this;
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param  array|\Spatie\Permission\Contracts\Role|string  ...$roles
     *
     * @return $this
     */
    public function syncRoles(...$roles)
    {
        $this->companyRoles()->detach();

        return $this->assignRole($roles);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     *
     * @param string|int|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @param string|null $guard
     * @return bool
     */
    public function hasRole(int $company_id, $roles, string $guard = null): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $guard
                ? $this->companyRoles->where('guard_name', $guard)->contains('name', $roles)
                : $this->companyRoles->contains('name', $roles);
        }

        if (is_int($roles)) {
            return $guard
                ? $this->companyRoles->where('guard_name', $guard)->contains('id', $roles)
                : $this->companyRoles->contains('id', $roles);
        }

        if ($roles instanceof Role) {
            return $this->companyRoles->contains('role.id', $roles->id);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($company_id, $role, $guard)) {
                    return true;
                }
            }

            return false;
        }

        return $roles->intersect(
            $guard ?
                $this->companyRoles->pluck('role')->where('guard_name', $guard) :
                $this->companyRoles->pluck('role')
        )->isNotEmpty();
    }

    /**
     * Determine if the model has any of the given role(s).
     *
     * Alias to hasRole() but without Guard controls
     *
     * @param string|int|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAnyRole(...$roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the model has all of the given role(s).
     *
     * @param  string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection  $roles
     * @param  string|null  $guard
     * @return bool
     */
    public function hasAllRoles($roles, string $guard = null): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $guard
                ? $this->companyRoles->where('guard_name', $guard)->contains('name', $roles)
                : $this->companyRoles->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $this->companyRoles->contains('id', $roles->id);
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return $roles->intersect(
            $guard
                ? $this->companyRoles->where('guard_name', $guard)->pluck('name')
                : $this->getRoleNames()) == $roles;
    }

    /**
     * Return all permissions directly coupled to the model.
     */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
    }

    public function getRoleNames(): Collection
    {
        return $this->companyRoles->pluck('role.name');
    }

    protected function getStoredRole(int $company_id, $role): CompanyRoleContract
    {
        if (is_numeric($role)) {
            return CompanyRole::findById($company_id, $role, $this->getDefaultGuardName());
        }

        if (is_string($role)) {
            return CompanyRole::findByName($company_id, $role, $this->getDefaultGuardName());
        }

        return $role;
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (! in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }
}
