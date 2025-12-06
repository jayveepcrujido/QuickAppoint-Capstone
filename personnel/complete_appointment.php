<?php
session_start();
include '../conn.php';

// Check if user is authenticated and is LGU Personnel
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $id = $_POST['appointment_id'];
    
    // Update status to Completed AND set updated_at to current timestamp
    $query = "UPDATE appointments SET status = 'Completed', updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $success = $stmt->execute(['id' => $id]);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Appointment marked as completed.' : 'Failed to update status.'
    ]);
}
?>