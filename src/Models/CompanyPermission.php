<?php

namespace Spatie\Permission\Models;

use App\Models\Company\Company;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\CompanyPermissionRegistrar;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Contracts\CompanyPermission as CompanyPermissionContract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class CompanyPermission extends Model implements CompanyPermissionContract
{
    protected $with = ['permission'];

    protected $hidden = ['pivot'];

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function roles() :BelongsToMany
    {
        return $this->belongsToMany(
            CompanyRole::class,
            'company_role_has_company_permissions'
        )->withTimestamps();
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @throws \Spatie\Permission\Exceptions\PermissionDoesNotExist
     *
     * @return \Spatie\Permission\Contracts\Permission
     */
    public static function findByName(int $company_id, string $name, $guardName = null): CompanyPermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermissions($company_id, ['name' => $name, 'guard_name' => $guardName])->first();

        if (!$permission) {
            throw PermissionDoesNotExist::create($name, $guardName);
        }

        return $permission;
    }

    /**
     * Find a permission by its id (and optionally guardName).
     *
     * @param int $id
     * @param string|null $guardName
     *
     * @throws \Spatie\Permission\Exceptions\PermissionDoesNotExist
     *
     * @return \Spatie\Permission\Contracts\Permission
     */
    public static function findById(int $company_id, int $id, $guardName = null): CompanyPermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermissions($company_id, ['id' => $id, 'guard_name' => $guardName])->first();

        if (! $permission) {
            throw PermissionDoesNotExist::withId($id, $guardName);
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     */
    protected static function getPermissions($company_id, array $params = []): Collection
    {
        return app(CompanyPermissionRegistrar::class)
            ->setPermissionClass(static::class)
            ->getPermissions($company_id, $params);
    }

    /**
     * Find or create permission by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Spatie\Permission\Contracts\Permission
     */

    public static function findOrCreate(int $company_id, string $name, $guardName = null): CompanyPermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermissions($company_id, ['name' => $name, 'guard_name' => $guardName])->first();

        if (! $permission) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $permission;
    }
}
