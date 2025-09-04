<?php
// sse.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");

// Desliga buffering PHP
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

// Força envio inicial (evita buffering do navegador)
echo str_repeat(" ", 1024) . "\n\n";

// Conexão com DB
require './vendor/autoload.php';

use Dizzi\Database\Database;

$db = new Database();
$db = $db->getConnection();

// ID da eleição
$pollId = $_GET["poll_id"] ?? 0; 

try {
    while (true) {
        // Query única trazendo título, total de votos, códigos, opções e usuários
        $stmt = $db->prepare("
            SELECT
            p.id AS poll_id,
            p.title AS poll_title,
            p.number_votes AS total_votes,
            GROUP_CONCAT(DISTINCT pc.code) AS codes,
            GROUP_CONCAT(DISTINCT po.option_name) AS options,
            GROUP_CONCAT(DISTINCT u.user_id) AS voted_users
            FROM
                polls p
            LEFT JOIN
                poll_options po ON p.id = po.poll_id
            LEFT JOIN
                poll_codes pc ON p.id = pc.poll_id AND pc.is_expired = 0
            LEFT JOIN
                ledger l ON p.id = l.poll_id
            LEFT JOIN
                users u ON l.user_id = u.user_id
            WHERE
                p.id = :poll_id
            GROUP BY
                p.id;
        ");

        // Bind do parâmetro
        $stmt->bindValue(':poll_id', $pollId, PDO::PARAM_INT);
        $stmt->execute();
        $pollData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Monta payload
        $data = [
            "poll_id"      => $pollData['poll_id'],
            "poll_title"   => $pollData['poll_title'] ?? 'Eleição não encontrada',
            "total_votes"  => (int)($pollData['total_votes'] ?? 0),
            "codes"        => $pollData['codes'] ? explode(',', $pollData['codes']) : [],
            "options"      => $pollData['options'] ? explode(',', $pollData['options']) : [],
            "voted_users"  => $pollData['voted_users'] ? explode(',', $pollData['voted_users']) : [],
            "timestamp"    => date("H:i:s")
        ];

        // Envia para o cliente
        echo "data: " . json_encode($data) . "\n\n";
        flush(); // apenas flush, sem ob_flush()

        sleep(10); // atualiza a cada 10 segundos
    }
} catch (Throwable $e) {
    echo "data: " . json_encode([
        "error"     => $e->getMessage(),
        "timestamp" => date("H:i:s")
    ]) . "\n\n";
    flush();
}
