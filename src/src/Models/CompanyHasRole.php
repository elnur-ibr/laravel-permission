<?php

namespace Spatie\Permission\Models;

use App\Models\Company\Company;
use App\User;
use Illuminate\Database\Eloquent\Model;

class CompanyHasRole extends Model
{
    public function role()
    {
        return $this->belongsTo(Role::claas);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
