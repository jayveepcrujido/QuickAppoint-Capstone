<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized');
}

include '../conn.php';

$departmentId = $_GET['department_id'] ?? '';

if ($departmentId !== '') {
    $stmt = $pdo->prepare("
        SELECT f.feedback, f.created_at, u.first_name, u.last_name, d.name AS department_name
        FROM feedback f
        JOIN users u ON f.user_id = u.id
        JOIN appointments a ON f.appointment_id = a.id
        JOIN departments d ON a.department_id = d.id
        WHERE d.id = :department_id
        ORDER BY f.created_at DESC
        LIMIT 10
    ");
    $stmt->execute(['department_id' => $departmentId]);
} else {
    $stmt = $pdo->query("
        SELECT f.feedback, f.created_at, u.first_name, u.last_name, d.name AS department_name
        FROM feedback f
        JOIN users u ON f.user_id = u.id
        JOIN appointments a ON f.appointment_id = a.id
        JOIN departments d ON a.department_id = d.id
        ORDER BY a.scheduled_for ASC
        LIMIT 10
    ");
}

$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<table class="table table-bordered mt-4">
    <thead>
        <tr>
            <th>Resident</th>
            <th>Department</th>
            <th>Feedback</th>
            <th>Submitted At</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($feedbacks)): ?>
        <?php foreach ($feedbacks as $fb): ?>
            <tr>
                <td><?= htmlspecialchars($fb['first_name'] . ' ' . $fb['last_name']) ?></td>
                <td><?= htmlspecialchars($fb['department_name']) ?></td>
                <td><?= htmlspecialchars($fb['feedback']) ?></td>
                <td><?= htmlspecialchars($fb['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="4" class="text-center text-muted">No feedback found for this department.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
