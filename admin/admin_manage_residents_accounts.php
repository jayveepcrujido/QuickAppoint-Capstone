<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';

// Handle delete request
if (isset($_POST['delete_id'])) {
    $deleteId = $_POST['delete_id'];

    // Delete auth first (residents.auth_id references auth.id, ON DELETE CASCADE will remove resident)
    $stmt = $pdo->prepare("SELECT auth_id FROM residents WHERE id = ?");
    $stmt->execute([$deleteId]);
    $authId = $stmt->fetchColumn();

    if ($authId) {
        $pdo->prepare("DELETE FROM auth WHERE id = ?")->execute([$authId]);
    }

    echo json_encode(['status' => 'success']);
    exit();
}

// Get all residents with their auth info
$stmt = $pdo->prepare("
    SELECT r.id, r.first_name, r.middle_name, r.last_name, r.created_at, a.email
    FROM residents r
    JOIN auth a ON r.auth_id = a.id
    WHERE a.role = 'Resident'
    ORDER BY r.created_at DESC
");
$stmt->execute();
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Resident Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.2); cursor: pointer; }
        .modal-full-img .modal-dialog { max-width: 600px; }
        .modal-full-img img { width: 100%; height: auto; }
    </style>
</head>
<body class="p-4">
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class='bx bx-group'></i> Manage Resident Accounts</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Date Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($residents)): ?>
                            <?php foreach ($residents as $resident): ?>
                                <tr>
                                    <td><?= htmlspecialchars($resident['first_name'] . ' ' . $resident['middle_name'] . ' ' . $resident['last_name']) ?></td>
                                    <td><?= htmlspecialchars($resident['email']) ?></td>
                                    <td><?= date('F j, Y - g:i A', strtotime($resident['created_at'])) ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteResident(<?= $resident['id'] ?>)">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted">No resident accounts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function deleteResident(id) {
    if (confirm('Are you sure you want to delete this resident account?')) {
        $.post('admin_manage_residents_accounts.php', { delete_id: id }, function(response) {
            if (response.status === 'success') {
                alert('Resident account deleted successfully.');
                location.reload();
            } else {
                alert('Failed to delete resident account.');
            }
        }, 'json');
    }
}
</script>
</body>
</html>
