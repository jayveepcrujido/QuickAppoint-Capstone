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
    echo json_encode(['success' => false, 'message' => 'Only department heads can add co-personnel']);
    exit();
}

try {
    // Validate inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        throw new Exception('All required fields must be filled');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    
    // Sanitize inputs
    $first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
    $middle_name = htmlspecialchars($middle_name, ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    // Check if email already exists
    $check_email = $pdo->prepare("SELECT id FROM auth WHERE email = ?");
    $check_email->execute([$email]);
    if ($check_email->fetch()) {
        throw new Exception('Email address already exists');
    }
    
    // Get department info
    $personnel_id = $_SESSION['personnel_id'];
    $dept_stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE id = ?");
    $dept_stmt->execute([$personnel_id]);
    $department_id = $dept_stmt->fetchColumn();
    
    if (!$department_id) {
        throw new Exception('Department not found');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert into auth table
    $auth_stmt = $pdo->prepare("
        INSERT INTO auth (email, password, role) 
        VALUES (?, ?, 'LGU Personnel')
    ");
    $auth_stmt->execute([$email, $hashed_password]);
    $auth_id = $pdo->lastInsertId();
    
    // Insert into lgu_personnel table - REMOVED created_by_admin, set is_department_head = 0
    $personnel_stmt = $pdo->prepare("
        INSERT INTO lgu_personnel 
        (auth_id, first_name, middle_name, last_name, department_id, is_department_head, created_by_personnel_id) 
        VALUES (?, ?, ?, ?, ?, 0, ?)
    ");
    $personnel_stmt->execute([
        $auth_id,
        $first_name,
        $middle_name,
        $last_name,
        $department_id,
        $personnel_id
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Co-personnel created successfully!'
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