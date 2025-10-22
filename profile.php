<?php
session_start();
require_once 'conn.php';

// Guard: must be logged in
if (!isset($_SESSION['auth_id']) || empty($_SESSION['auth_id'])) {
  echo "<div class='alert alert-danger m-3'>Unauthorized access.</div>";
  exit();
}

$auth_id = (int)$_SESSION['auth_id'];
$role    = $_SESSION['role'] ?? '';

if (!in_array($role, ['Resident','Admin','LGU Personnel'], true)) {
  echo "<div class='alert alert-danger m-3'>Unknown role.</div>";
  exit();
}

try {
  if ($role === 'Resident') {
    $stmt = $pdo->prepare("SELECT r.*, a.email FROM residents r JOIN auth a ON a.id = r.auth_id WHERE r.auth_id = ? LIMIT 1");
  } elseif ($role === 'Admin') {
    $stmt = $pdo->prepare("SELECT ad.*, a.email FROM admins ad JOIN auth a ON a.id = ad.auth_id WHERE ad.auth_id = ? LIMIT 1");
  } else { // LGU Personnel
    $stmt = $pdo->prepare("SELECT lp.*, a.email, d.name AS department_name
                           FROM lgu_personnel lp
                           JOIN auth a ON a.id = lp.auth_id
                           LEFT JOIN departments d ON d.id = lp.department_id
                           WHERE lp.auth_id = ? LIMIT 1");
  }
  $stmt->execute([$auth_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) {
    echo "<div class='alert alert-danger m-3'>User record not found for role: ".htmlspecialchars($role).".</div>";
    exit();
  }
} catch (Exception $e) {
  echo "<div class='alert alert-danger m-3'>Database error: ".htmlspecialchars($e->getMessage())."</div>";
  exit();
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile - <?php echo h($role); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
  <style>
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      padding: 1.5rem 0;
    }

    .profile-container {
      max-width: 1000px;
      margin: 0 auto;
    }

    .profile-header {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
      position: relative;
      overflow: hidden;
    }

    .profile-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 300px;
      height: 300px;
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
      border-radius: 50%;
    }

    .profile-header-content {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .profile-avatar {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg,  #2c3e50 0%, #3498db 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 2rem;
      font-weight: bold;
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .profile-info {
      flex: 1;
      min-width: 200px;
    }

    .profile-info h3 {
      margin: 0;
      color: #2d3748;
      font-weight: 700;
      font-size: 1.5rem;
    }

    .profile-info p {
      margin: 0.25rem 0 0 0;
      color: #718096;
      font-size: 0.95rem;
    }

    .role-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: linear-gradient(135deg,  #2c3e50 0%, #3498db 100%);
      color: white;
      padding: 0.5rem 1.25rem;
      border-radius: 25px;
      font-weight: 600;
      font-size: 0.9rem;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .section-card {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
    }

    .section-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 35px rgba(0, 0, 0, 0.12);
    }

    .section-title {
      font-weight: 700;
      color: #2d3748;
      font-size: 1.2rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding-bottom: 1rem;
      border-bottom: 3px solid #f0f3f7;
    }

    .section-title i {
      color: #3498db
      font-size: 1.3rem;
    }

    .form-group label {
      font-weight: 600;
      color: #4a5568;
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
    }

    .form-control {
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      padding: 0.75rem 1rem;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      background: #f8fafc;
    }

    .form-control:focus {
      border-color: #3498db;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      outline: none;
      background: white;
    }

    .form-control:disabled {
      background: #f0f3f7;
      color: #718096;
      cursor: not-allowed;
    }

    select.form-control {
      cursor: pointer;
    }

    .btn {
      border-radius: 10px;
      padding: 0.75rem 1.75rem;
      font-weight: 600;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      border: none;
    }

    .btn-primary {
      background: linear-gradient(135deg,  #2c3e50 0%, #3498db 100%);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-warning {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    .btn-warning:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
      color: white;
    }

    .btn-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }

    .btn-secondary {
      background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
      box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
    }

    .btn-secondary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(107, 114, 128, 0.4);
    }

    .action-buttons {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .alert {
      border-radius: 12px;
      border: none;
      padding: 1rem 1.25rem;
      font-weight: 500;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }

    .alert-success {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      color: #065f46;
    }

    .alert-danger {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #991b1b;
    }

    .modal-content {
      border-radius: 20px;
      border: none;
      overflow: hidden;
    }

    .modal-header {
      background: linear-gradient(135deg,  #2c3e50 0%, #3498db 100%);
      color: white;
      padding: 1.5rem;
      border: none;
    }

    .modal-header .modal-title {
      font-weight: 700;
      font-size: 1.25rem;
    }

    .modal-header .close {
      color: white;
      opacity: 1;
      font-size: 1.5rem;
      text-shadow: none;
    }

    .modal-body {
      padding: 2rem;
    }

    .modal-footer {
      border-top: 2px solid #f0f3f7;
      padding: 1.5rem 2rem;
    }

    .info-note {
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
      border-left: 4px solid #3b82f6;
      padding: 1rem;
      border-radius: 8px;
      color: #1e40af;
      font-size: 0.9rem;
    }

    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .section-card {
      animation: fadeInUp 0.5s ease forwards;
    }

    .section-card:nth-child(1) { animation-delay: 0.1s; }
    .section-card:nth-child(2) { animation-delay: 0.2s; }
    .section-card:nth-child(3) { animation-delay: 0.3s; }

    /* Mobile Responsive */
    @media (max-width: 576px) {
      body {
        padding: 1rem 0;
      }

      .profile-header {
        padding: 1.5rem;
        border-radius: 15px;
      }

      .profile-avatar {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
      }

      .profile-info h3 {
        font-size: 1.25rem;
      }

      .profile-info p {
        font-size: 0.85rem;
      }

      .role-badge {
        padding: 0.4rem 1rem;
        font-size: 0.85rem;
      }

      .section-card {
        padding: 1.5rem;
        border-radius: 15px;
      }

      .section-title {
        font-size: 1.1rem;
      }

      .form-control {
        padding: 0.65rem 0.9rem;
        font-size: 0.9rem;
      }

      .btn {
        width: 100%;
        margin-bottom: 0.5rem;
      }

      .action-buttons {
        flex-direction: column;
      }

      .modal-body {
        padding: 1.5rem;
      }
    }

    /* Tablet Responsive */
    @media (min-width: 577px) and (max-width: 991px) {
      .profile-header {
        padding: 1.75rem;
      }

      .section-card {
        padding: 1.75rem;
      }
    }

    /* Desktop Optimization */
    @media (min-width: 992px) {
      body {
        padding: 2.5rem 0;
      }

      .profile-header {
        padding: 2.5rem;
      }
    }
  </style>
</head>
<body>
<div class="container profile-container px-3 px-md-4">
  <div class="profile-header">
    <div class="profile-header-content">
      <div class="profile-avatar">
        <?php 
          $initials = '';
          if (!empty($user['first_name'])) {
            $initials .= strtoupper(substr($user['first_name'], 0, 1));
          }
          if (!empty($user['last_name'])) {
            $initials .= strtoupper(substr($user['last_name'], 0, 1));
          }
          echo $initials ?: 'U';
        ?>
      </div>
      <div class="profile-info">
        <h3><?php echo h($user['first_name'] ?? '') . ' ' . h($user['last_name'] ?? ''); ?></h3>
        <p><i class="fas fa-envelope mr-1"></i> <?php echo h($user['email'] ?? ''); ?></p>
      </div>
      <div class="ml-auto">
        <span class="role-badge">
          <i class="fas fa-user-tag"></i>
          <?php echo h($role); ?>
        </span>
      </div>
    </div>
  </div>

  <div id="ajaxAlert"></div>

  <form id="updateProfileForm">
    <input type="hidden" name="auth_id" value="<?php echo $auth_id; ?>">
    <input type="hidden" name="role" value="<?php echo h($role); ?>">

    <!-- Account Information -->
    <div class="section-card">
      <div class="section-title">
        <i class="fas fa-user-circle"></i>
        <span>Account Information</span>
      </div>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label><i class="fas fa-id-badge mr-1"></i> First Name</label>
          <input type="text" name="first_name" class="form-control" value="<?php echo h($user['first_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group col-md-4">
          <label><i class="fas fa-id-badge mr-1"></i> Middle Name</label>
          <input type="text" name="middle_name" class="form-control" value="<?php echo h($user['middle_name'] ?? ''); ?>">
        </div>
        <div class="form-group col-md-4">
          <label><i class="fas fa-id-badge mr-1"></i> Last Name</label>
          <input type="text" name="last_name" class="form-control" value="<?php echo h($user['last_name'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label><i class="fas fa-envelope mr-1"></i> Email Address</label>
        <input type="email" name="email" class="form-control" value="<?php echo h($user['email'] ?? ''); ?>" required>
      </div>
    </div>

    <?php if ($role === 'Resident'): ?>
      <div class="section-card">
        <div class="section-title">
          <i class="fas fa-address-card"></i>
          <span>Personal Information</span>
        </div>
        
        <div class="form-row">
          <div class="form-group col-md-4">
            <label><i class="fas fa-calendar mr-1"></i> Birthday</label>
            <input type="date" name="birthday" class="form-control" value="<?php echo h($user['birthday'] ?? ''); ?>">
          </div>
          <div class="form-group col-md-2">
            <label><i class="fas fa-hashtag mr-1"></i> Age</label>
            <input type="number" name="age" class="form-control" value="<?php echo h($user['age'] ?? ''); ?>">
          </div>
          <div class="form-group col-md-3">
            <label><i class="fas fa-venus-mars mr-1"></i> Sex</label>
            <select name="sex" class="form-control">
              <option value="">Select</option>
              <option value="Male"   <?php echo (isset($user['sex']) && $user['sex']==='Male')?'selected':''; ?>>Male</option>
              <option value="Female" <?php echo (isset($user['sex']) && $user['sex']==='Female')?'selected':''; ?>>Female</option>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label><i class="fas fa-ring mr-1"></i> Civil Status</label>
            <select name="civil_status" class="form-control">
              <option value="">Select</option>
              <option value="Single"  <?php echo (isset($user['civil_status']) && $user['civil_status']==='Single')?'selected':''; ?>>Single</option>
              <option value="Married" <?php echo (isset($user['civil_status']) && $user['civil_status']==='Married')?'selected':''; ?>>Married</option>
            </select>
          </div>
        </div>
        
        <div class="form-group">
          <label><i class="fas fa-map-marker-alt mr-1"></i> Address</label>
          <input type="text" name="address" class="form-control" value="<?php echo h($user['address'] ?? ''); ?>">
        </div>
      </div>
    <?php elseif ($role === 'LGU Personnel'): ?>
      <div class="section-card">
        <div class="section-title">
          <i class="fas fa-building"></i>
          <span>Personnel Details</span>
        </div>
        
        <div class="form-group">
          <label><i class="fas fa-sitemap mr-1"></i> Department</label>
          <input type="text" class="form-control" value="<?php echo h($user['department_name'] ?? 'Not Assigned'); ?>" disabled>
        </div>
        
        <div class="info-note">
          <i class="fas fa-info-circle mr-2"></i>
          Your department assignment is managed by system administrators. Contact admin if you need to change departments.
        </div>
      </div>
    <?php elseif ($role === 'Admin'): ?>
      <div class="section-card">
        <div class="section-title">
          <i class="fas fa-user-shield"></i>
          <span>Administrator Information</span>
        </div>
        
        <div class="info-note">
          <i class="fas fa-info-circle mr-2"></i>
          As an administrator, your profile contains name and email information. Additional settings can be configured in the admin dashboard.
        </div>
      </div>
    <?php endif; ?>

    <div class="action-buttons">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-2"></i> Save Changes
      </button>
      <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#changePasswordModal">
        <i class="fas fa-lock mr-2"></i> Change Password
      </button>
    </div>
  </form>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form id="changePasswordForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-key mr-2"></i>
          Change Password
        </h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="auth_id" value="<?php echo $auth_id; ?>">
        
        <div class="form-group">
          <label><i class="fas fa-lock mr-1"></i> Current Password</label>
          <input type="password" name="current_password" class="form-control" required placeholder="Enter your current password">
        </div>
        
        <div class="form-group">
          <label><i class="fas fa-key mr-1"></i> New Password</label>
          <input type="password" name="new_password" class="form-control" required placeholder="Enter new password">
        </div>
        
        <div class="form-group">
          <label><i class="fas fa-check-circle mr-1"></i> Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" required placeholder="Confirm new password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">
          <i class="fas fa-check mr-2"></i> Change Password
        </button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          <i class="fas fa-times mr-2"></i> Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
  // AJAX profile update
  $("#updateProfileForm").on("submit", function(e){
    e.preventDefault();
    $.ajax({
      url: 'update_profile.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(json){
        const cls = (json && json.status === 'success') ? 'success' : 'danger';
        const msg = (json && json.message) ? json.message : 'Unexpected error.';
        $('#ajaxAlert').html(`<div class="alert alert-${cls}"><i class="fas fa-${cls === 'success' ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>${msg}</div>`);
        
        // Scroll to alert
        $('html, body').animate({
          scrollTop: $('#ajaxAlert').offset().top - 100
        }, 500);

        // Auto-hide after 5 seconds
        setTimeout(function() {
          $('#ajaxAlert').fadeOut(500, function() {
            $(this).html('');
            $(this).show();
          });
        }, 5000);
      },
      error: function(){
        $('#ajaxAlert').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Request failed.</div>');
      }
    });
  });

  // AJAX password change
  $("#changePasswordForm").on("submit", function(e){
    e.preventDefault();
    $.ajax({
      url: 'change_password.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(json){
        $('#changePasswordModal').modal('hide');

        // Fix lingering backdrop
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right','');

        const cls = (json && json.status === 'success') ? 'success' : 'danger';
        const msg = (json && json.message) ? json.message : 'Unexpected error.';
        $('#ajaxAlert').html(`<div class="alert alert-${cls}"><i class="fas fa-${cls === 'success' ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>${msg}</div>`);
        $('#changePasswordForm')[0].reset();

        // Scroll to alert
        $('html, body').animate({
          scrollTop: $('#ajaxAlert').offset().top - 100
        }, 500);

        // Auto-hide after 5 seconds
        setTimeout(function() {
          $('#ajaxAlert').fadeOut(500, function() {
            $(this).html('');
            $(this).show();
          });
        }, 5000);
      },
      error: function(){
        $('#changePasswordModal').modal('hide');
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right','');
        $('#ajaxAlert').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Request failed.</div>');
      }
    });
  });
});
</script>
</body>
</html>