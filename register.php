<?php
session_start();
include 'conn.php';
require_once __DIR__ . '/vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['validate_only'])) {
    // Only include validation when actually processing form submission
    require_once 'validate_id.php';
    
    // Make sure this is a full registration submission, not an AJAX validation
    $valid_id_type  = $_POST['valid_id_type'];
    $first_name     = $_POST['first_name'];
    $middle_name    = $_POST['middle_name'];
    $last_name      = $_POST['last_name'];
    
    // Construct full address
    $house_number   = $_POST['house_number'];
    $street         = $_POST['street'];
    $barangay       = $_POST['barangay'];
    $municipality   = $_POST['municipality'];
    $province       = $_POST['province'];
    
    $address_parts = array_filter([$house_number, $street, $barangay, $municipality, $province]);
    $address = implode(', ', $address_parts);
    
    $birthday       = $_POST['birthday'];
    $age            = $_POST['age'];
    $sex            = $_POST['sex'];
    $civil_status   = $_POST['civil_status'];
    $email          = $_POST['email'];
    $phone_number   = '+63' . $_POST['phone_number'];
    $password       = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role           = 'Resident';

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Create temporary file paths first
    $temp_id = uniqid();
    $temp_front_path = $uploadDir . 'temp_' . $temp_id . '_front_' . basename($_FILES['id_front']['name']);
    $temp_selfie_path = $uploadDir . 'temp_' . $temp_id . '_selfie_' . basename($_FILES['selfie_with_id']['name']);

    if (move_uploaded_file($_FILES['id_front']['tmp_name'], $temp_front_path) &&
        move_uploaded_file($_FILES['selfie_with_id']['tmp_name'], $temp_selfie_path)) {

        // ===== OCR VALIDATION =====
        $validator = new IDValidator();
        $validationResult = $validator->validateID($temp_front_path, $valid_id_type);
        
        if (!$validationResult['valid']) {
            // Clean up uploaded files
            if (file_exists($temp_front_path)) unlink($temp_front_path);
            if (file_exists($temp_selfie_path)) unlink($temp_selfie_path);
            
            echo "<script>
                alert('Invalid ID picture! The uploaded ID doesn\\'t match the selected ID type.\\n\\nMatch Score: " . $validationResult['score'] . "%\\n\\nPlease upload the correct ID picture for: " . addslashes($valid_id_type) . "');
                window.history.back();
            </script>";
            exit();
        }
        // ===== END OCR VALIDATION =====

        try {
            $pdo->beginTransaction();

            // Insert auth record
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

            // Insert resident record with temporary paths
            $residentStmt = $pdo->prepare("
                INSERT INTO residents (
                    auth_id, first_name, middle_name, last_name,
                    address, birthday, age, sex, civil_status,
                    valid_id_type, id_front_image, selfie_with_id_image, phone_number
                ) VALUES (
                    :auth_id, :first_name, :middle_name, :last_name,
                    :address, :birthday, :age, :sex, :civil_status,
                    :valid_id_type, :id_front_image, :selfie_with_id_image, :phone_number
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
                'id_front_image'       => $temp_front_path,
                'selfie_with_id_image' => $temp_selfie_path,
                'phone_number'         => $phone_number
            ]);
            
            // Get the resident ID
            $resident_id = $pdo->lastInsertId();

            // Get file extensions
            $front_ext = pathinfo($_FILES['id_front']['name'], PATHINFO_EXTENSION);
            $selfie_ext = pathinfo($_FILES['selfie_with_id']['name'], PATHINFO_EXTENSION);

            // Create new file paths with resident ID
            $new_front_path = $uploadDir . 'resident_' . $resident_id . '_front.' . $front_ext;
            $new_selfie_path = $uploadDir . 'resident_' . $resident_id . '_selfie.' . $selfie_ext;

            // Rename files
            rename($temp_front_path, $new_front_path);
            rename($temp_selfie_path, $new_selfie_path);

            // Update database with new file paths
            $updateStmt = $pdo->prepare("
                UPDATE residents 
                SET id_front_image = :front, 
                    selfie_with_id_image = :selfie
                WHERE id = :resident_id
            ");
            $updateStmt->execute([
                'front' => $new_front_path,
                'selfie' => $new_selfie_path,
                'resident_id' => $resident_id
            ]);

            $pdo->commit();

            echo "<script>
                alert('Registration successful! ID validation score: " . $validationResult['score'] . "%'); 
                window.location.href='login.php';
            </script>";
        } catch (Exception $e) {
            $pdo->rollBack();
            
            // Clean up temporary files if they exist
            if (file_exists($temp_front_path)) unlink($temp_front_path);
            if (file_exists($temp_selfie_path)) unlink($temp_selfie_path);
            
            echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
        }
    } else {
        echo "<script>alert('Error uploading files. Please try again.');</script>";
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    body {
      background: linear-gradient(rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0.85)),
        url('assets/images/LGU_Unisan.jpg') no-repeat center center/cover;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      padding: 20px 15px;
      min-height: 100vh;
    }

    .register-container {
      margin: auto;
      padding: 2rem;
      border-radius: 12px;
      background: #fff;
      max-width: 900px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }

    .register-container h2 {
      text-align: center;
      font-weight: 700;
      color: #27548A;
      margin-bottom: 1.5rem;
      font-size: 24px;
    }

    .form-section {
      margin-bottom: 1.5rem;
      padding-bottom: 1.5rem;
    }

    .form-section:last-of-type {
      padding-bottom: 0;
    }

    .section-title {
      font-size: 16px;
      font-weight: 700;
      color: #27548A;
      margin-bottom: 1rem;
    }

    .form-row {
      margin-left: -8px;
      margin-right: -8px;
    }

    .form-row > .col,
    .form-row > [class*="col-"] {
      padding-left: 8px;
      padding-right: 8px;
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-control {
      border-radius: 8px;
      padding: 10px 12px;
      font-size: 14px;
      border: 1px solid #ced4da;
    }

    .form-control:focus {
      border-color: #27548A;
      box-shadow: 0 0 0 0.2rem rgba(39, 84, 138, 0.25);
    }

    select.form-control {
      height: auto;
      min-height: 42px;
    }

    label {
      font-size: 13px;
      font-weight: 600;
      color: #333;
      margin-bottom: 0.4rem;
    }

    .required::after {
      content: " *";
      color: red;
    }

    .btn-primary {
      background-color: #27548A;
      border: none;
      border-radius: 8px;
      margin-top: 0.5rem;
      padding: 12px;
      font-weight: bold;
      font-size: 15px;
      transition: 0.3s;
      width: 100%;
    }

    .btn-primary:hover {
      background-color: #1b3b61;
    }

    .btn-upload-id {
      background-color: #27548A;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 15px 30px;
      font-weight: bold;
      font-size: 16px;
      transition: 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin: 20px auto;
      max-width: 300px;
    }

    .btn-upload-id:hover {
      background-color: #1b3b61;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(39, 84, 138, 0.3);
    }

    .btn-upload-id i {
      font-size: 20px;
    }

    .id-status {
      text-align: center;
      padding: 15px;
      border-radius: 8px;
      margin: 15px 0;
      font-weight: 600;
    }

    .id-status.pending {
      background-color: #fff3cd;
      color: #856404;
      border: 2px solid #ffc107;
    }

    .id-status.verified {
      background-color: #d4edda;
      color: #155724;
      border: 2px solid #28a745;
    }

    .login-link {
      text-align: center;
      display: block;
      margin-top: 1rem;
      font-size: 14px;
      color: #27548A;
    }

    .input-group-text {
      background-color: #27548A;
      color: white;
      border-radius: 8px 0 0 8px;
      border: none;
      font-weight: 600;
      font-size: 14px;
    }

    .input-group .form-control {
      border-radius: 0 8px 8px 0;
    }

    .password-strength {
      font-size: 12px;
      margin-top: 5px;
    }

    .strength-weak { color: #dc3545; }
    .strength-medium { color: #ffc107; }
    .strength-strong { color: #28a745; }

    .password-requirements {
      font-size: 11px;
      color: #6c757d;
      margin-top: 5px;
      padding-left: 15px;
    }

    .password-requirements li {
      list-style: none;
      position: relative;
      margin-bottom: 3px;
    }

    .password-requirements li::before {
      content: "○";
      position: absolute;
      left: -15px;
    }

    .password-requirements li.valid::before {
      content: "✓";
      color: #28a745;
    }

    .custom-control-label {
      font-size: 12px;
      line-height: 1.5;
      color: #495057;
      font-weight: normal;
      cursor: pointer;
      padding-left: 5px;
    }

    .custom-control-label strong {
      color: #27548A;
      font-weight: 600;
    }

    .custom-control-input:checked ~ .custom-control-label::before {
      background-color: #27548A;
      border-color: #27548A;
    }

    .custom-checkbox {
      margin-bottom: 0.8rem;
    }

    small.form-text {
      font-size: 11px;
    }

    .btn-outline-secondary {
      border-color: #ced4da;
      color: #495057;
      border-radius: 0 8px 8px 0;
    }

    .btn-outline-secondary:hover {
      background-color: #e9ecef;
      border-color: #ced4da;
      color: #495057;
    }

    /* Modal Styles */
    .modal-content {
      border-radius: 12px;
      border: none;
    }

    .modal-header {
      background-color: #27548A;
      color: white;
      border-radius: 12px 12px 0 0;
      padding: 20px;
    }

    .modal-header .close {
      color: white;
      opacity: 1;
    }

    .modal-body {
      padding: 30px;
    }

    .modal-title {
      font-weight: 700;
      font-size: 20px;
    }

    .id-type-selector {
      margin-bottom: 25px;
    }

    .upload-section {
      display: none;
    }

    .upload-section.active {
      display: block;
    }

    .upload-box {
      border: 3px dashed #27548A;
      border-radius: 12px;
      padding: 40px 20px;
      text-align: center;
      background-color: #f8f9fa;
      cursor: pointer;
      transition: 0.3s;
      margin-bottom: 20px;
    }

    .upload-box:hover {
      background-color: #e9ecef;
      border-color: #1b3b61;
    }

    .upload-box i {
      font-size: 50px;
      color: #27548A;
      margin-bottom: 15px;
    }

    .upload-box.has-file {
      border-color: #28a745;
      background-color: #d4edda;
    }

    .upload-box.has-file i {
      color: #28a745;
    }

    .preview-container {
      margin-top: 20px;
      text-align: center;
    }

    .preview-image {
      max-width: 100%;
      max-height: 300px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .validation-result {
      margin-top: 20px;
      padding: 15px;
      border-radius: 8px;
      font-weight: 600;
      text-align: center;
    }

    .validation-result.success {
      background-color: #d4edda;
      color: #155724;
      border: 2px solid #28a745;
    }

    .validation-result.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 2px solid #dc3545;
    }

    .validation-result.loading {
      background-color: #d1ecf1;
      color: #0c5460;
      border: 2px solid #17a2b8;
    }

    .btn-validate {
      background-color: #28a745;
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-weight: 600;
      transition: 0.3s;
      margin-top: 15px;
    }

    .btn-validate:hover {
      background-color: #218838;
    }

    .btn-validate:disabled {
      background-color: #6c757d;
      cursor: not-allowed;
    }

    .spinner-border-sm {
      width: 1rem;
      height: 1rem;
      border-width: 0.15em;
    }

    @media (max-width: 768px) {
      .register-container {
        padding: 1.5rem;
      }

      .modal-body {
        padding: 20px;
      }

      .upload-box {
        padding: 30px 15px;
      }

      .upload-box i {
        font-size: 40px;
      }
    }

    @media (max-width: 576px) {
      body {
        padding: 10px;
      }

      .register-container {
        padding: 1rem;
      }

      .register-container h2 {
        font-size: 20px;
        margin-bottom: 1rem;
      }

      .section-title {
        font-size: 15px;
      }

      .form-control {
        font-size: 13px;
        padding: 8px 10px;
      }

      label {
        font-size: 12px;
      }

      .btn-primary {
        padding: 10px;
        font-size: 14px;
      }

      .custom-control-label {
        font-size: 11px;
      }

      .btn-upload-id {
        padding: 12px 20px;
        font-size: 14px;
      }
    }
  </style>
</head>
<body>
  <div class="register-container">
    <h2>Register for LGU QuickAppoint</h2>
    
    <!-- ID Upload Status -->
    <div id="idStatus" class="id-status pending">
      <i class="fas fa-id-card"></i> Valid ID Not Yet Uploaded
    </div>

    <!-- Upload ID Button -->
    <button type="button" class="btn-upload-id" id="openModalBtn">
      <i class="fas fa-cloud-upload-alt"></i>
      Upload Valid ID
    </button>

    <form method="POST" enctype="multipart/form-data" id="registrationForm">
      
      <!-- Hidden inputs for ID data -->
      <input type="hidden" name="valid_id_type" id="hidden_valid_id_type" required>
      <input type="file" name="id_front" id="hidden_id_front" style="display: none;" required>
      <input type="file" name="selfie_with_id" id="hidden_selfie_with_id" style="display: none;" required>

      <!-- SECTION: PERSONAL INFORMATION -->
      <div class="form-section">
        <div class="section-title">Personal Information</div>
        
        <div class="form-row">
          <div class="col-md-4 form-group">
            <label for="first_name" class="required">First Name</label>
            <input type="text" name="first_name" id="first_name" class="form-control" required>
          </div>

          <div class="col-md-4 form-group">
            <label for="middle_name">Middle Name</label>
            <input type="text" name="middle_name" id="middle_name" class="form-control">
          </div>

          <div class="col-md-4 form-group">
            <label for="last_name" class="required">Last Name</label>
            <input type="text" name="last_name" id="last_name" class="form-control" required>
          </div>
        </div>

        <div class="form-row">
          <div class="col-md-4 form-group">
            <label for="birthday" class="required">Birthday</label>
            <input type="date" name="birthday" id="birthday" class="form-control" required>
          </div>

          <div class="col-md-2 form-group">
            <label for="age">Age</label>
            <input type="number" name="age" id="age" class="form-control" readonly required>
          </div>

          <div class="col-md-3 form-group">
            <label for="sex" class="required">Sex</label>
            <select name="sex" id="sex" class="form-control" required>
              <option value="">-- Select --</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>

          <div class="col-md-3 form-group">
            <label for="civil_status" class="required">Civil Status</label>
            <select name="civil_status" id="civil_status" class="form-control" required>
              <option value="">-- Select --</option>
              <option value="Single">Single</option>
              <option value="Married">Married</option>
              <option value="Separated">Separated</option>
              <option value="Widowed">Widowed</option>
              <option value="Divorced">Divorced</option>
              <option value="Annulled">Annulled</option>
              <option value="Widower">Widower</option>
              <option value="Single Parent">Single Parent</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="col-md-6 form-group">
            <label for="province" class="required">Province</label>
            <select name="province" id="province" class="form-control" required>
              <option value="">-- Loading Provinces... --</option>
            </select>
          </div>

          <div class="col-md-6 form-group">
            <label for="municipality" class="required">Municipality/City</label>
            <select name="municipality" id="municipality" class="form-control" required disabled>
              <option value="">-- Select Municipality --</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="col-md-6 form-group">
            <label for="barangay" class="required">Barangay</label>
            <select name="barangay" id="barangay" class="form-control" required disabled>
              <option value="">-- Select Barangay --</option>
            </select>
          </div>

          <div class="col-md-6 form-group">
            <label for="street" class="required">Street/Purok</label>
            <input type="text" name="street" id="street" class="form-control" placeholder="Enter street/purok name" required>
          </div>
        </div>

        <div class="form-group">
          <label for="house_number">House Number / Building Name (Optional)</label>
          <input type="text" name="house_number" id="house_number" class="form-control" placeholder="e.g., Block 5 Lot 10">
        </div>
      </div>

      <!-- SECTION: ACCOUNT INFORMATION -->
      <div class="form-section">
        <div class="section-title">Account Information</div>
        
        <div class="form-row">
          <div class="col-md-6 form-group">
            <label for="email" class="required">Email Address</label>
            <input type="email" name="email" id="email" class="form-control" placeholder="example@email.com" required>
          </div>

          <div class="col-md-6 form-group">
            <label for="phone_number" class="required">Phone Number</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">+63</span>
              </div>
              <input type="tel" name="phone_number" id="phone_number" class="form-control" placeholder="9123456789" pattern="[0-9]{10}" maxlength="10" required>
            </div>
            <small class="form-text text-muted">Enter 10-digit mobile number</small>
          </div>
        </div>

        <div class="form-group">
          <label for="password" class="required">Password</label>
          <div class="input-group">
            <input type="password" name="password" id="password" class="form-control" required>
            <div class="input-group-append">
              <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                <i class="fas fa-eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>
          <div id="passwordStrength" class="password-strength"></div>
          <ul class="password-requirements">
            <li id="length">At least 8 characters</li>
            <li id="uppercase">At least one uppercase letter</li>
            <li id="lowercase">At least one lowercase letter</li>
            <li id="number">At least one number</li>
            <li id="special">At least one special character (!@#$%^&*)</li>
          </ul>
        </div>
      </div>

      <!-- SECTION: DATA PRIVACY AND DECLARATION -->
      <div class="form-section">
        <div class="section-title">Data Privacy Consent & Declaration</div>
        
        <div class="form-group">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="privacy_consent" name="privacy_consent" required>
            <label class="custom-control-label" for="privacy_consent">
              <strong>Data Privacy Consent:</strong> I consent to the collection and processing of my personal information in accordance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>. I understand that my data will be used solely for legitimate and official LGU transactions.
            </label>
          </div>
        </div>

        <div class="form-group">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="declaration_truth" name="declaration_truth" required>
            <label class="custom-control-label" for="declaration_truth">
              <strong>Declaration of Truth:</strong> I hereby certify that all information provided is true, correct, and complete to the best of my knowledge. I acknowledge that providing false information may result in the denial of services and penalties under applicable laws.
            </label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Complete Registration</button>
      <a href="login.php" class="login-link">Already have an account? Login Here</a>
    </form>
  </div>

  <!-- ID Upload Modal -->
  <div class="modal fade" id="idUploadModal" tabindex="-1" role="dialog" aria-labelledby="idUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="idUploadModalLabel">
            <i class="fas fa-id-card"></i> Upload Valid ID
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Step 1: Select ID Type -->
          <div class="id-type-selector">
            <label class="required">Step 1: Select Valid ID Type</label>
            <select class="form-control" id="modal_valid_id_type">
              <option value="">-- Select ID Type --</option>
              <option value="National ID (Card type)">National ID (Card type)</option>
              <option value="National ID (Paper type) / Digital National ID / ePhilID">National ID (Paper type) / Digital National ID / ePhilID</option>
              <option value="Passport">Passport</option>
              <option value="HDMF (Pag-IBIG Loyalty Plus) ID">HDMF (Pag-IBIG Loyalty Plus) ID</option>
              <option value="Driver's License (including BLTO Driver's License)">Driver's License (including BLTO Driver's License)</option>
              <option value="Philippine Postal ID">Philippine Postal ID</option>
              <option value="PRC ID (Professional Regulation Commission ID)">PRC ID (Professional Regulation Commission ID)</option>
              <option value="UMID (Unified Multi-Purpose ID)">UMID (Unified Multi-Purpose ID)</option>
              <option value="SSS ID">SSS ID</option>
            </select>
          </div>

          <!-- Step 2: Upload Files -->
          <div class="upload-section" id="uploadSection">
            <label class="required">Step 2: Upload ID Images</label>
            
            <!-- ID Front Upload -->
            <div class="upload-box" id="idFrontBox" onclick="document.getElementById('modal_id_front').click()">
              <i class="fas fa-id-card"></i>
              <h6>Upload Valid ID</h6>
              <p>Click to select image</p>
              <input type="file" id="modal_id_front" accept="image/*" style="display: none;">
            </div>
            <div class="preview-container" id="idFrontPreview" style="display: none;">
              <img class="preview-image" id="idFrontImage" src="" alt="ID Preview">
            </div>

            <!-- Selfie Upload -->
            <div class="upload-box" id="selfieBox" onclick="document.getElementById('modal_selfie_with_id').click()">
              <i class="fas fa-camera"></i>
              <h6>Selfie Holding ID</h6>
              <p>Click to select/take photo</p>
              <input type="file" id="modal_selfie_with_id" accept="image/*" style="display: none;">
            </div>
            <div class="preview-container" id="selfiePreview" style="display: none;">
              <img class="preview-image" id="selfieImage" src="" alt="Selfie Preview">
            </div>

            <!-- Validation Result -->
            <div class="validation-result" id="validationResult" style="display: none;"></div>

            <!-- Validate Button -->
            <button type="button" class="btn btn-validate" id="validateBtn" disabled onclick="validateID()">
              <i class="fas fa-check-circle"></i> Validate ID
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    // Global variables
    let provinces = [];
    let municipalities = [];
    let barangays = [];
    let isIDValidated = false;
    let uploadedIDFront = null;
    let uploadedSelfie = null;
    let selectedIDType = null;

    // Load provinces on page load
    async function loadProvinces() {
      try {
        const response = await fetch('https://psgc.gitlab.io/api/provinces/');
        provinces = await response.json();
        
        provinces.sort((a, b) => a.name.localeCompare(b.name));
        
        const provinceSelect = document.getElementById('province');
        provinceSelect.innerHTML = '<option value="">-- Select Province --</option>';
        
        provinces.forEach(province => {
          const option = document.createElement('option');
          option.value = province.code;
          option.textContent = province.name;
          provinceSelect.appendChild(option);
        });
      } catch (error) {
        console.error('Error loading provinces:', error);
        alert('Failed to load provinces. Please refresh the page.');
      }
    }

    async function loadMunicipalities(provinceCode) {
      try {
        const response = await fetch(`https://psgc.gitlab.io/api/provinces/${provinceCode}/cities-municipalities/`);
        municipalities = await response.json();
        
        municipalities.sort((a, b) => a.name.localeCompare(b.name));
        
        const municipalitySelect = document.getElementById('municipality');
        municipalitySelect.innerHTML = '<option value="">-- Select Municipality --</option>';
        municipalitySelect.disabled = false;
        
        municipalities.forEach(municipality => {
          const option = document.createElement('option');
          option.value = municipality.code;
          option.textContent = municipality.name;
          municipalitySelect.appendChild(option);
        });
        
        document.getElementById('barangay').innerHTML = '<option value="">-- Select Barangay --</option>';
        document.getElementById('barangay').disabled = true;
      } catch (error) {
        console.error('Error loading municipalities:', error);
        alert('Failed to load municipalities. Please try again.');
      }
    }

    async function loadBarangays(municipalityCode) {
      try {
        const response = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${municipalityCode}/barangays/`);
        barangays = await response.json();
        
        barangays.sort((a, b) => a.name.localeCompare(b.name));
        
        const barangaySelect = document.getElementById('barangay');
        barangaySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
        barangaySelect.disabled = false;
        
        barangays.forEach(barangay => {
          const option = document.createElement('option');
          option.value = barangay.name;
          option.textContent = barangay.name;
          barangaySelect.appendChild(option);
        });
      } catch (error) {
        console.error('Error loading barangays:', error);
        alert('Failed to load barangays. Please try again.');
      }
    }

    // Event listeners for address dropdowns
    document.getElementById('province').addEventListener('change', function() {
      const provinceCode = this.value;
      if (provinceCode) {
        loadMunicipalities(provinceCode);
      } else {
        document.getElementById('municipality').innerHTML = '<option value="">-- Select Municipality --</option>';
        document.getElementById('municipality').disabled = true;
        document.getElementById('barangay').innerHTML = '<option value="">-- Select Barangay --</option>';
        document.getElementById('barangay').disabled = true;
      }
    });

    document.getElementById('municipality').addEventListener('change', function() {
      const municipalityCode = this.value;
      if (municipalityCode) {
        loadBarangays(municipalityCode);
      } else {
        document.getElementById('barangay').innerHTML = '<option value="">-- Select Barangay --</option>';
        document.getElementById('barangay').disabled = true;
      }
    });

    // Load provinces when page loads
    window.addEventListener('DOMContentLoaded', function() {
      loadProvinces();
    });

    // Modal functionality
    document.getElementById('openModalBtn').addEventListener('click', function() {
      $('#idUploadModal').modal('show');
    });

    // ID Type Selection
    document.getElementById('modal_valid_id_type').addEventListener('change', function() {
      const idType = this.value;
      const uploadSection = document.getElementById('uploadSection');
      
      if (idType) {
        selectedIDType = idType;
        uploadSection.classList.add('active');
        resetUploadSection();
      } else {
        uploadSection.classList.remove('active');
        selectedIDType = null;
      }
    });

    // File upload handling
    document.getElementById('modal_id_front').addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        uploadedIDFront = file;
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('idFrontImage').src = e.target.result;
          document.getElementById('idFrontPreview').style.display = 'block';
          document.getElementById('idFrontBox').classList.add('has-file');
        };
        reader.readAsDataURL(file);
        checkValidateButton();
      }
    });

    document.getElementById('modal_selfie_with_id').addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        uploadedSelfie = file;
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('selfieImage').src = e.target.result;
          document.getElementById('selfiePreview').style.display = 'block';
          document.getElementById('selfieBox').classList.add('has-file');
        };
        reader.readAsDataURL(file);
        checkValidateButton();
      }
    });

    function checkValidateButton() {
      const validateBtn = document.getElementById('validateBtn');
      if (uploadedIDFront && uploadedSelfie && selectedIDType) {
        validateBtn.disabled = false;
      } else {
        validateBtn.disabled = true;
      }
    }

    function resetUploadSection() {
      uploadedIDFront = null;
      uploadedSelfie = null;
      document.getElementById('modal_id_front').value = '';
      document.getElementById('modal_selfie_with_id').value = '';
      document.getElementById('idFrontPreview').style.display = 'none';
      document.getElementById('selfiePreview').style.display = 'none';
      document.getElementById('idFrontBox').classList.remove('has-file');
      document.getElementById('selfieBox').classList.remove('has-file');
      document.getElementById('validationResult').style.display = 'none';
      document.getElementById('validateBtn').disabled = true;
    }

    // Validate ID using AJAX
    async function validateID() {
      const validateBtn = document.getElementById('validateBtn');
      const validationResult = document.getElementById('validationResult');
      
      // Show loading state
      validateBtn.disabled = true;
      validateBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Validating...';
      validationResult.className = 'validation-result loading';
      validationResult.style.display = 'block';
      validationResult.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing ID... Please wait.';
      
      // Create FormData
      const formData = new FormData();
      formData.append('id_front', uploadedIDFront);
      formData.append('valid_id_type', selectedIDType);
      formData.append('validate_only', 'true');
      
      try {
        // Send AJAX request to validate
        const response = await fetch('validate_id_ajax.php', {
          method: 'POST',
          body: formData
        });
        
        // Check if response is ok
        if (!response.ok) {
          throw new Error('Server returned ' + response.status);
        }
        
        // Get response text first to check if it's valid JSON
        const responseText = await response.text();
        
        // Try to parse JSON
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (parseError) {
          console.error('Response was not JSON:', responseText);
          throw new Error('Server returned invalid response. Check console for details.');
        }
        
        if (result.valid) {
          // Success
          validationResult.className = 'validation-result success';
          validationResult.innerHTML = `
            <i class="fas fa-check-circle"></i> 
            ID Validated Successfully! 
            <br>Match Score: ${result.score}%
            <br><button type="button" class="btn btn-primary mt-3" onclick="confirmID()">Confirm & Continue</button>
          `;
          isIDValidated = true;
        } else {
          // Failed validation
          validationResult.className = 'validation-result error';
          validationResult.innerHTML = `
            <i class="fas fa-times-circle"></i> 
            ID Validation Failed!
            <br>Match Score: ${result.score}%
            <br>${result.message}
            <br>Please upload the correct ID type.
          `;
          isIDValidated = false;
        }
      } catch (error) {
        console.error('Validation error:', error);
        validationResult.className = 'validation-result error';
        validationResult.innerHTML = `
          <i class="fas fa-exclamation-triangle"></i> 
          Error: ${error.message}
          <br>Please try again or contact support if the issue persists.
        `;
        isIDValidated = false;
      } finally {
        validateBtn.disabled = false;
        validateBtn.innerHTML = '<i class="fas fa-check-circle"></i> Validate ID';
      }
    }

    function confirmID() {
      if (!isIDValidated) {
        alert('Please validate your ID first!');
        return;
      }
      
      // Transfer files to hidden inputs using DataTransfer
      const dtFront = new DataTransfer();
      dtFront.items.add(uploadedIDFront);
      const hiddenFrontInput = document.getElementById('hidden_id_front');
      hiddenFrontInput.files = dtFront.files;
      
      const dtSelfie = new DataTransfer();
      dtSelfie.items.add(uploadedSelfie);
      const hiddenSelfieInput = document.getElementById('hidden_selfie_with_id');
      hiddenSelfieInput.files = dtSelfie.files;
      
      // Set ID type
      document.getElementById('hidden_valid_id_type').value = selectedIDType;
      
      // Update status
      const idStatus = document.getElementById('idStatus');
      idStatus.className = 'id-status verified';
      idStatus.innerHTML = `<i class="fas fa-check-circle"></i> Valid ID Verified: ${selectedIDType}`;
      
      // Enable submit button
      document.getElementById('submitBtn').disabled = false;
      
      // Close modal
      $('#idUploadModal').modal('hide');
      
      alert('ID validated and saved! You can now complete your registration.');
    }

    // Auto-calculate age from birthday
    document.getElementById('birthday').addEventListener('change', function () {
        const birthDate = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        const dayDiff = today.getDate() - birthDate.getDate();

        if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
          age--;
        }

        if (!isNaN(age) && age >= 0) {
          document.getElementById('age').value = age;
        } else {
          document.getElementById('age').value = '';
        }
    });

    // Password strength checker
    document.getElementById('password').addEventListener('input', function() {
      const password = this.value;
      const strengthDiv = document.getElementById('passwordStrength');
      
      const hasLength = password.length >= 8;
      const hasUppercase = /[A-Z]/.test(password);
      const hasLowercase = /[a-z]/.test(password);
      const hasNumber = /[0-9]/.test(password);
      const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
      
      document.getElementById('length').classList.toggle('valid', hasLength);
      document.getElementById('uppercase').classList.toggle('valid', hasUppercase);
      document.getElementById('lowercase').classList.toggle('valid', hasLowercase);
      document.getElementById('number').classList.toggle('valid', hasNumber);
      document.getElementById('special').classList.toggle('valid', hasSpecial);
      
      const strength = [hasLength, hasUppercase, hasLowercase, hasNumber, hasSpecial].filter(Boolean).length;
      
      if (password.length === 0) {
        strengthDiv.textContent = '';
        strengthDiv.className = 'password-strength';
      } else if (strength < 3) {
        strengthDiv.textContent = 'Weak Password';
        strengthDiv.className = 'password-strength strength-weak';
      } else if (strength < 5) {
        strengthDiv.textContent = 'Medium Password';
        strengthDiv.className = 'password-strength strength-medium';
      } else {
        strengthDiv.textContent = 'Strong Password';
        strengthDiv.className = 'password-strength strength-strong';
      }
    });

    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const eyeIcon = document.getElementById('eyeIcon');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
      }
    });

    // Phone number validation
    document.getElementById('phone_number').addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Form validation before submit
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
      if (!isIDValidated) {
        e.preventDefault();
        alert('Please upload and validate your ID first!');
        return false;
      }

      const password = document.getElementById('password').value;
      const hasLength = password.length >= 8;
      const hasUppercase = /[A-Z]/.test(password);
      const hasLowercase = /[a-z]/.test(password);
      const hasNumber = /[0-9]/.test(password);
      const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
      
      if (!(hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial)) {
        e.preventDefault();
        alert('Please ensure your password meets all requirements.');
        return false;
      }

      const phoneNumber = document.getElementById('phone_number').value;
      if (phoneNumber.length !== 10) {
        e.preventDefault();
        alert('Please enter a valid 10-digit phone number.');
        return false;
      }

      const privacyConsent = document.getElementById('privacy_consent').checked;
      const declarationTruth = document.getElementById('declaration_truth').checked;

      if (!privacyConsent || !declarationTruth) {
        e.preventDefault();
        alert('Please agree to the Data Privacy Consent and Declaration of Truth to proceed.');
        return false;
      }
    });
  </script>
</body>
</html>