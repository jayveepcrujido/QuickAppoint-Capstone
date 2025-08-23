<?php
include 'conn.php';
$id = $_POST['id'] ?? 0;

// This will delete from both users and auth because of foreign key cascade
$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
