<?php

namespace Dizzi\Routes;

use Dizzi\Controllers\AuthController;
use Dizzi\Controllers\PollController;

require_once('../Controllers/PollController.php');

// CORS
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header("Content-Type: application/json");

// OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER["REQUEST_METHOD"];

// Recupera dados do body apenas se POST
$data = ($method === "POST") ? json_decode(file_get_contents("php://input"), true) : null;

switch ($method) {

    case "POST":
        if (!isset($data["action"])) {
            http_response_code(400);
            echo json_encode(["error" => "Action not specified"]);
            exit;
        }

        switch ($data["action"]) {

            case "register":
                (new AuthController())->register($data);
                break;

            case "login":
                (new AuthController())->login($data);
                break;

            case "create_poll":
                echo json_encode(["success" => (new PollController())->initPoll()]);
                break;

            case "vote":
                (new PollController())->vote($data);
                break;

            case "profile":
                (new PollController())->vote($data);
                break;
            case "finish_poll":
                (new PollController())->finishPoll($data["poll_id"]);
                break;

            default:
                http_response_code(400);
                echo json_encode(["error" => "Invalid action"]);
        }
        break;

    case "GET":
        if (isset($_GET['code_poll'])) {
            (new PollController())->getPoll($_GET['code_poll']);
        } elseif (isset($_GET['action']) && $_GET['action'] === 'get_polls' && isset($_GET['user_id'])) {
            (new PollController())->userPolls($_GET['user_id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros inválidos']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}
