<?php
include 'conn.php';

$showForm = false;
$message = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Validate token
    $stmt = $pdo->prepare("SELECT * FROM auth WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $showForm = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'];
            $confirm = $_POST['confirm_password'];

            if ($password !== $confirm) {
                $message = "<div class='alert error'>Passwords do not match.</div>";
            } elseif (strlen($password) < 6) {
                $message = "<div class='alert error'>Password must be at least 6 characters long.</div>";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE auth SET password = ?, reset_token = NULL WHERE reset_token = ?");
                $update->execute([$hashed, $token]);

                $message = "<div class='alert success'>Password successfully reset. <a href='login.php'>Login here</a>.</div>";
                $showForm = false;
            }
        }
    } else {
        $message = "<div class='alert error'>Invalid or expired token.</div>";
    }
} else {
    $message = "<div class='alert error'>No token provided.</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
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

        input[type="password"] {
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

        a {
            color: #2980b9;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Reset Your Password</h2>

    <?= $message ?>

    <?php if ($showForm): ?>
    <form method="POST">
        <label for="password">New Password:</label>
        <input type="password" name="password" required>

        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Reset Password</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
