<?php
session_start();
include 'conn.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please log in first.'); window.location.href='login.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch notifications for the logged-in LGU personnel
$query = "SELECT n.message, n.created_at, u.name AS citizen_name 
          FROM notifications n
          INNER JOIN appointments a ON n.appointment_id = a.id
          INNER JOIN users u ON a.user_id = u.id
          WHERE n.user_id = :user_id
          ORDER BY n.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container {
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">Your Notifications</h2>
        <?php if (count($notifications) > 0): ?>
            <ul class="list-group">
                <?php foreach ($notifications as $notification): ?>
                    <li class="list-group-item">
                        <p><strong>Citizen:</strong> <?= htmlspecialchars($notification['citizen_name']) ?></p>
                        <p><?= htmlspecialchars($notification['message']) ?></p>
                        <small class="text-muted">Created at: <?= htmlspecialchars($notification['created_at']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-center">No notifications found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
