<?php
session_start();
include 'conn.php';
header('Content-Type: application/json');

$data = $_POST;
$stmt = $pdo->prepare("SELECT password FROM auth WHERE user_id=?");
$stmt->execute([$data['user_id']]);
$user = $stmt->fetch();

if (!$user || !password_verify($data['current_password'], $user['password'])) {
  echo json_encode(['status'=>'error','message'=>'Incorrect current password.']);
  exit;
}
if ($data['new_password'] !== $data['confirm_password']) {
  echo json_encode(['status'=>'error','message'=>'New passwords do not match.']);
  exit;
}

$newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE auth SET password=? WHERE user_id=?");
$stmt->execute([$newHash, $data['user_id']]);

echo json_encode(['status'=>'success','message'=>'Password changed successfully.']);
