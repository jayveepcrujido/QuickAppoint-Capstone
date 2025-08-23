<?php
include 'conn.php';
$departments = $pdo->query("SELECT id, name FROM departments")->fetchAll();

$filter = $_GET['department_id'] ?? '';
$sql = "SELECT cp.*, u.first_name, u.last_name
        FROM complaints cp
        JOIN users u ON cp.user_id = u.id";
if ($filter) {
    $sql .= " WHERE u.department_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filter]);
} else {
    $stmt = $pdo->query($sql);
}
$complaints = $stmt->fetchAll();
?>

<div class="container">
    <div class="card shadow mb-3">
        <div class="card-header bg-danger text-white">
            <i class='bx bx-error'></i> System Complaints
        </div>
        <div class="card-body">
            <form method="GET" onChange="this.submit()" class="mb-3">
                <label>Filter by Department:</label>
                <select name="department_id" class="form-control">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $filter == $dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php foreach ($complaints as $cp): ?>
                <div class="card mb-3 border-left-danger">
                    <div class="card-body">
                        <h5><i class='bx bx-user-voice'></i> <?= htmlspecialchars($cp['first_name'] . ' ' . $cp['last_name']) ?></h5>
                        <p><strong>Employee:</strong> <?= htmlspecialchars($cp['employee_name']) ?></p>
                        <p><strong>Office:</strong> <?= htmlspecialchars($cp['office']) ?></p>
                        <p><strong>Complaint Type:</strong> <?= htmlspecialchars($cp['complaint_type']) ?></p>
                        <?php if ($cp['additional_details']): ?>
                            <p><strong>Additional Info:</strong> <?= nl2br(htmlspecialchars($cp['additional_details'])) ?></p>
                        <?php endif; ?>
                        <small class="text-muted"><i class='bx bx-calendar'></i> <?= $cp['created_at'] ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
