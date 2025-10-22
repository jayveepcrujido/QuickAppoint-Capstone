<?php
session_start();
include 'conn.php';

if (!isset($_SESSION['ocr_data'])) {
    header("Location: register_camera.php");
    exit;
}

$ocrData = $_SESSION['ocr_data'];
$registrationStatus = $ocrData['registration_status'] ?? 'pending';
$faceMatchScore = $ocrData['face_match_score'] ?? 0;
$ocrConfidence = $ocrData['confidence'] ?? 0;
$isCameraCapture = $ocrData['is_camera_capture'] ?? false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name    = trim($_POST['first_name']);
    $middle_name   = trim($_POST['middle_name']);
    $last_name     = trim($_POST['last_name']);
    $address       = trim($_POST['address']);
    $birthday      = $_POST['birthday'];
    $age           = $_POST['age'];
    $sex           = $_POST['sex'];
    $civil_status  = $_POST['civil_status'];
    $email         = trim($_POST['email']);
    $password      = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $valid_id_type = $_POST['valid_id_type'];

    $valid_id_image = $ocrData['valid_id_image'];
    $selfie_image   = $ocrData['selfie_image'];

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $pdo->beginTransaction();

            // Check if email already exists
            $checkEmail = $pdo->prepare("SELECT id FROM auth WHERE email = :email");
            $checkEmail->execute(['email' => $email]);
            if ($checkEmail->fetch()) {
                throw new Exception("Email already registered. Please use a different email.");
            }

            // Insert into auth table
            $authStmt = $pdo->prepare("INSERT INTO auth (email, password, role) VALUES (:email, :password, 'Resident')");
            $authStmt->execute(['email' => $email, 'password' => $password]);
            $auth_id = $pdo->lastInsertId();

            // Determine final status based on verification
            $finalStatus = $registrationStatus; // 'approved' or 'pending'
            
            // Insert into residents table with status
            $residentStmt = $pdo->prepare("
                INSERT INTO residents (
                    auth_id, first_name, middle_name, last_name,
                    address, birthday, age, sex, civil_status,
                    valid_id_type, valid_id_image, selfie_image,
                    verification_status, face_match_score, ocr_confidence,
                    is_camera_capture, created_at
                ) VALUES (
                    :auth_id, :first_name, :middle_name, :last_name,
                    :address, :birthday, :age, :sex, :civil_status,
                    :valid_id_type, :valid_id_image, :selfie_image,
                    :verification_status, :face_match_score, :ocr_confidence,
                    :is_camera_capture, NOW()
                )
            ");
            
            $residentStmt->execute([
                'auth_id'              => $auth_id,
                'first_name'           => $first_name,
                'middle_name'          => $middle_name,
                'last_name'            => $last_name,
                'address'              => $address,
                'birthday'             => $birthday,
                'age'                  => $age,
                'sex'                  => $sex,
                'civil_status'         => $civil_status,
                'valid_id_type'        => $valid_id_type,
                'valid_id_image'       => $valid_id_image,
                'selfie_image'         => $selfie_image,
                'verification_status'  => $finalStatus,
                'face_match_score'     => $faceMatchScore,
                'ocr_confidence'       => $ocrConfidence,
                'is_camera_capture'    => $isCameraCapture ? 1 : 0
            ]);

            $pdo->commit();
            unset($_SESSION['ocr_data']);
            
            // Different messages based on status
            if ($finalStatus === 'approved') {
                $_SESSION['success_message'] = 'Registration successful! Your account has been automatically verified. You can now login.';
            } else {
                $_SESSION['success_message'] = 'Registration submitted! Your account is pending admin approval. You will receive an email once approved.';
            }
            
            header("Location: registration_success.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Review Information - LGU QuickAppoint</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #ffffffff 0%);
      min-height: 100vh;
      padding: 20px 0;
    }
    .main-container {
      max-width: 900px;
      margin: 0 auto;
    }
    .card {
      border-radius: 15px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    .verification-badge {
      display: inline-block;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: bold;
      margin-bottom: 15px;
    }
    .badge-approved {
      background: #28a745;
      color: white;
    }
    .badge-pending {
      background: #ffc107;
      color: #000;
    }
    .score-box {
      text-align: center;
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 15px;
    }
    .score-high {
      background: #d4edda;
      border: 2px solid #28a745;
    }
    .score-medium {
      background: #fff3cd;
      border: 2px solid #ffc107;
    }
    .score-low {
      background: #f8d7da;
      border: 2px solid #dc3545;
    }
    .preview-images {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
    }
    .preview-box {
      flex: 1;
      text-align: center;
    }
    .preview-box img {
      max-width: 100%;
      height: auto;
      border-radius: 10px;
      border: 3px solid #ddd;
    }
    .form-label {
      font-weight: 600;
      color: #333;
      margin-top: 10px;
    }
    .required {
      color: #dc3545;
    }
  </style>
</head>
<body>
  <div class="main-container">
    <div class="card">
      <div class="card-body">
        <h2 class="text-center mb-4">
          <i class="fas fa-user-check"></i> Review Your Information
        </h2>

        <?php if (isset($error)): ?>
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <!-- Verification Status -->
        <div class="text-center mb-4">
          <?php if ($registrationStatus === 'approved'): ?>
            <div class="verification-badge badge-approved">
              <i class="fas fa-check-circle"></i> Auto-Verified
            </div>
            <p class="text-success">Your identity has been automatically verified!</p>
          <?php else: ?>
            <div class="verification-badge badge-pending">
              <i class="fas fa-clock"></i> Pending Review
            </div>
            <p class="text-warning">Your registration will be reviewed by an administrator.</p>
          <?php endif; ?>
        </div>

        <!-- Verification Scores -->
        <?php if ($isCameraCapture): ?>
        <div class="row mb-4">
          <div class="col-md-6">
            <div class="score-box <?= $faceMatchScore >= 80 ? 'score-high' : ($faceMatchScore >= 60 ? 'score-medium' : 'score-low') ?>">
              <h5><i class="fas fa-smile"></i> Face Match</h5>
              <h2><?= $faceMatchScore ?>%</h2>
            </div>
          </div>
          <div class="col-md-6">
            <div class="score-box <?= $ocrConfidence >= 70 ? 'score-high' : ($ocrConfidence >= 50 ? 'score-medium' : 'score-low') ?>">
              <h5><i class="fas fa-id-card"></i> ID Confidence</h5>
              <h2><?= $ocrConfidence ?>%</h2>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Preview Images -->
        <div class="preview-images">
          <div class="preview-box">
            <h6><i class="fas fa-id-card"></i> Valid ID</h6>
            <img src="<?= htmlspecialchars($ocrData['valid_id_image']) ?>" alt="Valid ID">
          </div>
          <div class="preview-box">
            <h6><i class="fas fa-user-circle"></i> Selfie</h6>
            <img src="<?= htmlspecialchars($ocrData['selfie_image']) ?>" alt="Selfie">
          </div>
        </div>

        <!-- Registration Form -->
        <form method="POST">
          <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong>Please review and correct any information below.</strong>
            Fields marked with <span class="required">*</span> are required.
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">First Name <span class="required">*</span></label>
                <input type="text" name="first_name" class="form-control" 
                       value="<?= htmlspecialchars($ocrData['first_name'] ?? '') ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middle_name" class="form-control" 
                       value="<?= htmlspecialchars($ocrData['middle_name'] ?? '') ?>">
              </div>

              <div class="form-group">
                <label class="form-label">Last Name <span class="required">*</span></label>
                <input type="text" name="last_name" class="form-control" 
                       value="<?= htmlspecialchars($ocrData['last_name'] ?? '') ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Address <span class="required">*</span></label>
                <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($ocrData['address'] ?? '') ?></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">Birthday <span class="required">*</span></label>
                <input type="date" name="birthday" id="birthday" class="form-control" 
                       value="<?= htmlspecialchars($ocrData['birthday'] ?? '') ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Age</label>
                <input type="number" name="age" id="age" class="form-control" 
                       value="<?= htmlspecialchars($ocrData['age'] ?? '') ?>" readonly required>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Sex <span class="required">*</span></label>
                <select name="sex" class="form-control" required>
                  <option value="">-- Select --</option>
                  <option value="Male" <?= isset($ocrData['sex']) && $ocrData['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                  <option value="Female" <?= isset($ocrData['sex']) && $ocrData['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Civil Status <span class="required">*</span></label>
                <select name="civil_status" class="form-control" required>
                  <option value="">-- Select --</option>
                  <option value="Single">Single</option>
                  <option value="Married">Married</option>
                  <option value="Widowed">Widowed</option>
                  <option value="Separated">Separated</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" required 
                       placeholder="your.email@example.com">
              </div>

              <div class="form-group">
                <label class="form-label">Password <span class="required">*</span></label>
                <input type="password" name="password" id="password" class="form-control" 
                       minlength="6" required placeholder="Minimum 6 characters">
              </div>

              <div class="form-group">
                <label class="form-label">Confirm Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" class="form-control" 
                       required placeholder="Re-enter password">
                <small id="passwordError" class="text-danger" style="display:none;">Passwords do not match!</small>
              </div>

              <div class="form-group">
                <label class="form-label">Valid ID Type <span class="required">*</span></label>
                <select name="valid_id_type" class="form-control" required>
                  <option value="">-- Select ID Type --</option>
                  <option value="Driver's License" <?= $ocrData['id_type'] === 'drivers_license' ? 'selected' : '' ?>>Driver's License</option>
                  <option value="National ID" <?= $ocrData['id_type'] === 'national_id' ? 'selected' : '' ?>>National ID (PhilSys)</option>
                  <option value="Passport" <?= $ocrData['id_type'] === 'passport' ? 'selected' : '' ?>>Philippine Passport</option>
                  <option value="UMID" <?= $ocrData['id_type'] === 'umid' ? 'selected' : '' ?>>UMID / SSS</option>
                  <option value="Postal ID" <?= $ocrData['id_type'] === 'postal_id' ? 'selected' : '' ?>>Postal ID</option>
                  <option value="TIN ID" <?= $ocrData['id_type'] === 'tin_id' ? 'selected' : '' ?>>TIN ID</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" id="agreeTerms" required>
            <label class="form-check-label" for="agreeTerms">
              I agree to the <a href="#" target="_blank">Terms and Conditions</a> and <a href="#" target="_blank">Privacy Policy</a> <span class="required">*</span>
            </label>
          </div>

          <div class="row mt-4">
            <div class="col-md-6">
              <button type="button" class="btn btn-secondary btn-block" onclick="window.location.href='upload_id.php'">
                <i class="fas fa-arrow-left"></i> Back
              </button>
            </div>
            <div class="col-md-6">
              <button type="submit" id="submitBtn" class="btn btn-success btn-block">
                <i class="fas fa-check-circle"></i> Confirm & Register
              </button>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>

  <script>
    // Auto-compute age from birthday
    function computeAge() {
      const birthdayInput = document.getElementById('birthday');
      const ageInput = document.getElementById('age');
      
      if (!birthdayInput.value) return;
      
      const birthDate = new Date(birthdayInput.value);
      const today = new Date();
      let age = today.getFullYear() - birthDate.getFullYear();
      const monthDiff = today.getMonth() - birthDate.getMonth();
      const dayDiff = today.getDate() - birthDate.getDate();
      
      if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
        age--;
      }
      
      if (!isNaN(age) && age >= 0) {
        ageInput.value = age;
      }
    }

    // Password validation
    function validatePassword() {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const errorMsg = document.getElementById('passwordError');
      const submitBtn = document.getElementById('submitBtn');
      
      if (password !== confirmPassword) {
        errorMsg.style.display = 'block';
        submitBtn.disabled = true;
        return false;
      } else {
        errorMsg.style.display = 'none';
        submitBtn.disabled = false;
        return true;
      }
    }

    // Event listeners
    window.addEventListener('load', computeAge);
    document.getElementById('birthday').addEventListener('change', computeAge);
    document.getElementById('confirm_password').addEventListener('keyup', validatePassword);
    document.getElementById('password').addEventListener('keyup', validatePassword);

    // Form submission validation
    document.querySelector('form').addEventListener('submit', function(e) {
      if (!validatePassword()) {
        e.preventDefault();
        alert('Please make sure passwords match before submitting.');
      }
    });
  </script>
</body>
</html>