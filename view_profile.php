<?php
session_start();
include 'conn.php';

if (!isset($_SESSION['auth_id'])) {
    echo "<div class='alert alert-danger'>Unauthorized access.</div>";
    exit();
}

$auth_id = $_SESSION['auth_id'];
$role = $_SESSION['role'];

if ($role === 'Admin') {
    $stmt = $pdo->prepare("
        SELECT a.email, ad.first_name, ad.middle_name, ad.last_name, ad.created_at
        FROM auth a
        JOIN admins ad ON a.id = ad.auth_id
        WHERE a.id = ?
    ");
} elseif ($role === 'LGU Personnel') {
    $stmt = $pdo->prepare("
        SELECT a.email, p.first_name, p.middle_name, p.last_name, p.department_id, p.created_at
        FROM auth a
        JOIN lgu_personnel p ON a.id = p.auth_id
        WHERE a.id = ?
    ");
} elseif ($role === 'Resident') {
    $stmt = $pdo->prepare("
        SELECT a.email, r.first_name, r.middle_name, r.last_name, r.address, r.birthday, r.age, 
               r.sex, r.civil_status, r.valid_id_type, r.valid_id_image, r.selfie_image, r.created_at
        FROM auth a
        JOIN residents r ON a.id = r.auth_id
        WHERE a.id = ?
    ");
} else {
    echo "<div class='alert alert-danger'>Invalid role.</div>";
    exit();
}

$stmt->execute([$auth_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<div class='alert alert-danger'>User not found.</div>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>
    .profile-card {
      max-width: 720px;
      margin: 40px auto;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      background: #fff;
    }
    .profile-icon { font-size: 60px; color: #0d6efd; }
    .profile-label { font-weight: 600; color: #555; }
    .profile-value { font-size: 16px; color: #222; }
    hr { border-top: 1px dashed #ccc; }
  </style>
</head>
<body>
  <div class="container">
    <div class="profile-card text-center">
      <i class='bx bx-user-circle profile-icon mb-3'></i>
      <h4 class="mb-4 text-primary">My Profile</h4>

      <div class="text-left">
        <?php foreach ($user as $field => $value): ?>
          <div class="row mb-2">
            <div class="col-md-4 profile-label"><?= ucfirst(str_replace("_"," ",$field)) ?>:</div>
            <div class="col-md-8 profile-value"><?= htmlspecialchars($value) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</body>
</html>
