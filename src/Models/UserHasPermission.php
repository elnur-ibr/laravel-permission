<?php

namespace Spatie\Permission\Models;

use App\Models\Company\Company;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class UserHasPermission extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function companyPermission()
    {
        return $this->belongsTo(CompanyPermission::class,'company_permission_id');
    }

    public function userPermission()
    {
        return $this->belongsTo(Permission::class);
    }
}
