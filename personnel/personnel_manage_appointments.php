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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
    <style>
        .card {
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }
        .card-header {
            border-radius: 12px 12px 0 0 !important;
        }
        .filter-card {
            border-radius: 12px;
        }

        /* ✅ Modal centering and width fix */
        .custom-modal {
            margin: auto !important;   
            max-width: 700px !important; 
        }

        @media (max-width: 768px) {
            .custom-modal {
                max-width: 95% !important; /* responsive for small screens */
            }
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <div class="bg-light border-left border-primary pl-3 py-2 mb-4 shadow-sm">
        <h2 class="text-primary font-weight-bold mb-0">
            <i class="fas fa-calendar-check mr-2"></i> Manage Your Appointments
        </h2>
    </div>

    <!-- Filters Section -->
    <div class="card p-3 mb-4 shadow-sm filter-card">
        <div class="row">
            <!-- Search -->
            <div class="col-md-5 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    </div>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search appointments...">
                </div>
            </div>

            <!-- Service Dropdown -->
            <div class="col-md-5 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-cogs"></i></span>
                    </div>
                    <select id="serviceFilter" class="form-control">
                        <option value="">All Services</option>
                        <?php foreach ($services as $srv): ?>
                            <?php if (!empty($srv['service_name'])): ?>
                                <option value="<?= htmlspecialchars($srv['service_name']) ?>"><?= htmlspecialchars($srv['service_name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Clear Button -->
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-danger w-100" id="clearFilters">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <div id="message-box"></div>

    <!-- Appointment Cards -->
    <div class="row row-cols-1 row-cols-md-3 g-4" id="appointments-container">
        <?php if (!empty($appointmentData)): ?>
            <?php foreach ($appointmentData as $app): ?>
                <div class="col mb-4 appointment-card">
                    <div class="card shadow-sm h-100 border-0" data-toggle="modal" data-target="#viewModal<?= $app['id'] ?>">
                        <div class="card-header bg-primary text-white">
                            <strong>Transaction #<?= $app['id'] ?></strong>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-user text-muted"></i> 
                                <strong>Resident:</strong> <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>
                            </p>
                            <p>
                                <span class="badge badge-info">
                                    <i class="fas fa-cogs"></i> <?= htmlspecialchars($app['service_name'] ?? 'N/A') ?>
                                </span>
                            </p>
                            <p><i class="fas fa-comment-dots text-muted"></i> 
                                <strong>Reason:</strong> <?= htmlspecialchars($app['reason']) ?>
                            </p>
                            <p><i class="fas fa-calendar-alt text-muted"></i> 
                                <strong>Scheduled:</strong> <?= $app['scheduled_for'] ?? 'N/A' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Appointment Detail Modal -->
                <div class="modal fade" id="viewModal<?= $app['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-lg custom-modal">
                        <div class="modal-content shadow">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Appointment Details - Transaction #<?= $app['id'] ?></h5>
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
                                        <img src="<?= (strpos($app['valid_id_path'], 'uploads/') === 0) ? htmlspecialchars($app['valid_id_path']) : 'uploads/' . htmlspecialchars($app['valid_id_path']) ?>" 
                                             alt="Valid ID" class="img-thumbnail clickable-id" style="max-width: 100%; cursor: pointer;" 
                                             data-toggle="modal" data-target="#fullImageModal" 
                                             data-img-src="<?= (strpos($app['valid_id_path'], 'uploads/') === 0) ? htmlspecialchars($app['valid_id_path']) : 'uploads/' . htmlspecialchars($app['valid_id_path']) ?>">
                                    </div>
                                </div>
                                <div class="text-right mt-4">
                                    <button class="btn btn-success complete-btn" data-id="<?= $app['id'] ?>" data-dismiss="modal">Mark as Completed</button>
                                    <button class="btn btn-danger delete-btn" data-id="<?= $app['id'] ?>" data-dismiss="modal">Delete Appointment</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted">No appointments found.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Full Image Modal -->
<div class="modal fade" id="fullImageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered custom-modal">
        <div class="modal-content shadow">
            <div class="modal-body text-center p-3">
                <img src="" alt="Full ID Image" id="fullImage" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    function applyFilters() {
        const searchVal = $('#searchInput').val().toLowerCase();
        const selectedService = $('#serviceFilter').val().toLowerCase();

        $('.appointment-card').each(function () {
            const text = $(this).text().toLowerCase();
            const serviceText = $(this).find('.badge-info').text().toLowerCase();

            const matchesSearch = text.includes(searchVal);
            const matchesService = selectedService === "" || serviceText.includes(selectedService);

            $(this).toggle(matchesSearch && matchesService);
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
