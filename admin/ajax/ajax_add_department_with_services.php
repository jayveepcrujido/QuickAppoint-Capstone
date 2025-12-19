<?php
// ajax/ajax_add_department_with_services.php
include '../../conn.php';

// 1. Set Header to JSON so JavaScript can parse it correctly
header('Content-Type: application/json');

$name = $_POST['name'] ?? '';
$acronym = $_POST['acronym'] ?? null;
$description = $_POST['description'] ?? '';
$services = $_POST['services'] ?? [];
$requirements = $_POST['requirements'] ?? [];

// Basic Validation
if (!$name || empty($services)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Department name and at least one service are required.'
    ]);
    exit();
}

try {
    $pdo->beginTransaction();

    // Insert department
    $stmt = $pdo->prepare("INSERT INTO departments (name, acronym, description) VALUES (?, ?, ?)");
    $stmt->execute([$name, $acronym, $description]);
    $deptId = $pdo->lastInsertId();

    // Prepare statements for services and requirements
    $svcStmt = $pdo->prepare("INSERT INTO department_services (department_id, service_name) VALUES (?, ?)");
    $reqStmt = $pdo->prepare("INSERT INTO service_requirements (service_id, requirement) VALUES (?, ?)");

    // Logic to map services to requirements
    // Note: This relies on the input arrays being indexed in order. 
    // Since your form structure sends flat arrays, we iterate carefully.
    
    $reqIndex = 0; // Pointer for the requirements array
    
    foreach ($services as $index => $service) {
        if (trim($service) !== '') {
            // Insert Service
            $svcStmt->execute([$deptId, trim($service)]);
            $serviceId = $pdo->lastInsertId();

            // Insert Requirement (Assumes 1 requirement field per service based on your HTML form)
            if (isset($requirements[$index]) && trim($requirements[$index]) !== '') {
                $reqStmt->execute([$serviceId, trim($requirements[$index])]);
            }
        }
    }

    $pdo->commit();

    // 2. Return Success JSON
    echo json_encode([
        'status' => 'success',
        'message' => 'Department added successfully!'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // 3. Return Error JSON
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>