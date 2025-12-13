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
    echo json_encode(['success' => false, 'message' => 'Only department heads can delete co-personnel']);
    exit();
}

try {
    $personnel_id = intval($_POST['id'] ?? 0);
    
    if ($personnel_id <= 0) {
        throw new Exception('Invalid personnel ID');
    }
    
    // Get auth_id before deletion
    $auth_stmt = $pdo->prepare("SELECT auth_id, is_department_head FROM lgu_personnel WHERE id = ?");
    $auth_stmt->execute([$personnel_id]);
    $personnel = $auth_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$personnel) {
        throw new Exception('Personnel not found');
    }
    
    // Prevent deleting department heads
    if ($personnel['is_department_head']) {
        throw new Exception('Cannot delete department head');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete from auth table (cascade will handle lgu_personnel)
    $delete_auth = $pdo->prepare("DELETE FROM auth WHERE id = ?");
    $delete_auth->execute([$personnel['auth_id']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Co-personnel deleted successfully!'
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