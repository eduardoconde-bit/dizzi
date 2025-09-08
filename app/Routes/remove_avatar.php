<?php

require './../../vendor/autoload.php';

use Dizzi\Config\Config;
use Dizzi\Repositories\UserRepository;
use Dizzi\Services\TokenService;
use Dizzi\Models\User;

// Error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


TokenService::protect();

$env = new Config();
$request = json_decode(file_get_contents('php://input'), true) ?? null;

if ($request['action'] === 'remove_avatar') {
    $userRep = new UserRepository();
    if (!$userRep->updateAvatar((new User($GLOBALS['auth_user'])), $env->defaultAvatarURL)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'code'    => 'INTERNAL_SERVER_ERROR',
            'message' => "Failed to remove avatar."
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'code'    => 'AVATAR_REMOVED',
        'message' => "Avatar removed successfully.",
        'url'     => $env->defaultAvatarURL
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'code'    => 'MALFORMED_REQUEST',
        'message' => "Action not specified."
    ]);
}

