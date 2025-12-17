<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['game_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'No session token.']);
    exit;
}

$token = $_SESSION['game_token'];
$status = 'pending';

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT status FROM telegram_auth WHERE token = ?");
    $stmt->execute([$token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $status = $result['status'];
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database query failed.']);
    exit;
}

echo json_encode(['status' => $status]);