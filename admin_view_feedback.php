<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

include 'conn.php';

// Admin’s auth_id is available:
$authId = $_SESSION['auth_id'];

// Fetch departments for filter dropdown
$departments = $pdo->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_ASSOC);

// Get filter if applied
$filter = $_GET['department_id'] ?? '';

$sql = "
    SELECT f.*, d.name AS department_name,
           r.first_name, r.last_name
    FROM feedback f
    JOIN appointments a ON f.appointment_id = a.id
    JOIN departments d ON a.department_id = d.id
    JOIN residents r ON f.resident_id = r.id
";
if ($filter) {
    $sql .= " WHERE d.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filter]);
} else {
    $stmt = $pdo->query($sql);
}
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="card shadow mb-3">
        <div class="card-header bg-info text-white">
            <i class='bx bx-message-square-detail'></i> Service Feedback
        </div>
        <div class="card-body">
            <form id="filterForm" class="form-inline mb-3">
                <label class="mr-2">Filter by Department:</label>
                <select name="department_id" id="department_id" class="form-control">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $filter == $dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if (count($feedbacks) > 0): ?>
                <?php foreach ($feedbacks as $f): ?>
                    <div class="card mb-3 border-left-info">
                        <div class="card-body">
                            <h5><i class='bx bx-user-circle'></i>
                                <?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?>
                            </h5>
                            <p><strong>Department:</strong> <?= htmlspecialchars($f['department_name']) ?></p>
                            <?php if (!empty($f['attending_employee_name'])): ?>
                                <p><strong>Attending Employee:</strong> <?= htmlspecialchars($f['attending_employee_name']) ?></p>
                            <?php endif; ?>
                            <p><strong>Feedback:</strong> <?= nl2br(htmlspecialchars($f['feedback'])) ?></p>
                            <?php if ($f['comments']): ?>
                                <p><strong>Additional Comments:</strong> <?= nl2br(htmlspecialchars($f['comments'])) ?></p>
                            <?php endif; ?>
                            <small class="text-muted">
                                <i class='bx bx-calendar'></i> <?= htmlspecialchars($f['created_at']) ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class='bx bx-info-circle'></i> No feedback found for the selected department.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('filterForm').addEventListener('change', function () {
    const selectedDeptId = document.getElementById('department_id').value;
    const url = 'admin_view_feedback.php?department_id=' + encodeURIComponent(selectedDeptId);
    loadContent(url); // assuming you already have loadContent() function for AJAX partials
});
</script>
