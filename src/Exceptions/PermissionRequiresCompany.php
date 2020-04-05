<?php

namespace Spatie\Permission\Exceptions;

use InvalidArgumentException;

class PermissionRequiresCompany extends InvalidArgumentException
{
    public static function create(string $permissionName)
    {
        return new static("The named `{$permissionName} permission` requires company.");
    }
}
