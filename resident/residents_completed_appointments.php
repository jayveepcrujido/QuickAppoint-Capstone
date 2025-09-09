<?php 
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';
$authId = $_SESSION['auth_id'];

// ✅ Resolve resident_id from auth_id
$stmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ? LIMIT 1");
$stmt->execute([$authId]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    die("Resident profile not found.");
}
$residentId = $resident['id'];

// ✅ Fetch completed appointments
$queryCompleted = "
    SELECT a.id, a.scheduled_for, d.name AS department_name, s.service_name
    FROM appointments a
    JOIN departments d ON a.department_id = d.id
    JOIN department_services s ON a.service_id = s.id
    WHERE a.resident_id = :resident_id AND a.status = 'Completed'
    ORDER BY a.scheduled_for DESC
";
$stmtCompleted = $pdo->prepare($queryCompleted);
$stmtCompleted->execute(['resident_id' => $residentId]);
$completedAppointments = $stmtCompleted->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Completed Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container mt-4">
    <div class="">
        <!-- Completed Appointments Section -->
        <div class="container bg-light p-4 shadow-sm border-rounded mt-5">
            <div class="d-flex align-items-center mb-3">
                <i class="fas fa-check-circle fa-lg text-success mr-2"></i>
                <h4 class="mb-0 text-success font-weight-bold">My Completed Appointments</h4>
            </div>

            <?php if (empty($completedAppointments)): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle mr-2"></i>You have no completed appointments.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mt-2">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Transaction No.</th>
                                <th>Department</th>
                                <th>Service</th>
                                <th>Schedule</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completedAppointments as $index => $appt): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><span class="badge badge-success">#<?= htmlspecialchars($appt['id']) ?></span></td>
                                    <td><?= htmlspecialchars($appt['department_name']) ?></td>
                                    <td><?= htmlspecialchars($appt['service_name']) ?></td>
                                    <td>
                                        <i class="far fa-calendar-check mr-1 text-success"></i>
                                        <?= date('F d, Y h:i A', strtotime($appt['scheduled_for'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
