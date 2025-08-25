<?php

namespace Dizzi\Database;

use PDO;

class Database
{
    private ?PDO $connection = null;

    // Configurações (idealmente viriam de .env ou config externo)
    private string $host = 'localhost';
    private string $dbname = 'votacao';
    private string $username = 'eduardo';
    private string $password = '102030';
    private int $port = 3306; // porta padrão do MariaDB

    private array $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false, // true pode melhorar performance em alguns casos
    ];

    public function __construct(
        string $host = '',
        string $dbname = '',
        string $username = '',
        string $password = '',
        int $port = 0
    ) {
        if (!empty($host))     $this->host = $host;
        if (!empty($dbname))   $this->dbname = $dbname;
        if (!empty($username)) $this->username = $username;
        if (!empty($password)) $this->password = $password;
        if ($port > 0)         $this->port = $port;
    }

    /**
     * Retorna a conexão PDO única (lazy loading)
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Cria a conexão com o MariaDB
     */
    private function connect(): void
    {
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";

        try {
            $this->connection = new PDO($dsn, $this->username, $this->password, $this->options);
        } catch (\PDOException $e) {
            throw new \RuntimeException("Falha ao conectar ao MariaDB: " . $e->getMessage());
        }
    }
}
