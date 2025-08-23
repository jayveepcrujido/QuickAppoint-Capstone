<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit("Unauthorized");
}
include 'conn.php';

$id = $_POST['id'];
$name = $_POST['name'];
$description = $_POST['description'] ?? null;
$services = $_POST['services'] ?? null;

$stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ?, services = ? WHERE id = ?");
$stmt->execute([$name, $description, $services, $id]);

echo "Department updated";
