<?php

namespace Spatie\Permission\Models;

use App\Models\Company\Company;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\Contracts\CompanyRole as CompanyRoleContract;

class CompanyRole extends Model implements CompanyRoleContract
{
    protected $with = ['role'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions() : BelongsToMany
    {
        return $this->belongsToMany(CompanyPermission::class,'company_role_has_company_permissions');
    }

    public static function findByName(int $company_id, string $name, $guardName = null): CompanyRoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::where('company_id', $company_id)->whereHas('role', function(Builder $query) use ($name, $guardName){
            $query->where('name', $name)->where('guard_name', $guardName);
        })->first();

        if (! $role) {
            throw RoleDoesNotExist::named($name);
        }

        return $role;
    }

    public static function findById(int $company_id, int $id, $guardName = null): CompanyRoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::where('company_id', $company_id)->whereHas('role', function(Builder $query) use ($id, $guardName){
            $query->where('id', $id)->where('guard_name', $guardName);
        })->first();

        if (! $role) {
            throw RoleDoesNotExist::withId($id);
        }

        return $role;
    }

    /**
     * Find or create role by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Spatie\Permission\Contracts\Role
     */
    public static function findOrCreate(int $company_id, string $name, $guardName = null): CompanyRoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::where('name', $name)->where('guard_name', $guardName)->first();

        if (! $role) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $role;
    }

    public function hasPermissionTo(int $company_id, $permission): bool
    {
        /*if (config('permission.enable_wildcard_permission', false)) {
            return $this->hasWildcardPermission($permission, $this->getDefaultGuardName());
        }*/

        //$permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = Permission::findByName($permission, $this->getDefaultGuardName());
        }

        if (is_int($permission)) {
            $permission = Permission::findById($permission, $this->getDefaultGuardName());
        }

        if (! $this->getGuardNames()->contains($permission->guard_name)) {
            throw GuardDoesNotMatch::create($permission->guard_name, $this->getGuardNames());
        }

        return $this->permissions->contains('id', $permission->id);
    }
}
