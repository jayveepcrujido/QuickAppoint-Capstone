<?php
session_start();
require_once 'conn.php';
header('Content-Type: application/json');

if (!isset($_SESSION['auth_id'])) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

$auth_id = (int)$_SESSION['auth_id'];
$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
    echo json_encode(['status'=>'error','message'=>'All fields are required.']);
    exit;
}
if ($new !== $confirm) {
    echo json_encode(['status'=>'error','message'=>'New passwords do not match.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password FROM auth WHERE id=?");
    $stmt->execute([$auth_id]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password'])) {
        echo json_encode(['status'=>'error','message'=>'Incorrect current password.']);
        exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE auth SET password=? WHERE id=?");
    $stmt->execute([$hash, $auth_id]);

    echo json_encode(['status'=>'success','message'=>'Password changed successfully.']);
} catch (Exception $e) {
    echo json_encode(['status'=>'error','message'=>'Server error: '.$e->getMessage()]);
}
