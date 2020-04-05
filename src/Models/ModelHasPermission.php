<?php

namespace Spatie\Permission\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ModelHasPermission extends Model
{
    public function permission()
    {
        return $this->belongsTo(Permission::claas);
    }

    public function users()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo()
    }
}
