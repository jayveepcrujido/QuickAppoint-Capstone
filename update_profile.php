<?php
session_start();
include 'conn.php';

$auth_id = $_SESSION['auth_id'];
$role = $_SESSION['role'];

// ✅ Get user_id from auth
$stmt = $pdo->prepare("SELECT user_id FROM auth WHERE id = ?");
$stmt->execute([$auth_id]);
$row = $stmt->fetch();
if (!$row) {
    echo "<div class='alert alert-danger'>User not found.</div>";
    exit();
}
$user_id = $row['user_id'];

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role === 'Admin') {
        $stmt = $pdo->prepare("UPDATE admins 
            SET first_name=?, middle_name=?, last_name=? 
            WHERE id=?");
        $stmt->execute([$_POST['first_name'], $_POST['middle_name'], $_POST['last_name'], $user_id]);

    } elseif ($role === 'LGU Personnel') {
        $stmt = $pdo->prepare("UPDATE lgu_personnel 
            SET first_name=?, middle_name=?, last_name=?, department_id=? 
            WHERE id=?");
        $stmt->execute([
            $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'],
            $_POST['department_id'], $user_id
        ]);

    } elseif ($role === 'Resident') {
        $stmt = $pdo->prepare("UPDATE residents 
            SET first_name=?, middle_name=?, last_name=?, address=?, birthday=?, age=?, sex=?, civil_status=? 
            WHERE id=?");
        $stmt->execute([
            $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'],
            $_POST['address'], $_POST['birthday'], $_POST['age'], $_POST['sex'], $_POST['civil_status'],
            $user_id
        ]);
    }

    echo "<div class='alert alert-success'>Profile updated successfully.</div>";
    exit();
}

// ✅ Fetch current profile info for form
if ($role === 'Admin') {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id=?");
} elseif ($role === 'LGU Personnel') {
    $stmt = $pdo->prepare("SELECT * FROM lgu_personnel WHERE id=?");
} elseif ($role === 'Resident') {
    $stmt = $pdo->prepare("SELECT * FROM residents WHERE id=?");
}
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
<div class="container mt-4">
  <div class="card shadow p-4">
    <h3>Edit Profile (<?= htmlspecialchars($role) ?>)</h3>
    <form method="post">

      <!-- Common fields -->
      <div class="form-group">
        <label>First Name</label>
        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
      </div>
      <div class="form-group">
        <label>Middle Name</label>
        <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($user['middle_name']) ?>">
      </div>
      <div class="form-group">
        <label>Last Name</label>
        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
      </div>

      <?php if ($role === 'LGU Personnel'): ?>
        <div class="form-group">
          <label>Department</label>
          <select name="department_id" class="form-control" required>
            <?php
              $depts = $pdo->query("SELECT id, name FROM departments")->fetchAll();
              foreach ($depts as $dept):
            ?>
              <option value="<?= $dept['id'] ?>" <?= ($user['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($dept['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if ($role === 'Resident'): ?>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address']) ?>">
        </div>
        <div class="form-group">
          <label>Birthday</label>
          <input type="date" name="birthday" class="form-control" value="<?= htmlspecialchars($user['birthday']) ?>">
        </div>
        <div class="form-group">
          <label>Age</label>
          <input type="number" name="age" class="form-control" value="<?= htmlspecialchars($user['age']) ?>">
        </div>
        <div class="form-group">
          <label>Sex</label>
          <select name="sex" class="form-control">
            <option value="Male" <?= ($user['sex'] == 'Male') ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= ($user['sex'] == 'Female') ? 'selected' : '' ?>>Female</option>
          </select>
        </div>
        <div class="form-group">
          <label>Civil Status</label>
          <select name="civil_status" class="form-control">
            <option value="Single" <?= ($user['civil_status'] == 'Single') ? 'selected' : '' ?>>Single</option>
            <option value="Married" <?= ($user['civil_status'] == 'Married') ? 'selected' : '' ?>>Married</option>
          </select>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
  </div>
</div>
</body>
</html>
