<?php

namespace Dizzi\Config;

require __DIR__."/../../vendor/autoload.php";

use Dotenv\Dotenv;

class Config {
    public string $dbHost;
    public string $dbName;
    public string $dbUser;
    public string $dbPass;
    public int $dbPort;

    public string $awsKey;
    public string $awsSecret;
    public string $awsRegion;
    public string $awsBucket;

    public function __construct() {
        $dotenv = Dotenv::createImmutable("C:\Users\luise\Desktop\dizzi");
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
    }
}
