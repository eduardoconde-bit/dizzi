<?php

namespace Dizzi\Database;

require __DIR__."/../../vendor/autoload.php";

use PDO;
use Dizzi\Config\Config;

class Database
{
    private ?PDO    $connection = null;
    private Config  $env;

    private string  $host;
    private string  $dbname;
    private string  $username;
    private string  $password;
    private int     $port; 

    private array $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false, // true pode melhorar performance em alguns casos
    ];

    public function __construct()
    {
        $this->env = new Config();
        $this->host = $this->env->dbHost;
        $this->dbname = $this->env->dbName;
        $this->username = $this->env->dbUser;
        $this->password = $this->env->dbPass;
        $this->port = $this->env->dbPort;
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
