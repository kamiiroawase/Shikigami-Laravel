<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as BaseUser;

class User extends Model implements BaseUser
{
    use Authenticatable;
}
