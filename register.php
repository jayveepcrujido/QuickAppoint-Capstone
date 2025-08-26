<?php
session_start();
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name     = $_POST['first_name'];
    $middle_name    = $_POST['middle_name'];
    $last_name      = $_POST['last_name'];
    $email          = $_POST['email'];
    $password       = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role           = 'Resident';

    $address        = $_POST['address'];
    $birthday       = $_POST['birthday'];
    $age            = $_POST['age'];
    $sex            = $_POST['sex'];
    $civil_status   = $_POST['civil_status'];
    $valid_id_type  = $_POST['valid_id_type'];

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $valid_id_image_path = $uploadDir . uniqid() . '_' . basename($_FILES['valid_id_image']['name']);
    $selfie_image_path   = $uploadDir . uniqid() . '_' . basename($_FILES['selfie_image']['name']);

    move_uploaded_file($_FILES['valid_id_image']['tmp_name'], $valid_id_image_path);
    move_uploaded_file($_FILES['selfie_image']['tmp_name'], $selfie_image_path);

    try {
        $pdo->beginTransaction();

        $authStmt = $pdo->prepare("
            INSERT INTO auth (email, password, role)
            VALUES (:email, :password, :role)
        ");
        $authStmt->execute([
            'email'    => $email,
            'password' => $password,
            'role'     => $role
        ]);
        $auth_id = $pdo->lastInsertId();

        $residentStmt = $pdo->prepare("
            INSERT INTO residents (
                auth_id, first_name, middle_name, last_name,
                address, birthday, age, sex, civil_status,
                valid_id_type, valid_id_image, selfie_image
            ) VALUES (
                :auth_id, :first_name, :middle_name, :last_name,
                :address, :birthday, :age, :sex, :civil_status,
                :valid_id_type, :valid_id_image, :selfie_image
            )
        ");
        $residentStmt->execute([
            'auth_id'        => $auth_id,
            'first_name'     => $first_name,
            'middle_name'    => $middle_name,
            'last_name'      => $last_name,
            'address'        => $address,
            'birthday'       => $birthday,
            'age'            => $age,
            'sex'            => $sex,
            'civil_status'   => $civil_status,
            'valid_id_type'  => $valid_id_type,
            'valid_id_image' => $valid_id_image_path,
            'selfie_image'   => $selfie_image_path
        ]);

        $pdo->commit();

        echo "<script>alert('Registration successful!'); window.location.href='login.php';</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - LGU QuickAppoint</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    body {
      background: linear-gradient(rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0.85)),
        url('assets/images/LGU_Unisan.jpg') no-repeat center center/cover;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      padding: 20px;
    }

    .register-container {
      margin: auto;
      padding: 2rem;
      border-radius: 15px;
      background: #fff;
      max-width: 850px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .register-container h2 {
      text-align: center;
      font-weight: 700;
      color: #27548A;
      margin-bottom: 1.5rem;
    }

    .form-control {
      border-radius: 10px;
      padding: 8px 12px;
      font-size: 14px;
    }

    label {
      font-size: 14px;
      font-weight: 600;
      color: #333;
    }

    .btn-primary {
      background-color: #27548A;
      border: none;
      border-radius: 10px;
      margin-top: 1rem;
      padding: 10px;
      font-weight: bold;
      font-size: 15px;
      transition: 0.3s;
    }

    .btn-primary:hover {
      background-color: #1b3b61;
    }

    .login-link {
      text-align: center;
      display: block;
      margin-top: 1rem;
      font-size: 14px;
    }

    @media (max-width: 768px) {
      .register-container {
        padding: 1.5rem;
      }
      .form-control {
        font-size: 13px;
        padding: 7px 10px;
      }
      
    }
  </style>
</head>
<body>
  <div class="register-container">
    <h2>Register for LGU QuickAppoint</h2>
    <form method="POST" enctype="multipart/form-data">
      <div class="row">
        <!-- Left Column -->
        <div class="col-md-6">
          <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" name="first_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="middle_name">Middle Name</label>
            <input type="text" name="middle_name" class="form-control">
          </div>
          <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="address">Address</label>
            <input type="text" name="address" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="birthday">Birthday</label>
            <input type="date" name="birthday" id="birthday" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="age">Age</label>
            <input type="number" name="age" id="age" class="form-control" readonly required>
          </div>
        </div>

        <!-- Right Column -->
        <div class="col-md-6">
          <div class="form-group">
            <label for="sex">Sex</label>
            <select name="sex" class="form-control" required>
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
          <div class="form-group">
            <label for="civil_status">Civil Status</label>
            <select name="civil_status" class="form-control" required>
              <option value="">Select</option>
              <option value="Single">Single</option>
              <option value="Married">Married</option>
            </select>
          </div>
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="valid_id_type">Valid ID Type</label>
            <select name="valid_id_type" class="form-control" required>
              <option value="">Select ID Type</option>
              <option value="PhilSys ID">PhilSys ID</option>
              <option value="TIN ID">TIN ID</option>
              <option value="PhilHealth ID">PhilHealth ID</option>
              <option value="Driver's License">Driver's License</option>
            </select>
          </div>
          <div class="form-group">
            <label for="valid_id_image">Upload Valid ID</label>
            <input type="file" name="valid_id_image" class="form-control-file" accept="image/*" required>
          </div>
          <div class="form-group">
            <label for="selfie_image">Upload Selfie</label>
            <input type="file" name="selfie_image" class="form-control-file" accept="image/*" required>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Register</button>
      <a href="login.php" class="login-link">Already have an account? Login Here</a>
    </form>
  </div>

  <script>
    document.getElementById('birthday').addEventListener('change', function () {
        const birthDate = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        const dayDiff = today.getDate() - birthDate.getDate();

        if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
          age--;
        }

        if (!isNaN(age)) {
          document.getElementById('age').value = age;
        } else {
          document.getElementById('age').value = '';
        }
    });
  </script>
</body>
</html>
