<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit("Unauthorized");
}
include '../../conn.php';

$name = $_POST['name'];
$description = $_POST['description'] ?? null;
$services = $_POST['services'] ?? null;
$created_by = $_SESSION['user_id'];

$stmt = $pdo->prepare("INSERT INTO departments (name, description, services, created_by) VALUES (?, ?, ?, ?)");
$stmt->execute([$name, $description, $services, $created_by]);

echo "Department added";
