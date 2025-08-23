<?php
// ajax_add_department_with_services.php
include 'conn.php';

$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$services = $_POST['services'] ?? [];
$requirements = $_POST['requirements'] ?? [];

if (!$name || empty($services)) {
    http_response_code(400);
    echo "Department name and at least one service are required.";
    exit();
}

try {
    $pdo->beginTransaction();

    // Insert department
    $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);
    $deptId = $pdo->lastInsertId();

    // Insert services and associated requirements
    $svcStmt = $pdo->prepare("INSERT INTO department_services (department_id, service_name) VALUES (?, ?)");
    $reqStmt = $pdo->prepare("INSERT INTO service_requirements (service_id, requirement) VALUES (?, ?)");

    $reqIndex = 0;
    foreach ($services as $service) {
        if (trim($service) !== '') {
            $svcStmt->execute([$deptId, trim($service)]);
            $serviceId = $pdo->lastInsertId();

            // Insert corresponding requirements if any
            while ($reqIndex < count($requirements) && trim($requirements[$reqIndex]) !== '') {
                $reqStmt->execute([$serviceId, trim($requirements[$reqIndex])]);
                $reqIndex++;

                // If the next service starts, break to continue outer loop
                if (isset($services[$reqIndex])) break;
            }
        }
    }

    $pdo->commit();
    echo "Success";
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Database error: " . $e->getMessage();
}
?>