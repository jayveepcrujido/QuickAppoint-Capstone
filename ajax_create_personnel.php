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
    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $department_id = $_POST['department_id'] ?? '';
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';

    if (!$first_name || !$last_name || !$email || !$password || !$department_id) {
        http_response_code(400);
        echo "All required fields must be filled.";
        exit;
    }

    // Check duplicate email
    $check = $pdo->prepare("SELECT id FROM auth WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(400);
        echo "Email already exists.";
        exit;
    }

    $pdo->beginTransaction();

    // Insert into auth first
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO auth (email, password, role) VALUES (?, ?, 'LGU Personnel')");
    $stmt->execute([$email, $hashedPassword]);
    $authId = $pdo->lastInsertId();

    // Insert into lgu_personnel
    $stmt = $pdo->prepare("
        INSERT INTO lgu_personnel (auth_id, first_name, middle_name, last_name, department_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$authId, $first_name, $middle_name, $last_name, $department_id]);

    $pdo->commit();
    echo "LGU Personnel created successfully.";
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Database Error: " . $e->getMessage();
}
