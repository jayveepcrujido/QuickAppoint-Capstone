<?php
// ajax_update_department.php
include 'conn.php';
header('Content-Type: application/json');

// Validate POST
$deptId = $_POST['department_id'] ?? null;
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

$serviceIds = $_POST['service_ids'] ?? [];
$serviceNames = $_POST['service_names'] ?? [];
$requirementsMap = $_POST['requirements'] ?? [];

if (!$deptId || !$name || empty($serviceNames)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing data.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Update department
    $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $description, $deptId]);

    // Update services
    foreach ($serviceNames as $index => $serviceName) {
        $serviceId = $serviceIds[$index];

        if ($serviceId === 'new') {
            // New service
            $stmt = $pdo->prepare("INSERT INTO department_services (department_id, service_name) VALUES (?, ?)");
            $stmt->execute([$deptId, trim($serviceName)]);
            $newServiceId = $pdo->lastInsertId();

            // Add new requirements
            if (!empty($requirementsMap["new_{$index}"])) {
                foreach ($requirementsMap["new_{$index}"] as $req) {
                    if (trim($req) !== '') {
                        $stmt = $pdo->prepare("INSERT INTO service_requirements (service_id, requirement) VALUES (?, ?)");
                        $stmt->execute([$newServiceId, trim($req)]);
                    }
                }
            }
        } else {
            // Existing service
            $stmt = $pdo->prepare("UPDATE department_services SET service_name = ? WHERE id = ?");
            $stmt->execute([trim($serviceName), $serviceId]);

            // Delete old requirements
            $stmt = $pdo->prepare("DELETE FROM service_requirements WHERE service_id = ?");
            $stmt->execute([$serviceId]);

            // Insert new requirements
            if (!empty($requirementsMap[$serviceId])) {
                foreach ($requirementsMap[$serviceId] as $req) {
                    if (trim($req) !== '') {
                        $stmt = $pdo->prepare("INSERT INTO service_requirements (service_id, requirement) VALUES (?, ?)");
                        $stmt->execute([$serviceId, trim($req)]);
                    }
                }
            }
        }
    }
    

    $pdo->commit();
        echo json_encode(['status' => 'success']);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>