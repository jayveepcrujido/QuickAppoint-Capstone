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
        a.id, a.transaction_id, a.status, a.reason, a.scheduled_for, a.requested_at, a.available_date_id,
        r.first_name, r.middle_name, r.last_name, r.address, r.birthday, r.age, r.sex, r.civil_status,
        r.id_front_image, r.id_back_image, r.selfie_with_id_image,
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
            background: linear-gradient(135deg, #0D92F4, #27548A);
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
            background: linear-gradient(135deg, #0D92F4, #27548A);
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
            background: linear-gradient(135deg, #0D92F4, #27548A);
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
            border-color: #2c3e50 0%, #3498db;
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
        /* Table Styles */
.table-responsive {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    overflow-x: auto;
}

#appointments-table {
    margin-bottom: 0;
    font-size: 0.9rem;
}

#appointments-table thead {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
}

#appointments-table thead th {
    border: none;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem 0.75rem;
    font-size: 0.85rem;
    vertical-align: middle;
    white-space: nowrap;
}

#appointments-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #e0e6ed;
}

#appointments-table tbody tr:hover {
    background-color: #f7fafc;
    transform: scale(1.01);
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.1);
}

#appointments-table tbody td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    color: #4a5568;
}

#appointments-table tbody td strong {
    color: #2d3748;
    font-weight: 600;
}

.badge-primary {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    border: none;
}

.reason-text {
    display: block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}

.view-details-btn {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    color: white;
}

.view-details-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    color: white;
}

.view-details-btn i {
    font-size: 1rem;
}

/* Empty State in Table */
.empty-state {
    padding: 3rem 2rem;
    text-align: center;
}

.empty-state i {
    font-size: 4rem;
    color: #cbd5e0;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #a0aec0;
    font-size: 1.1rem;
    margin: 0;
}

/* Responsive Table */
@media (max-width: 1200px) {
    #appointments-table {
        font-size: 0.85rem;
    }
    
    #appointments-table thead th,
    #appointments-table tbody td {
        padding: 0.75rem 0.5rem;
    }
}

@media (max-width: 992px) {
    .table-responsive {
        padding: 1rem;
    }
    
    #appointments-table {
        font-size: 0.8rem;
    }
    
    #appointments-table thead th {
        font-size: 0.75rem;
        padding: 0.75rem 0.5rem;
    }
    
    .view-details-btn {
        padding: 0.4rem 0.6rem;
        font-size: 0.85rem;
    }
    
    .badge-primary {
        font-size: 0.75rem !important;
        padding: 0.4rem 0.6rem !important;
    }
}

@media (max-width: 768px) {
    .table-responsive {
        border-radius: 10px;
        padding: 0.5rem;
    }
    
    #appointments-table {
        font-size: 0.75rem;
    }
    
    #appointments-table thead th {
        font-size: 0.7rem;
        padding: 0.5rem 0.3rem;
    }
    
    #appointments-table tbody td {
        padding: 0.5rem 0.3rem;
    }
    
    .view-details-btn {
        padding: 0.35rem 0.5rem;
    }
    
    .view-details-btn i {
        font-size: 0.85rem;
    }
    
    .reason-text {
        font-size: 0.75rem;
    }
}
.btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4);
    color: white;
}
@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-toast {
    border-radius: 10px;
    border: none;
}

.table-success {
    background-color: #d4edda !important;
    transition: background-color 0.5s ease;
}
/* Calendar Styles */
.calendar-container {
    background: white;
    border: 2px solid #e0e6ed;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e0e6ed;
}

.calendar-header h4 {
    margin: 0;
    font-size: 1.1rem;
    color: #2c3e50;
    font-weight: 600;
}

.calendar-nav {
    display: flex;
    gap: 0.5rem;
}

.calendar-nav button {
    background: #f7fafc;
    border: 1px solid #e0e6ed;
    border-radius: 6px;
    padding: 0.25rem 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.calendar-nav button:hover {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
}

.calendar-day-header {
    text-align: center;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.85rem;
    padding: 0.5rem;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    border: 2px solid transparent;
}

.calendar-day:hover {
    background: #f7fafc;
    border-color: #3498db;
}

.calendar-day.disabled {
    color: #cbd5e0;
    cursor: not-allowed;
    opacity: 0.5;
}

.calendar-day.disabled:hover {
    background: transparent;
    border-color: transparent;
}

.calendar-day.available {
    background: #e6f7ff;
    color: #2c3e50;
    font-weight: 500;
}

.calendar-day.available:hover {
    background: #3498db;
    color: white;
    transform: scale(1.1);
}

.calendar-day.selected {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
    font-weight: 600;
    border-color: #2c3e50;
}

.calendar-day.other-month {
    color: #cbd5e0;
}

.calendar-legend {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e0e6ed;
    font-size: 0.85rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

.legend-color.available {
    background: #e6f7ff;
    border: 1px solid #3498db;
}

.legend-color.selected {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
}
/* Action buttons styling */
.btn-group .btn {
    margin: 0 2px;
}

.reschedule-action-btn {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border: none;
    color: white;
    transition: all 0.3s ease;
}

.reschedule-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(245, 158, 11, 0.4);
    color: white;
}

.reschedule-action-btn i {
    font-size: 1rem;
}

/* Responsive adjustments for action buttons */
@media (max-width: 992px) {
    .reschedule-action-btn {
        padding: 0.4rem 0.6rem;
        font-size: 0.85rem;
    }
}

@media (max-width: 768px) {
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .btn-group .btn {
        margin: 0;
        width: 100%;
    }
    
    .reschedule-action-btn {
        padding: 0.35rem 0.5rem;
    }
}
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
        <div class="table-responsive">
    <table class="table table-hover" id="appointments-table">
        <thead>
            <tr>
                <th style="width: 12%;">Transaction ID</th>
                <th style="width: 18%;">Resident Name</th>
                <th style="width: 15%;">Service</th>
                <!-- <th style="width: 20%;">Reason</th> -->
                <th style="width: 15%;">Scheduled For</th>
                <th style="width: 12%;">Requested At</th>
                <th style="width: 12%;" class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody id="appointments-tbody">
            <?php if (!empty($appointmentData)): ?>
                <?php foreach ($appointmentData as $app): ?>
                    <tr class="appointment-row" data-service="<?= strtolower($app['service_name'] ?? '') ?>">
                        <td>
                            <span class="badge badge-primary" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">
                                <?= $app['transaction_id'] ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($app['service_name'] ?? 'N/A') ?></td>
                        <!-- <td>
                            <span class="reason-text"><?= htmlspecialchars(substr($app['reason'], 0, 50)) ?><?= strlen($app['reason']) > 50 ? '...' : '' ?></span>
                        </td> -->
                        <td>
                            <?php if ($app['scheduled_for']): ?>
                                <i class="fas fa-calendar-day mr-1" style="color: #3498db;"></i>
                                <?= date('M j, Y', strtotime($app['scheduled_for'])) ?><br>
                                <small class="text-muted">
                                    <i class="fas fa-clock mr-1"></i><?= date('g:i A', strtotime($app['scheduled_for'])) ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">Not Scheduled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?= date('M j, Y', strtotime($app['requested_at'])) ?></small><br>
                            <small class="text-muted"><?= date('g:i A', strtotime($app['requested_at'])) ?></small>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-info view-details-btn" 
                                        data-toggle="modal" 
                                        data-target="#viewModal<?= $app['id'] ?>"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning reschedule-action-btn" 
                                        onclick="openRescheduleModal(<?= $app['id'] ?>)"
                                        title="Reschedule">
                                    <i class="fas fa-calendar-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <!-- Keep the existing modal code here -->
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
                                        <img src="<?= htmlspecialchars($app['id_front_image']) ?>" 
                                             alt="Valid ID" class="img-thumbnail clickable-id w-100" 
                                             style="cursor: zoom-in;"
                                             data-toggle="modal" data-target="#fullImageModal" 
                                             data-img-src="<?= htmlspecialchars($app['id_front_image']) ?>">
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
                    
                    <div class="modal fade" id="rescheduleModal<?= $app['id'] ?>" tabindex="-1" data-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-calendar-alt mr-2"></i> Reschedule Appointment
                                </h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <form id="rescheduleForm<?= $app['id'] ?>" class="reschedule-form">
                                    <input type="hidden" name="appointment_id" value="<?= $app['id'] ?>">
                                    <input type="hidden" name="old_date_id" value="<?= $app['available_date_id'] ?>">
                                    <input type="hidden" name="old_time_slot" value="<?= date('H:i:s', strtotime($app['scheduled_for'])) ?>">
                                    
                                    <input type="hidden" name="new_date_id" id="new_date_id_<?= $app['id'] ?>">

                                    <div class="form-group">
                                        <label><strong>Current Schedule:</strong></label>
                                        <p class="text-muted border-bottom pb-2">
                                            <?= date('F j, Y • g:i A', strtotime($app['scheduled_for'])) ?>
                                        </p>
                                    </div>

                                    <div class="form-group">
                                        <label>Select New Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control date-picker-input" 
                                            data-id="<?= $app['id'] ?>" 
                                            name="selected_date" 
                                            min="<?= date('Y-m-d') ?>" required>
                                        <small class="text-muted">Only dates with available slots set by Admin will be valid.</small>
                                    </div>

                                    <div id="loadingSlots<?= $app['id'] ?>" class="text-center my-3" style="display:none;">
                                        <div class="spinner-border text-primary spinner-border-sm" role="status"></div> Checking availability...
                                    </div>

                                    <div id="dateError<?= $app['id'] ?>" class="alert alert-danger p-2" style="display:none; font-size:0.9rem;"></div>

                                    <div class="form-group" id="timeSlotContainer<?= $app['id'] ?>" style="display:none;">
                                        <label>Select Time Slot <span class="text-danger">*</span></label>
                                        <div class="d-flex flex-column gap-2">
                                            <div class="custom-control custom-radio mb-2">
                                                <input type="radio" id="amSlot<?= $app['id'] ?>" name="new_time_slot" value="09:00:00" class="custom-control-input" disabled>
                                                <label class="custom-control-label" for="amSlot<?= $app['id'] ?>">Morning (9:00 AM)</label>
                                                <span class="badge badge-success float-right am-badge">Available</span>
                                            </div>
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="pmSlot<?= $app['id'] ?>" name="new_time_slot" value="14:00:00" class="custom-control-input" disabled>
                                                <label class="custom-control-label" for="pmSlot<?= $app['id'] ?>">Afternoon (2:00 PM)</label>
                                                <span class="badge badge-success float-right pm-badge">Available</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right mt-4">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning save-btn" disabled>
                                            <i class="fas fa-save mr-2"></i> Update Schedule
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No pending appointments at the moment</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
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

    // ==========================================
    // 1. FILTERS & SEARCH LOGIC (Keep Existing)
    // ==========================================
    function applyFilters() {
        const searchVal = $('#searchInput').val().toLowerCase();
        const selectedService = $('#serviceFilter').val().toLowerCase();

        $('.appointment-row').each(function () {
            const text = $(this).text().toLowerCase();
            const service = $(this).data('service');

            const matchesSearch = text.includes(searchVal);
            const matchesService = selectedService === "" || service === selectedService;

            $(this).toggle(matchesSearch && matchesService);
        });

        // Handle "No Results" row
        const visibleRows = $('.appointment-row:visible').length;
        if (visibleRows === 0 && $('#appointments-tbody tr').length > 0) {
            if ($('#no-results-row').length === 0) {
                $('#appointments-tbody').append(`
                    <tr id="no-results-row">
                        <td colspan="7" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <p>No appointments match your search criteria</p>
                            </div>
                        </td>
                    </tr>
                `);
            }
        } else {
            $('#no-results-row').remove();
        }
    }

    $('#searchInput').on('input', applyFilters);
    $('#serviceFilter').on('change', applyFilters);

    $('#clearFilters').click(function () {
        $('#searchInput').val('');
        $('#serviceFilter').val('');
        $('.appointment-row').show();
        $('#no-results-row').remove();
    });


    // ==========================================
    // 2. APPOINTMENT ACTIONS (Complete/Delete)
    // ==========================================
    $(document).on('click', '.complete-btn', function () {
        const id = $(this).data('id');
        if(confirm('Mark this appointment as completed?')) {
            $.post('complete_appointment.php', { appointment_id: id }, function (res) {
                // Assuming your PHP returns JSON, safety check
                try {
                    const r = JSON.parse(res);
                    if (r.success) location.reload();
                    else alert('Error: ' + (r.message || 'Unknown error'));
                } catch(e) { location.reload(); }
            });
        }
    });

    $(document).on('click', '.delete-btn', function () {
        const id = $(this).data('id');
        if (confirm('Are you sure you want to delete this appointment?')) {
            $.post('delete_appointment.php', { appointment_id: id }, function (res) {
                try {
                    const r = JSON.parse(res);
                    if (r.success) location.reload();
                    else alert('Error: ' + (r.message || 'Unknown error'));
                } catch(e) { location.reload(); }
            });
        }
    });


    // ==========================================
    // 3. IMAGE MODAL LOGIC
    // ==========================================
    $('.clickable-id').on('click', function () {
        const src = $(this).data('img-src');
        $('#fullImage').attr('src', src);
    });

    // Fix for nested modals scrolling issue
    $('#fullImageModal').on('hidden.bs.modal', function () {
        if ($('.modal.show').length) {
            $('body').addClass('modal-open');
        }
    });


    // ==========================================
    // 4. NEW RESCHEDULE LOGIC (Date Picker + AJAX)
    // ==========================================

    // A. Handle Date Selection
    $('.date-picker-input').on('change', function() {
        const dateInput = $(this);
        const appointmentId = dateInput.data('id');
        const selectedDate = dateInput.val();
        
        // UI Elements
        const loader = $(`#loadingSlots${appointmentId}`);
        const errorDiv = $(`#dateError${appointmentId}`);
        const slotContainer = $(`#timeSlotContainer${appointmentId}`);
        const saveBtn = $(`#rescheduleModal${appointmentId} .save-btn`);
        const hiddenDateId = $(`#new_date_id_${appointmentId}`);

        // Reset UI State
        errorDiv.hide();
        slotContainer.hide();
        saveBtn.prop('disabled', true);
        $(`input[name="new_time_slot"]`).prop('checked', false).prop('disabled', true);
        
        // Reset badges
        $('.am-badge, .pm-badge').removeClass('badge-success badge-secondary').text('');

        if (!selectedDate) return;

        loader.show();

        // Check Availability via AJAX
        $.ajax({
            url: 'check_availability.php',
            type: 'POST',
            data: { date: selectedDate },
            dataType: 'json',
            success: function(response) {
                loader.hide();

                if (response.status === 'available') {
                    // Success: Store ID and show slots
                    hiddenDateId.val(response.date_id);
                    slotContainer.fadeIn();

                    // Setup AM Slot
                    const amRadio = $(`#amSlot${appointmentId}`);
                    if (response.am_open) {
                        amRadio.prop('disabled', false);
                        amRadio.siblings('.am-badge').addClass('badge-success').text('Available');
                    } else {
                        amRadio.prop('disabled', true);
                        amRadio.siblings('.am-badge').addClass('badge-secondary').text('Full');
                    }

                    // Setup PM Slot
                    const pmRadio = $(`#pmSlot${appointmentId}`);
                    if (response.pm_open) {
                        pmRadio.prop('disabled', false);
                        pmRadio.siblings('.pm-badge').addClass('badge-success').text('Available');
                    } else {
                        pmRadio.prop('disabled', true);
                        pmRadio.siblings('.pm-badge').addClass('badge-secondary').text('Full');
                    }

                } else {
                    // Error: Date not found or full
                    errorDiv.html('<i class="fas fa-exclamation-circle"></i> ' + response.message).fadeIn();
                }
            },
            error: function() {
                loader.hide();
                errorDiv.text('Server connection error. Please try again.').show();
            }
        });
    });

    // B. Enable Save Button when Time Slot is selected
// B. Enable Save Button when Time Slot is selected
$(document).on('change click', 'input[name="new_time_slot"]', function() {
    console.log("Time slot selected: " + $(this).val()); // Debug check
    
    // Find the form that this specific radio button belongs to
    const form = $(this).closest('form');
    
    // Find the save button inside that form and enable it
    const saveBtn = form.find('.save-btn');
    saveBtn.prop('disabled', false);
    saveBtn.removeAttr('disabled'); // Extra safety for some browsers
});

    // C. Handle Form Submission
    $('.reschedule-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('.save-btn');
        const originalText = btn.html();

        if(!form.find('input[name="new_time_slot"]:checked').val()) {
            showNotification('error', 'Please select a time slot (AM or PM).');
            return;
        }

        // Loading State
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: 'process_reschedule.php',
            type: 'POST',
            data: form.serialize(),
            success: function(res) {
                // Parse if string, otherwise use object
                const response = typeof res === 'string' ? JSON.parse(res) : res;
                
                if (response.success) {
                    $('#rescheduleModal' + form.find('input[name="appointment_id"]').val()).modal('hide');
                    showNotification('success', 'Appointment Rescheduled Successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', response.message || 'Update failed.');
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showNotification('error', 'System error occurred.');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // ==========================================
    // 5. HELPER FUNCTIONS
    // ==========================================

    // Opens the modal (called by the button in your PHP loop)
    window.openRescheduleModal = function(appointmentId) {
        // Close "View Details" modal if it's open
        $(`#viewModal${appointmentId}`).modal('hide');
        
        // Wait for CSS transition, then open Reschedule modal
        setTimeout(() => {
            $(`#rescheduleModal${appointmentId}`).modal('show');
            
            // Reset the form inside the modal
            const form = $(`#rescheduleForm${appointmentId}`);
            form[0].reset(); 
            $(`#loadingSlots${appointmentId}`).hide();
            $(`#dateError${appointmentId}`).hide();
            $(`#timeSlotContainer${appointmentId}`).hide();
            form.find('.save-btn').prop('disabled', true);
            form.find('.badge').text('').removeClass('badge-success badge-secondary');
        }, 300);
    };

    function showNotification(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        const notification = $(`
            <div class="alert ${alertClass} alert-dismissible fade show notification-toast" role="alert" style="
                position: fixed; top: 20px; right: 20px; z-index: 9999;
                min-width: 300px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                animation: slideInRight 0.4s ease;">
                <i class="fas ${icon} mr-2"></i>
                <strong>${message}</strong>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        `);
        
        $('body').append(notification);
        setTimeout(() => {
            notification.fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }

});
</script>
</body>
</html>