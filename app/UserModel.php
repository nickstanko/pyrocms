<?php

namespace App;

use Laravel\Passport\HasApiTokens;

class UserModel extends \Anomaly\UsersModule\User\UserModel
{
    use HasApiTokens;
}
