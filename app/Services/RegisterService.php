<?php

namespace Dizzi\Services;

include_once (__DIR__ . "../../../vendor/autoload.php");

use Dizzi\Models\User;
use Dizzi\Repositories\UserRepository;
use Dizzi\Services\PassHashService;

class RegisterService
{
    /**
     * 
     */
    public static function register(User $user)
    {
        try {
            //$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, "/../security.env");
            //$dotenv->safeLoad();

            $passwordHash = PassHashService::passHash($user->getPassword());

            $user = new User($user->getUserName(), $passwordHash);

            $userRep = new UserRepository();
            return $userRep->create($user);
        } catch(\Exception $e) {
            return false;
        }
    }
}