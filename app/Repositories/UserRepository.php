<?php

namespace Dizzi\Repositories;

require __DIR__ . '/../../vendor/autoload.php';

use Dizzi\Database\Database;
use Dizzi\Models\User;

class UserRepository
{
    /**
     * Cria um novo usuário
     */
    public function __construct()
    {
        return $this;   
    }
    
    public function create(User $user): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("
            INSERT INTO users (user_id, password_hash)
            VALUES (:user_id, :password_hash)
        ");

            return $stmt->execute([
                ':user_id'     => $user->getUserName(),
                ':password_hash' => $user->getPassword(), // pode ser null
            ]);
        } catch (\PDOException $e) {
            throw new \RuntimeException("Erro ao criar usuário: " . $e->getMessage());
        }
    }


    /**
     * Busca usuário por ID
     */
    public function findById(string $userId): ?User
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id LIMIT 1");
            $stmt->execute([':user_id' => $userId]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return new User(
                $row['user_id'],
                $row['user_name'],
                $row['profile_image'] ?? null
            );
        } catch (\PDOException $e) {
            throw new \RuntimeException("Erro ao buscar usuário: " . $e->getMessage());
        }
    }

    public function existsById(string $userId): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE user_id = :user_id LIMIT 1");
            $stmt->execute([':user_id' => $userId]);

            return (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw new \RuntimeException("Erro ao verificar usuário: " . $e->getMessage());
        }
    }


    /**
     * Verifica credenciais de login
     */
    public function verifyCredentials(string $userId, string $password): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id LIMIT 1");
            $stmt->execute([':user_id' => $userId]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            return password_verify($password, $row['password_hash']);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Atualiza nome do usuário
     */
    public function updateName(string $userId, string $newName): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("UPDATE users SET user_name = :user_name WHERE user_id = :user_id");

            return $stmt->execute([
                ':user_name' => $newName,
                ':user_id'   => $userId,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function updateAvatar(User $user, string $avatarUrl): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("UPDATE users SET profile_image = :avatar_url WHERE user_id = :user_id");

            return $stmt->execute([
                ':avatar_url' => $avatarUrl,
                ':user_id'    => $user->getUserName(),
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Remove usuário
     */
    public function delete(string $userId): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
            return $stmt->execute([':user_id' => $userId]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getProfile() {
        
    }
}
