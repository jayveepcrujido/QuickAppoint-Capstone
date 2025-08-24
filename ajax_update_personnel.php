<?php
include 'conn.php';

$id     = $_POST['id'] ?? 0;
$first  = $_POST['first_name'] ?? '';
$middle = $_POST['middle_name'] ?? '';
$last   = $_POST['last_name'] ?? '';
$dept   = $_POST['department_id'] ?? '';
$email  = $_POST['email'] ?? '';

if (!$id || !$first || !$last || !$dept || !$email) {
    http_response_code(400);
    echo "Missing required fields.";
    exit;
}

try {
    $pdo->beginTransaction();

    // Update lgu_personnel
    $stmt = $pdo->prepare("UPDATE lgu_personnel SET first_name=?, middle_name=?, last_name=?, department_id=? WHERE id=?");
    $stmt->execute([$first, $middle, $last, $dept, $id]);

    // Update auth email
    $stmt = $pdo->prepare("
        UPDATE auth SET email=?
        WHERE id = (SELECT auth_id FROM lgu_personnel WHERE id=?)
    ");
    $stmt->execute([$email, $id]);

    $pdo->commit();
    echo "Personnel updated successfully.";
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Database Error: " . $e->getMessage();
}
