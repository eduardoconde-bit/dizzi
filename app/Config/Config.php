<?php

namespace Dizzi\Config;

require __DIR__."/../../vendor/autoload.php";

use Dotenv\Dotenv;

class Config {
    public readonly string $dbHost;
    public readonly string $dbName;
    public readonly string $dbUser;
    public readonly string $dbPass;
    public readonly int $dbPort;

    public readonly string $awsKey;
    public readonly string $awsSecret;
    public readonly string $awsRegion;
    public readonly string $awsBucket;

    public readonly string $defaultAvatarURL;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__."/../../");
        $dotenv->load();

        $this->dbHost = $_ENV['DB_HOST'];
        $this->dbName = $_ENV['DB_NAME'];
        $this->dbUser = $_ENV['DB_USER'];
        $this->dbPass = $_ENV['DB_PASS'];
        $this->dbPort = (int) $_ENV['DB_PORT'];

        $this->awsKey = $_ENV['AWS_KEY'];
        $this->awsSecret = $_ENV['AWS_SECRET'];
        $this->awsRegion = $_ENV['AWS_REGION'];
        $this->awsBucket = $_ENV['AWS_BUCKET'];
    
        $this->defaultAvatarURL = $_ENV['DEFAULT_AVATAR_URL'];
    }
}
