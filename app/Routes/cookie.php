<?php

require __DIR__ . './../../vendor/autoload.php';

use Dizzi\Models\User;
use Dizzi\Services\TokenService;

$data = $_POST ?? null;

TokenService::issueToken(new User('eduardo', '102030abc'));
header("Content-Type: application/json");
echo json_encode([
    "cookies" => $_COOKIE
]);