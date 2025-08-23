<?php
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId = $_POST['id'] ?? null;

    if (!$deptId) {
        http_response_code(400);
        echo "Missing department ID.";
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Get all service IDs under the department
        $stmt = $pdo->prepare("SELECT id FROM department_services WHERE department_id = ?");
        $stmt->execute([$deptId]);
        $serviceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Delete requirements tied to those services
        if ($serviceIds) {
            $in = implode(',', array_fill(0, count($serviceIds), '?'));
            $pdo->prepare("DELETE FROM service_requirements WHERE service_id IN ($in)")
                ->execute($serviceIds);
        }

        // Delete services
        $pdo->prepare("DELETE FROM department_services WHERE department_id = ?")->execute([$deptId]);

        // Delete department
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$deptId]);

        $pdo->commit();
        echo "Deleted";
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo "Database error: " . $e->getMessage();
    }
} else {
    http_response_code(405);
    echo "Invalid request method.";
}
