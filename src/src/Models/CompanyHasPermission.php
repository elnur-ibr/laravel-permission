<?php

namespace Spatie\Permission\Models;

use App\Models\Company\Company;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class CompanyHasPermission extends Model
{
    public function permission()
    {
        return $this->belongsTo(Permission::claas);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
