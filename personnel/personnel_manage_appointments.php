<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo "<script>alert('Unauthorized access!'); window.location.href='../login.php';</script>";
    exit();
}

// Get the logged-in personnel's department_id using auth_id
$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$department_id = $stmt->fetchColumn();

if (!$department_id) {
    echo "<script>alert('No department assigned to this personnel!'); window.location.href='../login.php';</script>";
    exit();
}

// Get pending appointments with resident + service info
$query = "
    SELECT 
        a.*, 
        r.first_name, r.middle_name, r.last_name, r.address, r.birthday, r.age, r.sex, r.civil_status, 
        r.valid_id_image, r.selfie_image,
        au.email, 
        ds.service_name
    FROM appointments a
    JOIN residents r ON a.resident_id = r.id
    JOIN auth au ON r.auth_id = au.id
    LEFT JOIN department_services ds ON a.service_id = ds.id
    WHERE a.department_id = ? AND a.status = 'Pending'
    ORDER BY a.scheduled_for ASC
";
$appointments = $pdo->prepare($query);
$appointments->execute([$department_id]);
$appointmentData = $appointments->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique services for filter dropdown
$serviceQuery = $pdo->prepare("SELECT DISTINCT ds.service_name 
                               FROM appointments a
                               LEFT JOIN department_services ds ON a.service_id = ds.id
                               WHERE a.department_id = ?");
$serviceQuery->execute([$department_id]);
$services = $serviceQuery->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background-color: #f8f9fc; }
        .page-title {
            border-left: 5px solid #007bff;
            padding-left: 15px;
        }
        .card {
            border: none;
            border-radius: 1rem;
            transition: all 0.2s ease-in-out;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }
        .modal-content {
            border-radius: 1rem;
        }
        .btn-action {
            min-width: 160px;
        }
        .filter-box {
            background: #fff;
            border-radius: .75rem;
            padding: 1rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <div class="mb-4">
        <h2 class="text-primary font-weight-bold page-title">
            <i class="fas fa-calendar-check mr-2"></i> Manage Appointments
        </h2>
    </div>

    <!-- Filters -->
    <div class="filter-box mb-4">
        <div class="row">
            <div class="col-md-6 mb-2 mb-md-0">
                <input type="text" class="form-control shadow-sm" id="searchInput" placeholder="🔍 Search by name, service, or reason...">
            </div>
            <div class="col-md-4 mb-2 mb-md-0">
                <select id="serviceFilter" class="form-control shadow-sm">
                    <option value="">Filter by Service</option>
                    <?php foreach ($services as $srv): ?>
                        <?php if (!empty($srv['service_name'])): ?>
                            <option value="<?= htmlspecialchars(strtolower($srv['service_name'])) ?>"><?= htmlspecialchars($srv['service_name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 text-md-right">
                <button class="btn btn-outline-secondary w-100" id="clearFilters"><i class="fas fa-eraser mr-1"></i> Clear</button>
            </div>
        </div>
    </div>

    <div class="row" id="appointments-container">
        <?php if (!empty($appointmentData)): ?>
            <?php foreach ($appointmentData as $app): ?>
                <div class="col-md-6 col-lg-4 mb-4 appointment-card">
                    <div class="card h-100"
                         data-toggle="modal"
                         data-target="#viewModal<?= $app['id'] ?>"
                         data-service="<?= htmlspecialchars(strtolower($app['service_name'] ?? '')) ?>">
                        <div class="card-body">
                            <h5 class="card-title text-primary">
                                #<?= $app['id'] ?> - <?= htmlspecialchars($app['service_name'] ?? 'N/A') ?>
                            </h5>
                            <p class="mb-1"><i class="fas fa-user mr-1 text-muted"></i> <strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong></p>
                            <p class="mb-1 text-truncate"><i class="fas fa-sticky-note mr-1 text-muted"></i> <?= htmlspecialchars($app['reason']) ?></p>
                            <p class="mb-0"><i class="fas fa-calendar-day mr-1 text-muted"></i> <?= $app['scheduled_for'] ?? 'N/A' ?></p>
                        </div>
                    </div>
                </div>

                <!-- Appointment Detail Modal -->
                <div class="modal fade" id="viewModal<?= $app['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content shadow">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i> Appointment #<?= $app['id'] ?></h5>
                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Full Name:</strong> <?= htmlspecialchars($app['first_name'] . ' ' . $app['middle_name'] . ' ' . $app['last_name']) ?></p>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($app['email']) ?></p>
                                        <p><strong>Address:</strong> <?= htmlspecialchars($app['address']) ?></p>
                                        <p><strong>Birthday:</strong> <?= htmlspecialchars($app['birthday']) ?></p>
                                        <p><strong>Age:</strong> <?= htmlspecialchars($app['age']) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Sex:</strong> <?= htmlspecialchars($app['sex']) ?></p>
                                        <p><strong>Civil Status:</strong> <?= htmlspecialchars($app['civil_status']) ?></p>
                                        <p><strong>Service:</strong> <?= htmlspecialchars($app['service_name'] ?? 'N/A') ?></p>
                                        <p><strong>Reason:</strong> <?= htmlspecialchars($app['reason']) ?></p>
                                        <p><strong>Valid ID:</strong></p>
                                        <img src="<?= htmlspecialchars($app['valid_id_image']) ?>" 
                                             alt="Valid ID" class="img-thumbnail clickable-id" 
                                             style="max-width: 100%; cursor: zoom-in;"
                                             data-toggle="modal" data-target="#fullImageModal" 
                                             data-img-src="<?= htmlspecialchars($app['valid_id_image']) ?>">
                                    </div>
                                </div>
                                <div class="text-right mt-4">
                                    <button class="btn btn-success btn-action complete-btn" data-id="<?= $app['id'] ?>" data-dismiss="modal">
                                        <i class="fas fa-check mr-1"></i> Mark as Completed
                                    </button>
                                    <button class="btn btn-danger btn-action delete-btn" data-id="<?= $app['id'] ?>" data-dismiss="modal">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                No appointments found.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Full Image Modal -->
<div class="modal fade" id="fullImageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-body text-center p-3">
                <img src="" alt="Full ID Image" id="fullImage" class="img-fluid rounded shadow-sm">
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    function applyFilters() {
        const searchVal = $('#searchInput').val().toLowerCase();
        const selectedService = $('#serviceFilter').val().toLowerCase();

        $('.appointment-card .card').each(function () {
            const text = $(this).text().toLowerCase();
            const service = $(this).data('service');

            const matchesSearch = text.includes(searchVal);
            const matchesService = selectedService === "" || service === selectedService;

            $(this).closest('.appointment-card').toggle(matchesSearch && matchesService);
        });
    }

    $(document).on('click', '.complete-btn', function () {
        const id = $(this).data('id');
        $.post('complete_appointment.php', { appointment_id: id }, function (res) {
            const r = JSON.parse(res);
            if (r.success) location.reload();
        });
    });

    $(document).on('click', '.delete-btn', function () {
        const id = $(this).data('id');
        $.post('delete_appointment.php', { appointment_id: id }, function (res) {
            const r = JSON.parse(res);
            if (r.success) location.reload();
        });
    });

    $('#searchInput').on('input', applyFilters);
    $('#serviceFilter').on('change', applyFilters);

    $('#clearFilters').click(function () {
        $('#searchInput').val('');
        $('#serviceFilter').val('');
        $('.appointment-card').show();
    });

    // Full image modal
    $('.clickable-id').on('click', function () {
        const src = $(this).data('img-src');
        $('#fullImage').attr('src', src);
    });

    $('#fullImageModal').on('hidden.bs.modal', function () {
        $('body').addClass('modal-open');
    });
});
</script>
</body>
</html>
