<?php
include 'conn.php';

$id = $_POST['id'];
$first = $_POST['first_name'];
$middle = $_POST['middle_name'];
$last = $_POST['last_name'];
$dept = $_POST['department_id'];
$email = $_POST['email'];

// Update users
$pdo->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, department_id=? WHERE id=?")
    ->execute([$first, $middle, $last, $dept, $id]);

// Update auth email
$pdo->prepare("UPDATE auth SET email=? WHERE user_id=?")->execute([$email, $id]);
