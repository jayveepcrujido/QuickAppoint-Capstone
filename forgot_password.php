<?php
session_start();
include 'conn.php';
require 'send_reset_email.php'; // PHPMailer function

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    // Find user in auth and users tables
    $query = "SELECT a.user_id, u.first_name, a.role FROM auth a
              JOIN users u ON a.user_id = u.id
              WHERE a.email = :email";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generate secure token
        $token = bin2hex(random_bytes(50));

        // Save token
        $update = $pdo->prepare("UPDATE auth SET reset_token = :token WHERE email = :email");
        $update->execute(['token' => $token, 'email' => $email]);

        // Create reset link
        $resetLink = "http://localhost/capstonev2/reset_password.php?token=$token";

        // Send email using PHPMailer
        $result = sendResetEmail($email, $user['first_name'], $resetLink);

        if ($result === true) {
            $message = "<div class='alert success'>A reset link has been sent to <strong>$email</strong>. Please check your inbox.</div>";
        } else {
            $message = "<div class='alert error'>Failed to send email: $result</div>";
        }
    } else {
        $message = "<div class='alert error'>No account found with that email.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f6f9fc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background: #fff;
            padding: 30px 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            max-width: 500px;
            width: 100%;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
        }

        label {
            display: block;
            margin: 12px 0 6px;
        }

        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            width: 100%;
            background-color: #2980b9;
            color: white;
            border: none;
            padding: 12px;
            font-size: 16px;
            border-radius: 6px;
            margin-top: 20px;
            cursor: pointer;
        }

        .alert {
            padding: 10px;
            margin-top: 15px;
            border-radius: 6px;
            text-align: center;
        }

        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }

        .error {
            background-color: #f2dede;
            color: #a94442;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Forgot Your Password?</h2>

    <?= $message ?>

    <form method="POST" style="margin-top: 20px;">
        <label for="email">Enter your email address:</label>
        <input type="email" name="email" required>
        <button type="submit">Send Reset Link</button>
    </form>
</div>
</body>
</html>
