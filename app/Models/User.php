<?php

namespace Dizzi\Models;

class User
{
    //private string $userId;
    private string $userName;
    private ?string $password; // pode ser null
    private ?string $publicName;

    /**
     * @param string $userName
     * @param string|null $password senha opcional, será convertida para hash se fornecida
     * @throws InvalidArgumentException
     */
    public function __construct(string $userName, ?string $password = null)
    {

        if (empty($userName) || strlen($userName) > 50 || strlen($userName) < 3) {
            throw new \InvalidArgumentException("user_name inválido: deve estar entre 3 a 50 caracteres.");
        }

        if ($password !== null && $password !== '') {
            if (mb_strlen($password, 'UTF-8') < 8 || mb_strlen($password, 'UTF-8') > 255) {
                throw new \InvalidArgumentException(
                    "A senha deve conter entre 8 e 255 caracteres."
                );
            }
        }



        //$this->userId = $userId;
        $this->userName = $userName;
        $this->password = $password;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
