<?php
session_start();
include '../conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth_id']) || !isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$notificationId = intval($_POST['notification_id']);

try {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->execute([$notificationId]);
    
    echo json_encode(['success' => true, 'message' => 'Notification deleted']);
} catch (PDOException $e) {
    error_log("Error deleting notification: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>