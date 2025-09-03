<?php

namespace Dizzi\Controllers;

require __DIR__ . '/../../vendor/autoload.php';

use Dizzi\Models\User;
use Dizzi\Repositories\UserRepository;
use Dizzi\Services\RegisterService;
use Dizzi\Services\TokenService;

class AuthController
{

    private UserRepository $userRep;
    private array $invalidCredentials = ["success" => false, "message" => "Username or Password Invalid"];
    private array $userAuthenticated = ["success" => true, "message" => "The user has already authenticated"];
    private array $userValid = ["success" => true, "message" => "Login Successful"];
    private array $userAlreadyExists = ["success" => false, "message" => "User already exists"];

    public function __construct()
    {
        $this->userRep = new UserRepository();
    }


    /**
     * Register a new user
     *
     * @param array|null $data The user data containing 'user_name' and 'password'
     * @return void
     */
    public function register(?array $data): void
    {
        try {
            if (!isset($data["user_name"]) || !isset($data["password"])) {
                echo json_encode($this->invalidCredentials);
                exit;
            }

            if ($this->userRep->existsById($data["user_name"])) {
                echo json_encode($this->userAlreadyExists);
                exit;
            }

            $user = new User($data["user_name"], $data['password']);
            echo json_encode(["success" => RegisterService::register($user)]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                "error" => "Internal Server Error"
            ]);
        }
    }

    /**
     * Login a user
     *
     * @param array|null $data The user data containing 'user_name' and 'password'
     * @return void
     */
    public function login(?array $data): void
    {
        try {
            //Poderia também revalidar o token a cada requisição que se mostre legítima como na condição abaixo
            if (isset($_COOKIE['auth_token'])) {
                if ((new UserRepository())->existsById($data["user_name"])) {
                    echo json_encode($this->userAuthenticated);
                    exit;
                }
            }

            if (!isset($data["user_name"]) || !isset($data["password"])) {
                http_response_code(400);
                echo json_encode($this->invalidCredentials);
                exit;
            }

            $token = TokenService::issueToken(new User($data["user_name"], $data["password"]));

            if (!$token) {
                echo json_encode($this->invalidCredentials);
                exit;
            }
            echo json_encode($this->userValid);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                "error" => "Internal Server Error"
            ]);
        }
    }
}
