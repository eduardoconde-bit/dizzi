<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . "/../../vendor/autoload.php";
use Dizzi\Database\Database;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $dbInstance = new Database();
    $pdo = $dbInstance->getConnection();

    $poll_id = isset($_GET['poll_id']) ? intval($_GET['poll_id']) : null;
    if (!$poll_id) throw new Exception('poll_id parameter is required');

    // Poll info
    $poll_stmt = $pdo->prepare("
        SELECT *, 
               (SELECT COUNT(*) FROM ledger WHERE poll_id = polls.id AND previous_hash != REPEAT('0',64)) AS total_votes
        FROM polls
        WHERE id = :poll_id
        LIMIT 1
    ");
    $poll_stmt->bindParam(':poll_id', $poll_id, PDO::PARAM_INT);
    $poll_stmt->execute();
    $poll = $poll_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$poll) throw new Exception('Poll not found');

    // Options
    $options_stmt = $pdo->prepare("SELECT id AS option_id, option_name FROM poll_options WHERE poll_id = :poll_id ORDER BY id");
    $options_stmt->bindParam(':poll_id', $poll_id, PDO::PARAM_INT);
    $options_stmt->execute();
    $options = $options_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Votes per option
    $votes_stmt = $pdo->prepare("
        SELECT po.id AS option_id, po.option_name,
               COUNT(l.id) AS vote_count
        FROM poll_options po
        LEFT JOIN ledger l ON po.id = l.option_id AND l.previous_hash != REPEAT('0',64)
        WHERE po.poll_id = :poll_id
        GROUP BY po.id, po.option_name
        ORDER BY po.id
    ");
    $votes_stmt->bindParam(':poll_id', $poll_id, PDO::PARAM_INT);
    $votes_stmt->execute();
    $votes_per_option = $votes_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Users who voted
    $users_stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.user_name AS username, u.profile_image AS profile_picture,
                        l.timestamp AS voted_at
        FROM ledger l
        JOIN users u ON l.user_id = u.user_id
        WHERE l.poll_id = :poll_id AND l.previous_hash != REPEAT('0',64)
        ORDER BY l.timestamp DESC
    ");
    $users_stmt->bindParam(':poll_id', $poll_id, PDO::PARAM_INT);
    $users_stmt->execute();
    $voted_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Percentages
    $total_votes = intval($poll['total_votes']);
    $percentages = [];
    foreach ($votes_per_option as $vote_data) {
        $percentages[$vote_data['option_id']] = $total_votes > 0 ? round(($vote_data['vote_count'] / $total_votes) * 100, 1) : 0;
    }

    // Active codes
    $codes_stmt = $pdo->prepare("SELECT code FROM poll_codes WHERE poll_id = :poll_id AND is_expired = 0");
    $codes_stmt->bindParam(':poll_id', $poll_id, PDO::PARAM_INT);
    $codes_stmt->execute();
    $codes = $codes_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Response
    echo json_encode([
        'success' => true,
        'poll_id' => $poll_id,
        'poll_title' => $poll['title'] ?? '',
        'poll_description' => $poll['description'] ?? '',
        'total_votes' => $total_votes,
        'options' => array_column($options, 'option_name'),
        'option_ids' => array_column($options, 'option_id'),
        'votes_per_option' => $votes_per_option,
        'percentages' => $percentages,
        'voted_users' => $voted_users,
        'codes' => $codes,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
