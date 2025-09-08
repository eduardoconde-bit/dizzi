<?php
// sse.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);  // permite que o script rode indefinidamente
ignore_user_abort(true); // continua rodando mesmo que o cliente desconecte


// --- IGNORE ---
// This file was modified to remove the infinite loop for better compatibility with certain server environments.
// If you need real-time updates, consider using a different approach or reintroduce the loop with caution.
// --- IGNORE ---
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");

// Desliga buffering PHP
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

// Força envio inicial (evita buffering do navegador)
echo str_repeat(" ", 1024) . "\n\n";

// Conexão com DB
require __DIR__. '/../../vendor/autoload.php';

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
            -- códigos ativos
            (SELECT GROUP_CONCAT(code) FROM poll_codes WHERE poll_id = p.id AND is_expired = 0) AS codes,
            -- opções de votação
            (SELECT GROUP_CONCAT(option_name) FROM poll_options WHERE poll_id = p.id) AS options,
            -- usuários que votaram com profile_image
            (
                SELECT JSON_ARRAYAGG(JSON_OBJECT(
                    'user_id', u.user_id,
                    'profile_image', u.profile_image
                ))
                FROM ledger l
                JOIN users u ON l.user_id = u.user_id
                WHERE l.poll_id = p.id
                AND l.previous_hash != REPEAT('0',64)
            ) AS voted_users
        FROM polls p
        WHERE p.id = :poll_id
        LIMIT 1;

        ");


        // Bind do parâmetro
        $stmt->bindValue(':poll_id', $pollId, PDO::PARAM_INT);
        $stmt->execute();
        $pollData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Monta payload
        $data = [
            "poll_id"     => $pollData['poll_id'],
            "poll_title"  => $pollData['poll_title'],
            "total_votes" => (int)$pollData['total_votes'],
            "codes"       => $pollData['codes'] ? explode(',', $pollData['codes']) : [],
            "options"     => $pollData['options'] ? explode(',', $pollData['options']) : [],
            "voted_users" => $pollData['voted_users'] ? json_decode($pollData['voted_users'], true) : [],
            "timestamp"   => date("Y-m-d H:i:s")
        ];


        // Envia para o cliente
        echo "data: " . json_encode($data) . "\n\n";
        flush(); // apenas flush, sem ob_flush()

        sleep(2); // atualiza a cada 2 segundos
    }
} catch (Throwable $e) {
    echo "data: " . json_encode([
        "error"     => $e->getMessage(),
        "timestamp" => date("H:i:s")
    ]) . "\n\n";
    flush();
}
