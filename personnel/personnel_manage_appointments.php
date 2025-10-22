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
        a.id, a.transaction_id, a.status, a.reason, a.scheduled_for, a.requested_at,
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 2rem 0;
        }

        .page-header {
            margin-top: -2rem;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); 
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .page-header h2 {
            color: white;
            font-weight: 700;
            margin: 0;
            font-size: 2rem;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0.5rem 0 0 0;
            position: relative;
            z-index: 1;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .filter-section .form-control {
            height: auto !important;
            padding-top: 0.6rem !important;
            padding-bottom: 0.6rem !important;
            line-height: 1.4 !important;
            font-size: 1rem;
            background-color: #fff;
            color: #2c3e50;
            appearance: none; /* optional: removes browser default arrow style */
        }
        select.form-control {
            height: auto !important;
            padding-top: 0.6rem !important;
            padding-bottom: 0.6rem !important;
            line-height: 1.4 !important;
            font-size: 1rem;
            background-color: #fff;
            color: #2c3e50;
            appearance: none; /* optional: removes browser default arrow style */
        }

        .filter-section .form-control:focus {
            border-color: #2c3e50;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .filter-section .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filter-section .btn-outline-secondary {
            border: 2px solid #e0e6ed;
        }

        .filter-section .btn-outline-secondary:hover {
            background: #3498db;
            border-color: #3498db;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .appointment-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .appointment-card .card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            background: white;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
        }

        .appointment-card .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.2);
        }

        .appointment-card .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 1rem 1.25rem;
            border: none;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .appointment-card .card-body {
            padding: 1.5rem;
        }

        .appointment-card .card-title {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointment-card .card-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            border-radius: 2px;
        }

        .appointment-card .card-body p {
            margin-bottom: 0.75rem;
            color: #4a5568;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointment-card .card-body i {
            color: #2c3e50;
            width: 20px;
            text-align: center;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 1.5rem;
            border: none;
        }

        .modal-header .modal-title {
            font-weight: 700;
            font-size: 1.25rem;
        }

        .modal-header .close {
            color: white;
            opacity: 1;
            font-size: 1.5rem;
            text-shadow: none;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-body p {
            margin-bottom: 1rem;
            color: #4a5568;
            line-height: 1.6;
        }

        .modal-body strong {
            color: #2d3748;
            font-weight: 600;
            display: inline-block;
            min-width: 120px;
        }

        .img-thumbnail {
            border: 3px solid #e0e6ed;
            border-radius: 12px;
            padding: 0.5rem;
            transition: all 0.3s ease;
            background: white;
        }

        .img-thumbnail:hover {
            border-color: #3498db; #2c3e50 0%, #3498db
            transform: scale(1.02);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }

        .btn-action {
            min-width: 160px;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(72, 187, 120, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 101, 101, 0.4);
        }

        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 5rem;
            color: #cbd5e0;
            margin-bottom: 1.5rem;
        }

        .empty-state p {
            color: #a0aec0;
            font-size: 1.2rem;
            margin: 0;
        }

        #fullImageModal .modal-body {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem;
        }

        #fullImageModal .modal-content {
            background: transparent;
            border: none;
        }

        #fullImage {
            border-radius: 10px;
            max-height: 80vh;
            object-fit: contain;
        }

        .info-section {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-section h6 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 768px) {
            .page-header h2 {
                font-size: 1.5rem;
            }

            .btn-action {
                min-width: 100%;
                margin-bottom: 0.5rem;
            }

            .modal-body {
                padding: 1.5rem;
            }
        }

        /* Loading animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .appointment-card {
            animation: fadeIn 0.5s ease forwards;
        }

        .appointment-card:nth-child(1) { animation-delay: 0.1s; }
        .appointment-card:nth-child(2) { animation-delay: 0.2s; }
        .appointment-card:nth-child(3) { animation-delay: 0.3s; }
        .appointment-card:nth-child(4) { animation-delay: 0.4s; }
        .appointment-card:nth-child(5) { animation-delay: 0.5s; }
        .appointment-card:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h2><i class="fas fa-calendar-check mr-2"></i> Manage Appointments</h2>
        <p>Review and process pending appointment requests</p>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <div class="row">
            <div class="col-md-6 mb-3 mb-md-0">
                <input type="text" class="form-control" id="searchInput" placeholder="🔍 Search by name, service, or reason...">
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <select id="serviceFilter" class="form-control">
                    <option value="">All Services</option>
                    <?php foreach ($services as $srv): ?>
                        <?php if (!empty($srv['service_name'])): ?>
                            <option value="<?= htmlspecialchars(strtolower($srv['service_name'])) ?>"><?= htmlspecialchars($srv['service_name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" id="clearFilters">
                    <i class="fas fa-eraser mr-1"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <div class="row" id="appointments-container">
        <?php if (!empty($appointmentData)): ?>
            <?php foreach ($appointmentData as $app): ?>
                <div class="col-12 col-md-6 col-lg-4 mb-4 appointment-card" data-service="<?= strtolower($app['service_name'] ?? '') ?>">
                    <div class="card" data-toggle="modal" data-target="#viewModal<?= $app['id'] ?>">
                        <div class="card-header">
                            <i class="fas fa-hashtag mr-1"></i> <?= $app['transaction_id'] ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">
                                <?= htmlspecialchars($app['service_name'] ?? 'N/A') ?>
                            </h5>
                            <p>
                                <i class="fas fa-user"></i>
                                <strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong>
                            </p>
                            <p class="text-truncate">
                                <i class="fas fa-sticky-note"></i>
                                <span><?= htmlspecialchars($app['reason']) ?></span>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-calendar-day"></i>
                                <span><?= $app['scheduled_for'] ? date('M j, Y • g:i A', strtotime($app['scheduled_for'])) : 'Not Scheduled' ?></span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Appointment Detail Modal -->
                <div class="modal fade" id="viewModal<?= $app['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <?= $app['transaction_id'] ?>
                                </h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="info-section">
                                    <h6><i class="fas fa-user-circle mr-2"></i>Personal Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Full Name:</strong> <?= htmlspecialchars($app['first_name'] . ' ' . $app['middle_name'] . ' ' . $app['last_name']) ?></p>
                                            <p><strong>Email:</strong> <?= htmlspecialchars($app['email']) ?></p>
                                            <p><strong>Address:</strong> <?= htmlspecialchars($app['address']) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Birthday:</strong> <?= htmlspecialchars($app['birthday']) ?></p>
                                            <p><strong>Age:</strong> <?= htmlspecialchars($app['age']) ?> years old</p>
                                            <p><strong>Sex:</strong> <?= htmlspecialchars($app['sex']) ?></p>
                                            <p><strong>Civil Status:</strong> <?= htmlspecialchars($app['civil_status']) ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="info-section">
                                    <h6><i class="fas fa-clipboard-list mr-2"></i>Appointment Details</h6>
                                    <p><strong>Service:</strong> <?= htmlspecialchars($app['service_name'] ?? 'N/A') ?></p>
                                    <p><strong>Reason:</strong> <?= htmlspecialchars($app['reason']) ?></p>
                                    <p><strong>Scheduled:</strong> <?= $app['scheduled_for'] ? date('F j, Y • g:i A', strtotime($app['scheduled_for'])) : 'Not Scheduled' ?></p>
                                    <p><strong>Requested:</strong> <?= date('F j, Y • g:i A', strtotime($app['requested_at'])) ?></p>
                                </div>

                                <div class="info-section">
                                    <h6><i class="fas fa-id-card mr-2"></i>Valid ID</h6>
                                    <img src="<?= htmlspecialchars($app['valid_id_image']) ?>" 
                                         alt="Valid ID" class="img-thumbnail clickable-id w-100" 
                                         style="cursor: zoom-in;"
                                         data-toggle="modal" data-target="#fullImageModal" 
                                         data-img-src="<?= htmlspecialchars($app['valid_id_image']) ?>">
                                </div>

                                <div class="text-right mt-4">
                                    <button class="btn btn-success btn-action complete-btn" data-id="<?= $app['id'] ?>" data-dismiss="modal">
                                        <i class="fas fa-check-circle mr-2"></i> Mark as Completed
                                    </button>
                                    <button class="btn btn-danger btn-action delete-btn" data-id="<?= $app['id'] ?>" data-dismiss="modal">
                                        <i class="fas fa-trash-alt mr-2"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No pending appointments at the moment</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Full Image Modal -->
<div class="modal fade" id="fullImageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-body p-0">
                <img src="" alt="Full ID Image" id="fullImage" class="w-100">
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
            const service = $(this).data('service');

            const matchesSearch = text.includes(searchVal);
            const matchesService = selectedService === "" || service === selectedService;

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
        if (confirm('Are you sure you want to delete this appointment?')) {
            $.post('delete_appointment.php', { appointment_id: id }, function (res) {
                const r = JSON.parse(res);
                if (r.success) location.reload();
            });
        }
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