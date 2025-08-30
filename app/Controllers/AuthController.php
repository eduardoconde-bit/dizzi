<?php

namespace Dizzi\Controllers;

require "../../vendor/autoload.php";

use Dizzi\Models\User;
use Dizzi\Repositories\UserRepository;
use Dizzi\Services\RegisterService;
use Dizzi\Services\TokenService;

class AuthController
{

    public function register(?array $data): void
    {
        if (!isset($data["user_name"]) && !isset($data["password"])) {
            echo json_encode(["success" => false, "message" => "Data Invalid!"]);
        }

        $userRep = new UserRepository();

        if ($userRep->existsById($data["user_name"])) {
            echo json_encode(["success" => false, "message" => "User already exists"]);
            exit;
        }

        $user = new User($data["user_name"], $data['password']);
        echo json_encode(["success" => RegisterService::register($user)]);
    }

    public function login(?array $data): void
    {
        if (isset($_COOKIE['auth_token'])) {
            $jwt = $_COOKIE['auth_token'];
            echo json_encode(["success" => "The user has already authenticated"]);
            exit;
        }

        if (!isset($data["user_name"]) || !isset($data["password"])) {
            echo json_encode(["success" => false, "message" => "Username or Password Invalid"]);
            exit;
        }
        $token = TokenService::issueToken(new User($data["user_name"], $data["password"]));

        if (!$token) {
            echo json_encode(["success" => false, "message" => "Username or Password Invalid"]);
            exit;
        }
        echo json_encode(["success" => true, "message" => "Login Successful"]);
    }
}
