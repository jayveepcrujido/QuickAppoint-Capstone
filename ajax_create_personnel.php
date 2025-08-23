<?php
session_start();
include 'conn.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo "Method not allowed.";
        exit;
    }

    // Get inputs
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $department_id = $_POST['department_id'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insert into users
    $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, department_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$first_name, $middle_name, $last_name, $department_id]);
    $user_id = $pdo->lastInsertId();

    // Insert into auth
    $stmt = $pdo->prepare("INSERT INTO auth (user_id, email, password, role) VALUES (?, ?, ?, 'LGU Personnel')");
    $stmt->execute([$user_id, $email, $hashedPassword]);

    echo "LGU Personnel created successfully.";
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database Error: " . $e->getMessage();
}
