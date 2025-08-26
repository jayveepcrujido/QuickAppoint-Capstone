<?php
include '../conn.php';
$id = $_POST['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo "Invalid ID.";
    exit;
}

try {
    $pdo->beginTransaction();

    // Get linked auth_id
    $stmt = $pdo->prepare("SELECT auth_id FROM lgu_personnel WHERE id=?");
    $stmt->execute([$id]);
    $authId = $stmt->fetchColumn();

    if ($authId) {
        // Delete personnel first
        $pdo->prepare("DELETE FROM lgu_personnel WHERE id=?")->execute([$id]);
        // Then delete login
        $pdo->prepare("DELETE FROM auth WHERE id=?")->execute([$authId]);
    }

    $pdo->commit();
    echo "Personnel deleted successfully.";
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Database Error: " . $e->getMessage();
}
