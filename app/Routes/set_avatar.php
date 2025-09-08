<?php

require './../../vendor/autoload.php';

use Dizzi\Services\ImageService;
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
$imageUrl = ImageService::upload($env, "avatar");
$action = $_POST['action'] ?? null;

if ($action === 'add_avatar' && $imageUrl) {
    $userRep = new UserRepository();
    if (!$userRep->updateAvatar((new User($GLOBALS['auth_user'])), $imageUrl)) {
        echo json_encode([
            'success' => false,
            'code'    => 'INTERNAL_SERVER_ERROR',
            'message' => "Failed to update avatar."
        ]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'code'    => 'AVATAR_UPDATED',  
        'url'     => $imageUrl
    ]);
} else {
    echo json_encode([
        'success' => false,
        'code'    => 'MALFORMED_REQUEST',
        'message' => "No image uploaded or action specified."
    ]);
}
