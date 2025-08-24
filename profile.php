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
  <title>Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>
  <style>
    body { background: #f8fafc; }
    .section-card { border: 1px solid #e9ecef; border-radius: .5rem; }
    .section-card > .title { font-weight: 600; color: #6c757d; }
  </style>
</head>
<body>
<div class="container mt-5 mb-5">
  <div class="d-flex align-items-center mb-4">
    <h3 class="mb-0 font-weight-bold">
      <i class="fas fa-user-edit text-primary mr-2"></i>
      Edit Profile
    </h3>
    <span class="badge badge-info ml-3"><?php echo h($role); ?></span>
  </div>

  <div id="ajaxAlert"></div>

  <form id="updateProfileForm">
    <input type="hidden" name="auth_id" value="<?php echo $auth_id; ?>">
    <input type="hidden" name="role" value="<?php echo h($role); ?>">

    <!-- Common: Name + Email -->
    <div class="section-card bg-white p-3 mb-4 shadow-sm">
      <div class="title mb-3">Account</div>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label>First Name</label>
          <input type="text" name="first_name" class="form-control" value="<?php echo h($user['first_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group col-md-4">
          <label>Middle Name</label>
          <input type="text" name="middle_name" class="form-control" value="<?php echo h($user['middle_name'] ?? ''); ?>">
        </div>
        <div class="form-group col-md-4">
          <label>Last Name</label>
          <input type="text" name="last_name" class="form-control" value="<?php echo h($user['last_name'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="<?php echo h($user['email'] ?? ''); ?>" required>
      </div>
    </div>

    <?php if ($role === 'Resident'): ?>
      <div class="section-card bg-light p-3 mb-4 shadow-sm">
        <div class="title mb-3">Personal Information</div>
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Birthday</label>
            <input type="date" name="birthday" class="form-control" value="<?php echo h($user['birthday'] ?? ''); ?>">
          </div>
          <div class="form-group col-md-2">
            <label>Age</label>
            <input type="number" name="age" class="form-control" value="<?php echo h($user['age'] ?? ''); ?>">
          </div>
          <div class="form-group col-md-3">
            <label>Sex</label>
            <select name="sex" class="form-control">
              <option value="">Select</option>
              <option value="Male"   <?php echo (isset($user['sex']) && $user['sex']==='Male')?'selected':''; ?>>Male</option>
              <option value="Female" <?php echo (isset($user['sex']) && $user['sex']==='Female')?'selected':''; ?>>Female</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Civil Status</label>
            <select name="civil_status" class="form-control">
              <option value="">Select</option>
              <option value="Single"  <?php echo (isset($user['civil_status']) && $user['civil_status']==='Single')?'selected':''; ?>>Single</option>
              <option value="Married" <?php echo (isset($user['civil_status']) && $user['civil_status']==='Married')?'selected':''; ?>>Married</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" class="form-control" value="<?php echo h($user['address'] ?? ''); ?>">
        </div>
      </div>
    <?php elseif ($role === 'LGU Personnel'): ?>
      <div class="section-card bg-light p-3 mb-4 shadow-sm">
        <div class="title mb-3">Personnel Details</div>
        <div class="form-group">
          <label>Department</label>
          <input type="text" class="form-control" value="<?php echo h($user['department_name'] ?? ''); ?>" disabled>
          <small class="text-muted">Department is managed by admin.</small>
        </div>
      </div>
    <?php elseif ($role === 'Admin'): ?>
      <div class="section-card bg-light p-3 mb-4 shadow-sm">
        <div class="title mb-3">Administrator</div>
        <p class="mb-0 text-muted">Admins only store name details in <code>admins</code>. Email comes from <code>auth</code>.</p>
      </div>
    <?php endif; ?>

    <div class="d-flex justify-content-start">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
      <button type="button" class="btn btn-warning ml-2" data-toggle="modal" data-target="#changePasswordModal">
        <i class="fas fa-lock mr-1"></i> Change Password
      </button>
    </div>
  </form>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form id="changePasswordForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Password</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="auth_id" value="<?php echo $auth_id; ?>">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Change Password</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
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
        $('#ajaxAlert').html(`<div class="alert alert-${cls}">${msg}</div>`);
      },
      error: function(){
        $('#ajaxAlert').html('<div class="alert alert-danger">Request failed.</div>');
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
      $('#ajaxAlert').html(`<div class="alert alert-${cls}">${msg}</div>`);
      $('#changePasswordForm')[0].reset();
    },
    error: function(){
      $('#changePasswordModal').modal('hide');
      $('.modal-backdrop').remove();
      $('body').removeClass('modal-open').css('padding-right','');
      $('#ajaxAlert').html('<div class="alert alert-danger">Request failed.</div>');
    }
  });
});
});
</script>
</body>
</html>
