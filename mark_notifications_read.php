<?php
session_start();
require_once 'DBS.inc.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$userEmail = $data['user_email'] ?? '';

try {
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET notification_read = 1 
        WHERE user_email = ? 
        AND status = 'follow_up'
    ");
    $stmt->execute([$userEmail]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 