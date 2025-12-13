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
    echo json_encode(['success' => false, 'message' => 'Only department heads can view co-personnel']);
    exit();
}

try {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception('Invalid personnel ID');
    }
    
    // Verify the personnel belongs to the same department
    $current_dept_stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE id = ?");
    $current_dept_stmt->execute([$_SESSION['personnel_id']]);
    $current_dept = $current_dept_stmt->fetchColumn();
    
    // Get personnel data
    $stmt = $pdo->prepare("
        SELECT 
            lp.id,
            lp.first_name,
            lp.middle_name,
            lp.last_name,
            lp.department_id,
            a.email
        FROM lgu_personnel lp
        JOIN auth a ON lp.auth_id = a.id
        WHERE lp.id = ? AND lp.is_department_head = 0
    ");
    $stmt->execute([$id]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$personnel) {
        throw new Exception('Personnel not found');
    }
    
    // Verify same department
    if ($personnel['department_id'] != $current_dept) {
        throw new Exception('You can only view personnel in your department');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $personnel
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>