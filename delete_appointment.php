<?php
session_start();
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $id = $_POST['appointment_id'];
    $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = :id");
    $success = $stmt->execute(['id' => $id]);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Appointment deleted successfully.' : 'Failed to delete appointment.'
    ]);
}
