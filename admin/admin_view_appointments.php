<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';

// Fetch all departments for the dropdown
$departments = $pdo->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_ASSOC);

// Fetch 10 most recent appointments initially
$query = "
    SELECT a.id, a.status, a.scheduled_for, a.reason, a.requested_at,
           r.first_name, r.last_name,
           d.name AS department_name
    FROM appointments a
    JOIN residents r ON a.resident_id = r.id
    JOIN departments d ON a.department_id = d.id
    ORDER BY r.last_name ASC, a.scheduled_for ASC
    LIMIT 10
";
$recentAppointments = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - View Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="p-4">
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class='bx bx-calendar-event'></i> View Appointments</h5>
            <div class="form-inline">
                <label for="departmentFilter" class="me-2">Filter by Department:</label>
                <select id="departmentFilter" class="form-select form-select-sm" style="width: 200px;" onchange="filterAppointments()">
                    <option value="">-- All Departments --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <th>Resident Name</th>
                            <th>Department</th>
                            <th>Reason</th>
                            <th>Scheduled For</th>
                            <th>Status</th>
                            <th>Requested At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentAppointments)): ?>
                            <?php foreach ($recentAppointments as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                                    <td><?= htmlspecialchars($row['reason'] ?? 'N/A') ?></td>
                                    <td><?= date('F j, Y • g:i A', strtotime($row['scheduled_for'] ?? '')) ?></td>
                                    <td class="text-center">
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
                            <tr><td colspan="6" class="text-center text-muted">No recent appointments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<script>
    function filterAppointments() {
        const departmentId = $('#departmentFilter').val();
        $.ajax({
            url: 'ajax_get_appointments_by_department.php',
            method: 'GET',
            data: { department_id: departmentId },
            success: function (data) {
                let html = `
                    <table class="table table-bordered mt-4">
                        <thead>
                            <tr>
                                <th>Resident Name</th>
                                <th>Department</th>
                                <th>Reason</th>
                                <th>Scheduled For</th>
                                <th>Status</th>
                                <th>Requested At</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                if (data.length > 0) {
                    data.forEach(row => {
                        html += `
                            <tr>
                                <td>${row.first_name} ${row.last_name}</td>
                                <td>${row.department_name}</td>
                                <td>${row.reason || 'N/A'}</td>
                                <td>${row.scheduled_for || 'N/A'}</td>
                                <td>${row.status}</td>
                                <td>${row.requested_at}</td>
                            </tr>
                        `;
                    });
                } else {
                    html += `<tr><td colspan="6" class="text-center">No appointments found.</td></tr>`;
                }

                html += '</tbody></table>';
                $('#appointmentsTable').html(html);
            },
            error: function () {
                alert('Failed to fetch appointments.');
            }
        });
    }
</script>
</body>
</html>
