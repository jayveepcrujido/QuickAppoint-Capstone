<?php
session_start();
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $id_front_path = $uploadDir . uniqid() . '_front_' . basename($_FILES['id_front']['name']);
    $id_back_path = $uploadDir . uniqid() . '_back_' . basename($_FILES['id_back']['name']);
    $selfie_with_id_path = $uploadDir . uniqid() . '_selfie_' . basename($_FILES['selfie_with_id']['name']);

    if (move_uploaded_file($_FILES['id_front']['tmp_name'], $id_front_path) &&
        move_uploaded_file($_FILES['id_back']['tmp_name'], $id_back_path) &&
        move_uploaded_file($_FILES['selfie_with_id']['tmp_name'], $selfie_with_id_path)) {

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
                    valid_id_type, id_front_image, id_back_image, selfie_with_id_image, phone_number
                ) VALUES (
                    :auth_id, :first_name, :middle_name, :last_name,
                    :address, :birthday, :age, :sex, :civil_status,
                    :valid_id_type, :id_front_image, :id_back_image, :selfie_with_id_image, :phone_number
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
                'id_front_image'       => $id_front_path,
                'id_back_image'        => $id_back_path,
                'selfie_with_id_image' => $selfie_with_id_path,
                'phone_number'         => $phone_number
            ]);

            $pdo->commit();

            echo "<script>alert('Registration successful!'); window.location.href='login.php';</script>";
        } catch (Exception $e) {
            $pdo->rollBack();
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
      padding: 20px;
    }

    .register-container {
      margin: auto;
      padding: 2rem;
      border-radius: 15px;
      background: #fff;
      max-width: 900px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .register-container h2 {
      text-align: center;
      font-weight: 700;
      color: #27548A;
      margin-bottom: 1.5rem;
    }

    .form-section {
      margin-bottom: 2rem;
      padding-bottom: 2rem;
      border-bottom: 2px solid #e9ecef;
    }

    .form-section:last-child {
      border-bottom: none;
    }

    .section-title {
      font-size: 18px;
      font-weight: 700;
      color: #27548A;
      margin-bottom: 1.5rem;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid #27548A;
    }

    .form-control {
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 14px;
    }

    select.form-control {
      height: auto;
      min-height: 42px;
      line-height: 1.5;
    }

    select.form-control option {
      padding: 8px 12px;
      font-size: 14px;
      line-height: 1.5;
      white-space: normal;
      word-wrap: break-word;
    }

    label {
      font-size: 14px;
      font-weight: 600;
      color: #333;
    }

    .required::after {
      content: " *";
      color: red;
    }

    .btn-primary {
      background-color: #27548A;
      border: none;
      border-radius: 10px;
      margin-top: 1rem;
      padding: 12px;
      font-weight: bold;
      font-size: 15px;
      transition: 0.3s;
      width: 100%;
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

    .input-group-text {
      background-color: #27548A;
      color: white;
      border-radius: 10px 0 0 10px;
      border: none;
      font-weight: 600;
    }

    .input-group .form-control {
      border-radius: 0 10px 10px 0;
    }

    .password-strength {
      font-size: 12px;
      margin-top: 5px;
    }

    .strength-weak { color: #dc3545; }
    .strength-medium { color: #ffc107; }
    .strength-strong { color: #28a745; }

    .password-requirements {
      font-size: 12px;
      color: #6c757d;
      margin-top: 5px;
      padding-left: 20px;
    }

    .password-requirements li {
      list-style: none;
      position: relative;
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

    .image-preview {
      max-width: 200px;
      max-height: 200px;
      margin-top: 10px;
      display: none;
      border: 2px solid #27548A;
      border-radius: 10px;
    }

    .file-input-wrapper {
      position: relative;
      overflow: hidden;
      display: inline-block;
      width: 100%;
    }

    .file-input-wrapper input[type=file] {
      position: absolute;
      left: -9999px;
    }

    .file-input-label {
      display: block;
      padding: 10px 12px;
      background-color: #f8f9fa;
      border: 2px dashed #27548A;
      border-radius: 10px;
      cursor: pointer;
      text-align: center;
      transition: 0.3s;
    }

    .file-input-label:hover {
      background-color: #e9ecef;
    }

    .file-input-label i {
      margin-right: 8px;
    }

    .custom-control-label {
      font-size: 13px;
      line-height: 1.6;
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
      margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
      .register-container {
        padding: 1.5rem;
      }
      .form-control {
        font-size: 13px;
        padding: 8px 10px;
      }
    }
  </style>
</head>
<body>
  <div class="register-container">
    <h2>Register for LGU QuickAppoint</h2>
    <form method="POST" enctype="multipart/form-data" id="registrationForm">
      
      <!-- SECTION 1: VALID ID -->
      <div class="form-section">
        <div class="section-title">1. Valid ID Information</div>
        
        <div class="form-group">
          <label for="valid_id_type" class="required">Select Valid ID Type</label>
          <select name="valid_id_type" id="valid_id_type" class="form-control" required>
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

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="id_front" class="required">Upload ID (Front)</label>
              <div class="file-input-wrapper">
                <label for="id_front" class="file-input-label">
                  <i class="fas fa-cloud-upload-alt"></i>
                  <span id="id_front_label">Choose Front Image</span>
                </label>
                <input type="file" name="id_front" id="id_front" accept="image/*" required>
              </div>
              <img id="id_front_preview" class="image-preview" alt="ID Front Preview">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="id_back" class="required">Upload ID (Back)</label>
              <div class="file-input-wrapper">
                <label for="id_back" class="file-input-label">
                  <i class="fas fa-cloud-upload-alt"></i>
                  <span id="id_back_label">Choose Back Image</span>
                </label>
                <input type="file" name="id_back" id="id_back" accept="image/*" required>
              </div>
              <img id="id_back_preview" class="image-preview" alt="ID Back Preview">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="selfie_with_id" class="required">Upload Selfie Holding Your ID</label>
          <div class="file-input-wrapper">
            <label for="selfie_with_id" class="file-input-label">
              <i class="fas fa-camera"></i>
              <span id="selfie_label">Take or Choose Selfie</span>
            </label>
            <input type="file" name="selfie_with_id" id="selfie_with_id" accept="image/*" required>
          </div>
          <img id="selfie_preview" class="image-preview" alt="Selfie Preview">
        </div>
      </div>

      <!-- SECTION 2: PERSONAL INFORMATION -->
      <div class="form-section">
        <div class="section-title">2. Personal Information</div>
        
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label for="first_name" class="required">First Name</label>
              <input type="text" name="first_name" id="first_name" class="form-control" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="middle_name">Middle Name</label>
              <input type="text" name="middle_name" id="middle_name" class="form-control">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="last_name" class="required">Last Name</label>
              <input type="text" name="last_name" id="last_name" class="form-control" required>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="province" class="required">Province</label>
              <select name="province" id="province" class="form-control" required>
                <option value="">-- Loading Provinces... --</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="municipality" class="required">Municipality/City</label>
              <select name="municipality" id="municipality" class="form-control" required disabled>
                <option value="">-- Select Municipality --</option>
              </select>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="barangay" class="required">Barangay</label>
              <select name="barangay" id="barangay" class="form-control" required disabled>
                <option value="">-- Select Barangay --</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="street" class="required">Street/Purok</label>
              <input type="text" name="street" id="street" class="form-control" placeholder="Enter street/purok name" required>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="house_number">House Number / Building Name (Optional)</label>
          <input type="text" name="house_number" id="house_number" class="form-control" placeholder="e.g., Block 5 Lot 10, Unit 204">
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="birthday" class="required">Birthday</label>
              <input type="date" name="birthday" id="birthday" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="age">Age</label>
              <input type="number" name="age" id="age" class="form-control" readonly required>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="sex" class="required">Sex</label>
              <select name="sex" id="sex" class="form-control" required>
                <option value="">-- Select Sex --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="civil_status" class="required">Civil Status</label>
              <select name="civil_status" id="civil_status" class="form-control" required>
                <option value="">-- Select Civil Status --</option>
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
        </div>
      </div>

      <!-- SECTION 3: ACCOUNT INFORMATION -->
      <div class="form-section">
        <div class="section-title">3. Account Information</div>
        
        <div class="form-group">
          <label for="email" class="required">Email Address</label>
          <input type="email" name="email" id="email" class="form-control" placeholder="example@email.com" required>
        </div>

        <div class="form-group">
          <label for="phone_number" class="required">Phone Number</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text">+63</span>
            </div>
            <input type="tel" name="phone_number" id="phone_number" class="form-control" placeholder="9123456789" pattern="[0-9]{10}" maxlength="10" required>
          </div>
          <small class="form-text text-muted">Enter 10-digit mobile number (e.g., 9123456789)</small>
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

      <!-- SECTION 4: DATA PRIVACY AND DECLARATION -->
      <div class="form-section">
        <div class="section-title">4. Data Privacy Consent & Declaration</div>
        
        <div class="form-group">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="privacy_consent" name="privacy_consent" required>
            <label class="custom-control-label" for="privacy_consent">
              <strong>Data Privacy Consent:</strong> I consent to the collection and processing of my personal information in accordance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>. I understand that my data will be used solely for legitimate and official LGU transactions, and will be handled with confidentiality and security.
            </label>
          </div>
        </div>

        <div class="form-group">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="declaration_truth" name="declaration_truth" required>
            <label class="custom-control-label" for="declaration_truth">
              <strong>Declaration of Truth:</strong> I hereby certify that all information provided is true, correct, and complete to the best of my knowledge. I acknowledge that providing false information may result in the denial of services and penalties under applicable laws including the Revised Penal Code.
            </label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Complete Registration</button>
      <a href="login.php" class="login-link">Already have an account? Login Here</a>
    </form>
  </div>

  <script>
    // Philippine Address Data using PSGC API
    let provinces = [];
    let municipalities = [];
    let barangays = [];

    // Load provinces on page load
    async function loadProvinces() {
      try {
        const response = await fetch('https://psgc.gitlab.io/api/provinces/');
        provinces = await response.json();
        
        // Sort alphabetically
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

    // Load municipalities based on selected province
    async function loadMunicipalities(provinceCode) {
      try {
        const response = await fetch(`https://psgc.gitlab.io/api/provinces/${provinceCode}/cities-municipalities/`);
        municipalities = await response.json();
        
        // Sort alphabetically
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
        
        // Reset barangay
        document.getElementById('barangay').innerHTML = '<option value="">-- Select Barangay --</option>';
        document.getElementById('barangay').disabled = true;
      } catch (error) {
        console.error('Error loading municipalities:', error);
        alert('Failed to load municipalities. Please try again.');
      }
    }

    // Load barangays based on selected municipality
    async function loadBarangays(municipalityCode) {
      try {
        const response = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${municipalityCode}/barangays/`);
        barangays = await response.json();
        
        // Sort alphabetically
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

    // Event listeners for cascading dropdowns
    document.getElementById('province').addEventListener('change', function() {
      const provinceCode = this.value;
      if (provinceCode) {
        const selectedProvince = provinces.find(p => p.code === provinceCode);
        if (selectedProvince) {
          loadMunicipalities(provinceCode);
        }
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

    // Image preview functionality
    function setupImagePreview(inputId, previewId, labelId) {
      document.getElementById(inputId).addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById(previewId);
        const label = document.getElementById(labelId);
        
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
          }
          reader.readAsDataURL(file);
          label.textContent = file.name;
        }
      });
    }

    setupImagePreview('id_front', 'id_front_preview', 'id_front_label');
    setupImagePreview('id_back', 'id_back_preview', 'id_back_label');
    setupImagePreview('selfie_with_id', 'selfie_preview', 'selfie_label');

    // Password strength checker
    document.getElementById('password').addEventListener('input', function() {
      const password = this.value;
      const strengthDiv = document.getElementById('passwordStrength');
      
      // Check requirements
      const hasLength = password.length >= 8;
      const hasUppercase = /[A-Z]/.test(password);
      const hasLowercase = /[a-z]/.test(password);
      const hasNumber = /[0-9]/.test(password);
      const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
      
      // Update requirement indicators
      document.getElementById('length').classList.toggle('valid', hasLength);
      document.getElementById('uppercase').classList.toggle('valid', hasUppercase);
      document.getElementById('lowercase').classList.toggle('valid', hasLowercase);
      document.getElementById('number').classList.toggle('valid', hasNumber);
      document.getElementById('special').classList.toggle('valid', hasSpecial);
      
      // Calculate strength
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

      // Check consent checkboxes
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