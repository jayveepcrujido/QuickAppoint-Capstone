<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    header("Location: login.php");
    exit();
}

include 'conn.php';

$personnelId = $_SESSION['user_id'];

// Get personnel department
$stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
$stmt->execute([$personnelId]);
$personnel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personnel) {
    echo "<div class='alert alert-danger'>Invalid user.</div>";
    exit();
}

$departmentId = $personnel['department_id'];

// Fetch appointments with status
$stmt = $pdo->prepare("
    SELECT a.id, a.status, a.scheduled_for, a.reason, a.requested_at,
           u.first_name, u.last_name
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.department_id = ?
    ORDER BY a.scheduled_for DESC
");
$stmt->execute([$departmentId]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Completed Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Department Appointments</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light text-center">
                        <tr>
                            <th>Resident Name</th>
                            <th>Reason</th>
                            <th>Scheduled For</th>
                            <th>Status</th>
                            <th>Requested At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($appointments)): ?>
                            <?php foreach ($appointments as $row): ?>
                                <tr class="text-center">
                                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><?= htmlspecialchars($row['reason'] ?? 'N/A') ?></td>
                                    <td><?= date('F j, Y • g:i A', strtotime($row['scheduled_for'] ?? '')) ?></td>
                                    <td>
                                        <?php
                                            $status = htmlspecialchars($row['status']);
                                            $badgeClass = match($status) {
                                                'Pending' => 'warning',
                                                'Approved' => 'primary',
                                                'Declined' => 'danger',
                                                'Completed' => 'success',
                                                default => 'secondary'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?>"><?= $status ?></span>
                                    </td>
                                    <td><?= date('M d, Y g:i A', strtotime($row['requested_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted">No appointments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>