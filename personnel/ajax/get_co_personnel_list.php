<?php
include '../../conn.php';
session_start();

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if user is department head
if (!isset($_SESSION['is_department_head']) || !$_SESSION['is_department_head']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit();
}

try {
    $personnel_id = $_SESSION['personnel_id'];
    $dept_stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE id = ?");
    $dept_stmt->execute([$personnel_id]);
    $department_id = $dept_stmt->fetchColumn();

    // Fetch co-personnel in the same department
    $co_personnel_query = $pdo->prepare("
        SELECT 
            lp.id,
            lp.first_name,
            lp.middle_name,
            lp.last_name,
            a.email,
            lp.created_at,
            lp.created_by_personnel_id,
            creator.first_name as creator_first_name,
            creator.last_name as creator_last_name
        FROM lgu_personnel lp
        JOIN auth a ON lp.auth_id = a.id
        LEFT JOIN lgu_personnel creator ON lp.created_by_personnel_id = creator.id
        WHERE lp.department_id = ? 
        AND lp.id != ?
        AND lp.is_department_head = 0
        ORDER BY lp.created_at DESC
    ");
    $co_personnel_query->execute([$department_id, $personnel_id]);
    $co_personnel_list = $co_personnel_query->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $co_personnel_list,
        'count' => count($co_personnel_list)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>