<?php
include '../../conn.php';
session_start();

header('Content-Type: application/json');

// Security checks
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_SESSION['is_department_head']) || !$_SESSION['is_department_head']) {
    echo json_encode(['success' => false, 'message' => 'Only department heads can update co-personnel']);
    exit();
}

try {
    // Validate inputs
    $personnel_id = intval($_POST['personnel_id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($personnel_id <= 0) {
        throw new Exception('Invalid personnel ID');
    }
    
    if (empty($first_name) || empty($last_name) || empty($email)) {
        throw new Exception('All required fields must be filled');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Sanitize inputs
    $first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
    $middle_name = htmlspecialchars($middle_name, ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    // Get auth_id and verify this person exists and is not department head
    $auth_stmt = $pdo->prepare("
        SELECT auth_id, is_department_head, department_id 
        FROM lgu_personnel 
        WHERE id = ?
    ");
    $auth_stmt->execute([$personnel_id]);
    $personnel_data = $auth_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$personnel_data) {
        throw new Exception('Personnel not found');
    }
    
    // Prevent editing department heads
    if ($personnel_data['is_department_head'] == 1) {
        throw new Exception('Cannot edit department head');
    }
    
    // Verify the personnel belongs to the same department
    $current_dept_stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE id = ?");
    $current_dept_stmt->execute([$_SESSION['personnel_id']]);
    $current_dept = $current_dept_stmt->fetchColumn();
    
    if ($personnel_data['department_id'] != $current_dept) {
        throw new Exception('You can only edit personnel in your department');
    }
    
    $auth_id = $personnel_data['auth_id'];
    
    // Check if email already exists (excluding current user)
    $check_email = $pdo->prepare("SELECT id FROM auth WHERE email = ? AND id != ?");
    $check_email->execute([$email, $auth_id]);
    if ($check_email->fetch()) {
        throw new Exception('Email address already exists');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update lgu_personnel table
    $update_personnel = $pdo->prepare("
        UPDATE lgu_personnel 
        SET first_name = ?, middle_name = ?, last_name = ?
        WHERE id = ?
    ");
    $update_personnel->execute([$first_name, $middle_name, $last_name, $personnel_id]);
    
    // Update auth table
    if (!empty($password)) {
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters');
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_auth = $pdo->prepare("UPDATE auth SET email = ?, password = ? WHERE id = ?");
        $update_auth->execute([$email, $hashed_password, $auth_id]);
    } else {
        $update_auth = $pdo->prepare("UPDATE auth SET email = ? WHERE id = ?");
        $update_auth->execute([$email, $auth_id]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Co-personnel updated successfully!'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>