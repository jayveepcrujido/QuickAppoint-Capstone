<?php
session_start();

if (!isset($_SESSION['success_message'])) {
    header("Location: register_camera.php");
    exit;
}

$message = $_SESSION['success_message'];
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Registration Success - LGU QuickAppoint</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #ffffffff);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .success-container {
      max-width: 600px;
      width: 100%;
    }
    .success-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      padding: 40px;
      text-align: center;
      animation: slideUp 0.5s ease-out;
    }
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    .success-icon {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 30px;
      animation: scaleIn 0.5s ease-out 0.2s both;
    }
    @keyframes scaleIn {
      from {
        transform: scale(0);
      }
      to {
        transform: scale(1);
      }
    }
    .success-icon i {
      font-size: 50px;
      color: white;
    }
    .success-title {
      font-size: 28px;
      font-weight: bold;
      color: #333;
      margin-bottom: 15px;
    }
    .success-message {
      font-size: 16px;
      color: #666;
      margin-bottom: 30px;
      line-height: 1.6;
    }
    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      padding: 12px 40px;
      font-size: 16px;
      font-weight: 600;
      border-radius: 25px;
      transition: transform 0.2s;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
    }
  </style>
</head>
<body>
  <div class="success-container">
    <div class="success-card">
      <div class="success-icon">
        <i class="fas fa-check"></i>
      </div>
      <h1 class="success-title">Registration Successful!</h1>
      <p class="success-message"><?= htmlspecialchars($message) ?></p>
      <a href="login.php" class="btn btn-primary btn-lg">
        <i class="fas fa-sign-in-alt"></i> Proceed to Login
      </a>
      <div class="mt-4">
        <small class="text-muted">
          Need help? <a href="contact.php">Contact Support</a>
        </small>
      </div>
    </div>
  </div>
</body>
</html>