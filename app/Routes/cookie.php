<?php

require __DIR__ . './../../vendor/autoload.php';

use Dizzi\Models\User;
use Dizzi\Services\TokenService;

TokenService::issueToken(new User('eduardo'), 3600, false);
header("Content-Type: application/json");
echo json_encode([
    "cookies" => $_COOKIE
]);