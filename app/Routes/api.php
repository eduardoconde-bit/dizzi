<?php

namespace Dizzi\Routes;

use Dizzi\Controllers\PollController;

require_once('../Controllers/PollController.php');
/**
 * API Base for the Application
 * 
 */


// Permitir solicitações de qualquer origem
header("Access-Control-Allow-Origin: *");

// Permitir solicitações GET, POST, PUT e DELETE
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

// Permitir os cabeçalhos personalizados necessários
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

//header("Content-Type: application/json");

//Endpoints

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    echo "<p>Index<p>";
}

// Create Voting
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if ($data["action"] === "create_poll") {
        echo "Criando Votação!<br>";

        $pollController = new PollController();

        if (!$pollController->initPoll()) {
            echo "Erro ao criar Votação!";
        } else {
            echo "Sucesso ao criar Votação! em andamento!";
        }
    }
}

// Insert Code
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["code_poll_options"])) {
    $pollController = new PollController();

    echo "<p>Página de Votação sobre o código: " . $_GET["code_poll_options"] . "<p>";

    $pollController->getOptions($_GET["code_poll_options"]);
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if ($data["action"] === "vote") {
        $pollController = new PollController();
        $pollController->vote($data);
    }
}
