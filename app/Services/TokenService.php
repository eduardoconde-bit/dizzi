<?php

namespace Dizzi\Services;

require __DIR__ . "/../../vendor/autoload.php";

use Dizzi\Models\User;
use Dizzi\Repositories\UserRepository;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class TokenService
{
    const SECRET = "xxxxxxfffffxxxxxfffffxxxxxxfffff2000";

    public static function issueToken(User $user, int $ttl = 3600, bool $verifyCredentials = true): bool
    {
        if ($verifyCredentials) {
            $validUser = (new UserRepository())->verifyCredentials($user->getUserName(), $user->getPassword());
            
            if (!$validUser) {
                return false;
            }
        }

        // Dados do JWT
        $payload = [
            "iss" => "https://localhost",
            "aud" => "https://localhost",
            "iat" => time(),
            "exp" => time() + $ttl, // expira em 1 hora
            "user" => $user->getUserName()
        ];

        $jwt = JWT::encode($payload, self::SECRET, 'HS256');

        // Define o cookie HttpOnly (não acessível via JS)
        setcookie(
            "auth_token",
            $jwt,
            [
                "expires"  => time() + $ttl,
                "path"     => "/",
                "httponly" => true,
                "secure"   => true,
                "samesite" => "Lax"
            ]
        );

        $user = (new UserRepository())->findById($user->getUserName());
        $data = [
            "userName" => $user->getUserName(),
            "profileImage" => $user->getProfileImage() ?? null
        ];

        setcookie(
            "user_data",
            json_encode($data), // converte array em JSON
            [
                "expires" => time() + $ttl,
                "path"    => "/",
                "httponly" => false,   // importante: false, para JS poder ler
                "secure"  => true,
                "samesite" => "Lax"
            ]
        );

        return true; // aqui não devolvemos o token
    }

    public static function protect(): void
    {
        if (!isset($_COOKIE['auth_token'])) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "Token ausente. Acesso negado."
            ]);
            exit;
        }

        $token = $_COOKIE['auth_token'];

        try {
            $decoded = JWT::decode($token, new Key(self::SECRET, 'HS256'));

            // Opcional: adicionar informações do usuário no global ou request
            // Exemplo: $_SESSION['user'] = $decoded->user;
            // Ou alguma variável global para ser acessada no controller
            $GLOBALS['auth_user'] = $decoded->user;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "Token inválido ou expirado: " . $e->getMessage()
            ]);
            exit;
        }
    }

    public static function decodeToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::SECRET, 'HS256'));
            return (array)$decoded;
        } catch (\Exception $e) {
            return null;
        }
    }
}
