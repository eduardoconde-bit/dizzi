<?php
// realtime_poll_fallback.php
// Fallback endpoint for realtime poll data when SSE connection fails or takes too long

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set JSON response headers
header("Content-Type: application/json");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
require __DIR__ . '/../../vendor/autoload.php';

use Dizzi\Database\Database;

try {
    $db = new Database();
    $connection = $db->getConnection();
    
    // Get poll ID from request
    $pollId = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $pollId = $_GET['poll_id'] ?? null;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $pollId = $input['poll_id'] ?? null;
    }
    
    if (!$pollId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Poll ID is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    // Validate poll ID
    if (!is_numeric($pollId) || $pollId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid poll ID format',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    // Query to get comprehensive poll data
    $stmt = $connection->prepare("
        SELECT
            p.id AS poll_id,
            p.title AS poll_title,
            p.description AS poll_description,
            p.number_votes AS total_votes,
            p.is_finished,
            p.start_time,
            p.end_time,
            -- Active codes
            (SELECT GROUP_CONCAT(code) FROM poll_codes WHERE poll_id = p.id AND is_expired = 0) AS codes,
            -- Poll options
            (SELECT GROUP_CONCAT(option_name) FROM poll_options WHERE poll_id = p.id ORDER BY id) AS options,
            -- Option IDs for vote distribution calculation
            (SELECT GROUP_CONCAT(id) FROM poll_options WHERE poll_id = p.id ORDER BY id) AS option_ids,
            -- Users who voted with profile images
            (
                SELECT JSON_ARRAYAGG(JSON_OBJECT(
                    'user_id', u.user_id,
                    'profile_image', u.profile_image,
                    'voted_at', l.created_at
                ))
                FROM ledger l
                JOIN users u ON l.user_id = u.user_id
                WHERE l.poll_id = p.id
                AND l.previous_hash != REPEAT('0', 64)
                ORDER BY l.created_at DESC
            ) AS voted_users,
            -- Vote distribution per option
            (
                SELECT JSON_OBJECTAGG(
                    option_id,
                    vote_count
                )
                FROM (
                    SELECT 
                        l.option_id,
                        COUNT(*) as vote_count
                    FROM ledger l
                    WHERE l.poll_id = p.id 
                    AND l.previous_hash != REPEAT('0', 64)
                    AND l.option_id IS NOT NULL
                    GROUP BY l.option_id
                ) AS vote_counts
            ) AS vote_distribution
        FROM polls p
        WHERE p.id = :poll_id
        LIMIT 1
    ");
    
    $stmt->bindValue(':poll_id', $pollId, PDO::PARAM_INT);
    $stmt->execute();
    $pollData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pollData) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Poll not found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    // Process and format the data
    $codes = $pollData['codes'] ? explode(',', $pollData['codes']) : [];
    $options = $pollData['options'] ? explode(',', $pollData['options']) : [];
    $optionIds = $pollData['option_ids'] ? explode(',', $pollData['option_ids']) : [];
    $votedUsers = $pollData['voted_users'] ? json_decode($pollData['voted_users'], true) : [];
    $voteDistribution = $pollData['vote_distribution'] ? json_decode($pollData['vote_distribution'], true) : [];
    
    // Calculate votes per option in order
    $votesPerOption = [];
    foreach ($optionIds as $index => $optionId) {
        $votesPerOption[] = intval($voteDistribution[$optionId] ?? 0);
    }
    
    // Calculate percentages
    $totalVotes = intval($pollData['total_votes']);
    $percentages = [];
    foreach ($votesPerOption as $votes) {
        $percentages[] = $totalVotes > 0 ? round(($votes / $totalVotes) * 100, 1) : 0.0;
    }
    
    // Prepare response data
    $responseData = [
        'success' => true,
        'poll_id' => intval($pollData['poll_id']),
        'poll_title' => $pollData['poll_title'],
        'poll_description' => $pollData['poll_description'],
        'total_votes' => $totalVotes,
        'is_finished' => boolval($pollData['is_finished']),
        'start_time' => $pollData['start_time'],
        'end_time' => $pollData['end_time'],
        'codes' => $codes,
        'options' => $options,
        'option_ids' => array_map('intval', $optionIds),
        'votes_per_option' => $votesPerOption,
        'percentages' => $percentages,
        'voted_users' => $votedUsers,
        'user_count' => count($votedUsers),
        'timestamp' => date('Y-m-d H:i:s'),
        'server_time' => time(),
        'is_fallback' => true
    ];
    
    // Return successful response
    http_response_code(200);
    echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unexpected error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>