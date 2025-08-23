<?php
session_start();
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $id = $_POST['appointment_id'];
    $query = "UPDATE appointments SET status = 'Completed' WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $success = $stmt->execute(['id' => $id]);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Appointment marked as completed.' : 'Failed to update status.'
    ]);
}
