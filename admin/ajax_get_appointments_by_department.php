<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';

$departmentId = $_GET['department_id'] ?? '';

if ($departmentId !== '') {
    $stmt = $pdo->prepare("
        SELECT a.id, a.status, a.scheduled_for, a.reason, a.requested_at,
               r.first_name, r.last_name,
               d.name AS department_name
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        JOIN departments d ON a.department_id = d.id
        WHERE a.department_id = ?
        ORDER BY r.last_name ASC, a.scheduled_for ASC
    ");
    $stmt->execute([$departmentId]);
} else {
    $stmt = $pdo->query("
        SELECT a.id, a.status, a.scheduled_for, a.reason, a.requested_at,
               r.first_name, r.last_name,
               d.name AS department_name
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        JOIN departments d ON a.department_id = d.id
        ORDER BY r.last_name ASC, a.scheduled_for ASC
        LIMIT 10
    ");
}

$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON for JS to render
header('Content-Type: application/json');
echo json_encode($appointments);
