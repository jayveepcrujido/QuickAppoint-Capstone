//reference for the book modal
<?php
require '../conn.php';

if (!isset($_GET['id'])) {
    die("No department selected.");
}

$id = intval($_GET['id']);

// Fetch department
$stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
$stmt->execute([$id]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    die("Department not found.");
}

// Fetch services
$serviceStmt = $pdo->prepare("SELECT * FROM department_services WHERE department_id = ?");
$serviceStmt->execute([$id]);
$services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch requirements
$requirementsByService = [];
if ($services) {
    $serviceIds = array_column($services, 'id');
    $in = str_repeat('?,', count($serviceIds) - 1) . '?';
    $reqStmt = $pdo->prepare("SELECT * FROM service_requirements WHERE service_id IN ($in)");
    $reqStmt->execute($serviceIds);
    $requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($requirements as $req) {
        $requirementsByService[$req['service_id']][] = $req['requirement'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($department['acronym'] ?: $department['name']) ?> - Department Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <style>
        body {
            background-color: #f9fafc;
            font-family: "Segoe UI", Tahoma, sans-serif;
        }

        /* Page Header - Responsive */
        .page-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
        }

        .page-header h2 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .page-header h2 i {
            font-size: 1.75rem;
            flex-shrink: 0;
        }

        .page-header .lead {
            font-size: 1rem;
        }

        @media (max-width: 767px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .page-header h2 {
                font-size: 1.25rem;
            }

            .page-header h2 i {
                font-size: 1.5rem;
            }

            .page-header .lead {
                font-size: 0.9rem;
            }

            .page-header .btn {
                margin-top: 1rem;
                width: 100%;
                justify-content: center;
            }
        }

        @media (min-width: 768px) {
            .page-header {
                padding: 1.2rem;
            }

            .page-header h2 {
                font-size: 1.75rem;
            }

            .page-header h2 i {
                font-size: 2rem;
            }
        }

        /* Service Cards - Responsive */
        .service-card {
            border: none;
            border-radius: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #ffffff;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
        }

        .service-icon {
            width: 40px;
            height: 40px;
            font-size: 1.25rem;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }

        @media (min-width: 576px) {
            .service-icon {
                width: 45px;
                height: 45px;
                font-size: 1.5rem;
            }
        }

        .service-card .card-title {
            font-size: 1rem;
            line-height: 1.3;
        }

        @media (min-width: 768px) {
            .service-card .card-title {
                font-size: 1.1rem;
            }
        }

        /* Enhanced Modal Styling */
        .modal-content {
            border: none;
            border-radius: 1rem;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: none;
            padding: 1.5rem 2rem;
            position: relative;
        }

        .modal-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 2rem;
            right: 2rem;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: none;
            padding: 1.5rem 2rem;
            background-color: #f8f9fa;
        }

        /* Service Details Modal */
        #serviceModal .modal-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
        }

        #serviceModal .modal-body {
            padding: 2rem;
        }

        #serviceModal .requirements-list {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 1.5rem;
        }

        #serviceModal .requirements-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        #serviceModal .requirements-list li:last-child {
            border-bottom: none;
        }

        /* Appointment Modal */
        #appointmentModal .modal-dialog {
            max-width: 800px;
        }

        #appointmentModal .modal-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
        }

        #appointmentModal .form-section {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            border: 1px solid #e9ecef;
        }

        #appointmentModal .section-title {
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        #appointmentModal .section-title i {
            color: #3498db;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        #appointmentModal .form-control,
        #appointmentModal .form-control-file {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        #appointmentModal .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
        }

        #appointmentModal label {
            color: #2c3e50;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        /* Calendar Styling - Mobile First & Compact */
        .calendar-container {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 0.75rem;
            border: 1px solid #e9ecef;
            max-width: 100%;
            margin: 0 auto;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 0.5rem;
            color: white;
            gap: 0.5rem;
        }

        .calendar-nav button {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.35rem 0.5rem;
            border-radius: 0.4rem;
            transition: all 0.3s ease;
            font-size: 0.75rem;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            line-height: 1;
        }

        .calendar-nav button:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .calendar-nav button i {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        #calendar-header {
            font-weight: 600;
            font-size: 0.85rem;
            text-align: center;
            flex: 1;
        }

        #calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
            margin-top: 0.5rem;
            max-width: 100%;
            width: 100%;
        }

        .calendar-day {
            aspect-ratio: 1;
            min-height: 40px;
            padding: 3px 2px;
            border: 1px solid #e9ecef;
            border-radius: 0.3rem;
            font-size: 0.7rem;
            background-color: #f8f9fa;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 2px;
        }

        .calendar-day.available {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-color: #4caf50;
            cursor: pointer;
        }

        .calendar-day.available:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .calendar-day.unavailable {
            background: #f5f5f5;
            border-color: #e0e0e0;
            color: #9e9e9e;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .calendar-day.selected {
            background: linear-gradient(135deg, #3498db, #2c3e50) !important;
            color: white;
            font-weight: bold;
            border-color: #2c3e50;
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.4);
        }

        .calendar-day-header {
            font-weight: 600;
            text-align: center;
            padding: 0.35rem 0.2rem;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            border-radius: 0.3rem;
            font-size: 0.65rem;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .calendar-day .day-number {
            font-weight: 600;
            font-size: 0.8rem;
            line-height: 1;
        }

        .calendar-day .badge {
            font-size: 0.5rem;
            padding: 1px 3px;
            line-height: 1;
            white-space: nowrap;
        }

        /* Slot Selector - Mobile First & Compact */
        #slotSelector {
            margin-top: 1rem;
        }

        #slotSelector .form-check {
            padding: 0.9rem;
            margin-bottom: 0.7rem;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 0.6rem;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        #slotSelector .form-check:hover {
            border-color: #3498db;
            background: linear-gradient(135deg, #e3f2fd, #f8f9fa);
            transform: translateX(3px);
        }

        #slotSelector .form-check-input {
            margin-top: 0.15rem;
        }

        #slotSelector .form-check-input:checked ~ .form-check-label {
            color: #2c3e50;
        }

        #slotSelector .form-check-input:disabled ~ .form-check-label {
            color: #9e9e9e;
            opacity: 0.6;
            cursor: not-allowed;
        }

        #slotSelector .form-check.disabled {
            background: #f5f5f5;
            border-color: #e0e0e0;
            cursor: not-allowed;
            opacity: 0.6;
        }

        #slotSelector .form-check.disabled:hover {
            transform: none;
            border-color: #e0e0e0;
            background: #f5f5f5;
        }

        #slotSelector .form-check-input:checked ~ .form-check-label::after {
            content: '✓';
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #4caf50;
            font-size: 1.1rem;
            font-weight: bold;
        }

        #slotSelector .form-check-label {
            width: 100%;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            position: relative;
            padding-right: 2rem;
        }

        #slotSelector .form-check-label strong {
            color: #2c3e50;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            line-height: 1.2;
        }

        #slotSelector .form-check-label strong i {
            font-size: 1rem;
            display: flex;
            align-items: center;
        }

        #slotSelector .form-check-label small {
            color: #6c757d;
            font-size: 0.75rem;
        }

        /* Submit Button */
        .btn-submit-appointment {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border: none;
            padding: 0.9rem 1.5rem;
            font-weight: 600;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-submit-appointment:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(44, 62, 80, 0.3);
        }

        .btn-submit-appointment i {
            display: inline-flex;
            align-items: center;
        }

        /* Transaction Modal - Responsive */
        #transactionModal .modal-content {
            border-radius: 1rem;
            overflow: hidden;
        }

        #transactionModal .modal-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            padding: 1.25rem 1rem;
        }

        #transactionModal .modal-header h4 {
            font-size: 1.1rem;
        }

        #transactionModal .modal-header img {
            height: 35px;
        }

        #transactionModal .modal-body {
            padding: 2rem 1.5rem;
        }

        #transactionModal .modal-body i.bx-check-circle {
            font-size: 3rem;
        }

        .transaction-number-box {
            background: linear-gradient(135deg, #e3f2fd, #f8f9fa);
            border: 2px dashed #3498db;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin: 1.25rem 0;
        }

        #transactionNumber {
            font-size: 1.5rem;
            letter-spacing: 2px;
            color: #2c3e50;
        }

        .alert-reminder {
            background: linear-gradient(135deg, #fff3cd, #fff9e6);
            border: none;
            border-left: 4px solid #ffc107;
            border-radius: 0.75rem;
            padding: 1rem;
        }

        .alert-reminder i {
            font-size: 1.25rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }

        @media (min-width: 576px) {
            #transactionModal .modal-header {
                padding: 1.5rem;
            }

            #transactionModal .modal-header h4 {
                font-size: 1.25rem;
            }

            #transactionModal .modal-header img {
                height: 40px;
            }

            #transactionModal .modal-body {
                padding: 2.5rem 2rem;
            }

            #transactionModal .modal-body i.bx-check-circle {
                font-size: 4rem;
            }

            .transaction-number-box {
                padding: 1.5rem;
                margin: 1.5rem 0;
            }

            #transactionNumber {
                font-size: 2rem;
            }

            .alert-reminder {
                padding: 1.25rem;
            }

            .alert-reminder i {
                font-size: 1.5rem;
            }
        }

        /* Hide footer during capture */
        .no-capture-capturing {
            display: none !important;
        }

        /* Responsive - Mobile First Approach */
        
        /* Small phones (320px - 374px) */
        @media (max-width: 374px) {
            .calendar-container {
                padding: 0.6rem;
                max-width: 100%;
            }

            .calendar-day {
                min-height: 38px;
                font-size: 0.65rem;
                padding: 2px 1px;
            }

            .calendar-day .day-number {
                font-size: 0.75rem;
            }

            .calendar-day .badge {
                font-size: 0.45rem;
                padding: 1px 2px;
            }

            .calendar-day-header {
                font-size: 0.6rem;
                padding: 0.3rem 0.1rem;
            }

            #calendar {
                gap: 2px;
            }

            .calendar-nav {
                padding: 0.4rem;
            }

            .calendar-nav button {
                padding: 0.3rem 0.4rem;
                font-size: 0.7rem;
            }

            .calendar-nav button i {
                font-size: 0.85rem;
            }

            #calendar-header {
                font-size: 0.75rem;
            }

            #slotSelector .form-check {
                padding: 0.7rem;
            }

            #slotSelector .form-check-label strong {
                font-size: 0.8rem;
            }

            #slotSelector .form-check-label strong i {
                font-size: 0.9rem;
            }

            #slotSelector .form-check-label small {
                font-size: 0.7rem;
            }

            #appointmentModal .form-section {
                padding: 1rem;
            }

            .btn-submit-appointment {
                padding: 0.8rem 1.2rem;
                font-size: 0.9rem;
            }
        }

        /* Tablets and small devices (575px - 767px) */
        @media (min-width: 575px) {
            .calendar-container {
                padding: 0.9rem;
                max-width: 500px;
            }

            .calendar-day {
                min-height: 48px;
                font-size: 0.75rem;
                padding: 4px 3px;
            }

            .calendar-day .day-number {
                font-size: 0.85rem;
            }

            .calendar-day .badge {
                font-size: 0.55rem;
                padding: 2px 4px;
            }

            .calendar-day-header {
                font-size: 0.7rem;
                padding: 0.4rem 0.2rem;
            }

            #calendar {
                gap: 4px;
            }

            .calendar-nav {
                padding: 0.55rem;
            }

            .calendar-nav button {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
            }

            .calendar-nav button i {
                font-size: 0.95rem;
            }

            #calendar-header {
                font-size: 0.9rem;
            }

            #slotSelector .form-check {
                padding: 0.95rem;
            }

            #slotSelector .form-check-label strong {
                font-size: 0.92rem;
            }

            #slotSelector .form-check-label strong i {
                font-size: 1.05rem;
            }

            #appointmentModal .form-section {
                padding: 1.3rem;
            }

            .btn-submit-appointment {
                padding: 0.95rem 1.7rem;
            }
        }

        /* Medium devices (768px - 991px) */
        @media (min-width: 768px) {
            #appointmentModal .modal-dialog {
                max-width: 750px;
            }

            .calendar-container {
                padding: 1rem;
                max-width: 550px;
            }

            .calendar-day {
                min-height: 52px;
                font-size: 0.78rem;
                padding: 5px 4px;
            }

            .calendar-day .day-number {
                font-size: 0.9rem;
            }

            .calendar-day .badge {
                font-size: 0.58rem;
                padding: 2px 4px;
            }

            .calendar-day-header {
                font-size: 0.72rem;
                padding: 0.45rem 0.25rem;
            }

            #calendar {
                gap: 4px;
            }

            .calendar-nav {
                padding: 0.6rem;
            }

            .calendar-nav button {
                padding: 0.42rem 0.7rem;
                font-size: 0.82rem;
            }

            .calendar-nav button i {
                font-size: 1rem;
            }

            #calendar-header {
                font-size: 0.95rem;
            }

            #slotSelector {
                margin-top: 1.1rem;
            }

            #slotSelector .form-check {
                padding: 1rem;
                margin-bottom: 0.8rem;
            }

            #slotSelector .form-check-label strong {
                font-size: 0.95rem;
            }

            #slotSelector .form-check-label strong i {
                font-size: 1.08rem;
            }

            #slotSelector .form-check-label small {
                font-size: 0.78rem;
            }

            #appointmentModal .form-section {
                padding: 1.4rem;
            }

            .btn-submit-appointment {
                padding: 0.95rem 1.8rem;
            }
        }

        /* Large devices (992px and up) */
        @media (min-width: 992px) {
            #appointmentModal .modal-dialog {
                max-width: 800px;
            }

            .calendar-container {
                padding: 1.1rem;
                max-width: 600px;
            }

            .calendar-day {
                min-height: 55px;
                font-size: 0.8rem;
                padding: 5px 4px;
            }

            .calendar-day .day-number {
                font-size: 0.95rem;
                margin-bottom: 0.15rem;
            }

            .calendar-day .badge {
                font-size: 0.6rem;
                padding: 2px 5px;
            }

            .calendar-day-header {
                font-size: 0.75rem;
                padding: 0.5rem 0.3rem;
            }

            #calendar {
                gap: 5px;
                margin-top: 0.7rem;
            }

            .calendar-nav {
                padding: 0.65rem;
                margin-bottom: 0.8rem;
            }

            .calendar-nav button {
                padding: 0.45rem 0.8rem;
                font-size: 0.85rem;
            }

            .calendar-nav button i {
                font-size: 1.05rem;
            }

            #calendar-header {
                font-size: 1rem;
            }

            #slotSelector {
                margin-top: 1.2rem;
            }

            #slotSelector .form-check {
                padding: 1.05rem;
                margin-bottom: 0.85rem;
            }

            #slotSelector .form-check:hover {
                transform: translateX(5px);
            }

            #slotSelector .form-check-label strong {
                font-size: 0.98rem;
            }

            #slotSelector .form-check-label strong i {
                font-size: 1.12rem;
            }

            #slotSelector .form-check-label small {
                font-size: 0.8rem;
            }

            #slotSelector .form-check-input:checked ~ .form-check-label::after {
                font-size: 1.2rem;
            }

            #appointmentModal .form-section {
                padding: 1.45rem;
            }

            .btn-submit-appointment {
                padding: 1rem 2rem;
            }
        }

        /* Extra large devices */
        @media (min-width: 1200px) {
            .calendar-day {
                min-height: 58px;
            }
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
        /* --- Responsive modal layout fix --- */
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 1rem auto;
                max-width: 95% !important;
                width: auto !important;
            }

            .modal-content {
                border-radius: 1rem;
                overflow: hidden;
            }

            .modal-header {
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 1rem;
                text-align: center;
            }

            .modal-title {
                font-size: 1rem;
                text-align: center;
                flex: 1 1 100%;
            }

            .modal-body {
                max-height: 70vh;
                overflow-y: auto;
                padding: 1rem;
            }

            .modal-footer {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        /* For ultra-small screens */
        @media (max-width: 480px) {
            .modal-dialog {
                margin: 0.5rem;
                width: 100% !important;
            }

            .modal-body {
                max-height: 68vh;
                overflow-y: auto;
            }

            .modal-header .btn-close {
                font-size: 1.2rem;
            }
        }
        /* --- Taller modals --- */
    #appointmentModal .modal-dialog {
        max-width: 600px;
        height: 90vh; /* make the modal take 90% of viewport height */
        display: flex;
        align-items: center;
    }

    #appointmentModal .modal-content {
        height: 100%;
        display: flex;
        flex-direction: column;
        border-radius: 1rem;
        overflow: hidden;
    }

    #appointmentModal .modal-body {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 1rem 1.5rem;
    }

    /* --- Mobile-specific adjustments --- */
    @media (max-width: 768px) {
        #appointmentModal .modal-dialog {
            height: 95vh;
            margin: 0.5rem auto;
        }

        #appointmentModal .modal-body {
            max-height: calc(95vh - 120px);
            overflow-y: auto;
        }
    }

    </style>
</head>
<body>
    <div id="content-area">
        <!-- Header -->
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bx bx-building-house"></i> 
                    <?= htmlspecialchars($department['name']) ?>
                </h2>
                <p class="lead mb-0">
                    <?= $department['description'] ? htmlspecialchars($department['description']) : '<em>No description provided.</em>' ?>
                </p>
            </div>

            <a href="#" class="btn btn-light text-primary d-inline-flex align-items-center px-4 py-2 rounded-pill shadow-sm" id="backButton">
                <i class="bx bx-arrow-back mr-2"></i> Back
            </a>
        </div>
        
        <div class="container">
            <!-- Services Section -->
            <h5 class="mb-3 text-secondary">
                <i class="bx bx-cog"></i> Services & Requirements
            </h5>
            
            <?php if ($services): ?>
                <div class="row">
                    <?php foreach ($services as $svc): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card service-card shadow-sm h-100"
                            data-id="<?= $svc['id'] ?>"
                            data-name="<?= htmlspecialchars($svc['service_name']) ?>"
                            data-req='<?= json_encode($requirementsByService[$svc['id']] ?? []) ?>'>

                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="service-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center mr-3">
                                            <i class="bx bx-envelope"></i>
                                        </div>
                                        <h5 class="card-title mb-0"><?= htmlspecialchars($svc['service_name']) ?></h5>
                                    </div>
                                    <p class="text-muted small mb-0">Click to view details & requirements</p>
                                </div>
                                <div class="text-right mt-3">
                                    <span class="badge badge-pill badge-primary px-3 py-2">
                                        <i class="bx bx-info-circle mr-1"></i> View
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-muted">No services available for this department.</div>
            <?php endif; ?>
        </div>

        <!-- Service Details Modal -->
        <div class="modal fade" id="serviceModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg">
                    <div class="modal-header text-white">
                        <h5 class="modal-title d-flex align-items-center">
                            <i class="bx bx-info-circle mr-2"></i> Service Details
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <h5 id="serviceName" style="color: #2c3e50; font-weight: 600;"></h5>
                        <div class="requirements-list mt-3">
                            <h6 class="d-flex align-items-center" style="color: #2c3e50; margin-bottom: 1rem;">
                                <i class="bx bx-list-ul mr-2"></i>Requirements:
                            </h6>
                            <ul id="serviceRequirements" class="list-unstyled mb-0"></ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn text-white d-inline-flex align-items-center" id="bookNowBtn" style="background: linear-gradient(135deg, #2c3e50, #3498db);">
                            <i class="bx bx-calendar mr-2"></i> Book Now
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Book Appointment Modal -->
        <div class="modal fade" id="appointmentModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form id="appointment-form" class="modal-content shadow-lg" enctype="multipart/form-data">
                    <div class="modal-header text-white">
                        <h5 class="modal-title d-flex align-items-center">
                            <i class="bx bx-calendar-check mr-2"></i> Book Appointment
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="department_id" id="department_id" value="<?= $department['id'] ?>">
                        <input type="hidden" name="available_date_id" id="available_date_id">

                        <!-- Service Selection Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="bx bx-briefcase"></i>
                                Service Information
                            </div>
                            <div class="form-group mb-3">
                                <label for="service">Select Service</label>
                                <select class="form-control" name="service" id="service" required></select>
                            </div>

                            <div class="form-group mb-0">
                                <label for="valid_id">Upload Valid ID</label>
                                <input type="file" class="form-control-file" name="valid_id" id="valid_id" accept="image/*" required>
                                <small class="form-text text-muted">Accepted formats: JPG, PNG, PDF</small>
                            </div>
                        </div>

                        <!-- Calendar Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="bx bx-calendar"></i>
                                Select Date & Time
                            </div>
                            
                            <div class="calendar-container">
                                <div class="calendar-nav">
                                    <button type="button" id="prevMonth">
                                        <i class="bx bx-chevron-left"></i> <span>Prev</span>
                                    </button>
                                    <strong id="calendar-header"></strong>
                                    <button type="button" id="nextMonth">
                                        <span>Next</span> <i class="bx bx-chevron-right"></i>
                                    </button>
                                </div>
                                <div id="calendar"></div>
                            </div>
                            
                            <div id="slotSelector"></div>
                        </div>

                        <!-- Reason Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="bx bx-message-square-detail"></i>
                                Purpose of Visit
                            </div>
                            <div class="form-group mb-0">
                                <textarea class="form-control" name="reason" id="reason" rows="3" placeholder="Please describe the purpose of your appointment..." required></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-submit-appointment btn-block text-white d-inline-flex align-items-center justify-content-center">
                            <i class="bx bx-check-circle mr-2"></i> Confirm Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transaction Success Modal -->
        <div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" id="transactionModalContent">
                    
                    <div class="modal-header text-white justify-content-center">
                        <div class="d-flex align-items-center">
                            <img src="../assets/images/logo.png" alt="LGU Logo" style="height: 40px; margin-right: 10px;">
                            <h4 class="font-weight-bold mb-0">LGU Quick Appoint</h4>
                        </div>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body text-center">
                        <div class="mb-3">
                            <i class="bx bx-check-circle d-inline-flex" style="font-size: 4rem; color: #4caf50;"></i>
                        </div>
                        
                        <h5 class="font-weight-semibold text-dark mb-2">Appointment Confirmed!</h5>
                        
                        <div class="transaction-number-box">
                            <p class="mb-2 text-muted">Transaction Number</p>
                            <h3 id="transactionNumber" class="font-weight-bold">-</h3>
                        </div>

                        <div class="alert-reminder">
                            <div class="d-flex align-items-start">
                                <i class="bx bx-info-circle" style="color: #ff9800; font-size: 1.5rem; margin-right: 12px; margin-top: 2px;"></i>
                                <div class="text-left">
                                    <strong style="color: #856404;">Important Reminder</strong>
                                    <p class="mb-0 mt-1 small" style="color: #856404;">
                                        Please save or screenshot your transaction number. Bring all required documents and present this slip to the assigned personnel.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 justify-content-center no-capture">
                        <button id="downloadTransactionBtn" class="btn text-white px-4 py-2 font-weight-semibold d-inline-flex align-items-center" style="background: linear-gradient(135deg, #2c3e50, #3498db); border-radius: 0.75rem;">
                            <i class="bx bx-download mr-2"></i> Download Slip
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

    <script>
        let currentMonth = new Date().getMonth() + 1;
        let currentYear = new Date().getFullYear();

        // Open booking modal
        function openBooking(departmentId) {
            // Reset to current date
            currentMonth = new Date().getMonth() + 1;
            currentYear = new Date().getFullYear();
            
            $('#appointmentModal').modal('show');
            $('#department_id').val(departmentId);
            $('#available_date_id').val('');
            $('#calendar').empty();
            $('#slotSelector').empty();

            // Load services
            $.get('get_services_by_department.php', { department_id: departmentId }, function(data) {
                $('#service').html(data);
            });

            loadCalendar(departmentId);
        }

        // Load calendar
        function loadCalendar(departmentId) {
            $.get('get_available_dates.php', { department_id: departmentId, month: currentMonth, year: currentYear }, function(data) {
                const availableDates = JSON.parse(data);
                generateCalendar(availableDates);
            });
        }

        // Generate calendar
        function generateCalendar(availableDates) {
            const calendar = $('#calendar');
            calendar.empty();

            const firstDay = new Date(currentYear, currentMonth - 1, 1);
            const lastDate = new Date(currentYear, currentMonth, 0).getDate();
            const startDay = firstDay.getDay();
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

            $('#calendar-header').text(firstDay.toLocaleString('default', { month: 'long' }) + ' ' + currentYear);

            // Day headers
            days.forEach(day => calendar.append(`<div class='calendar-day-header'>${day}</div>`));

            // Empty slots
            for (let i = 0; i < startDay; i++) {
                calendar.append('<div class="calendar-day"></div>');
            }

            // Dates
            for (let day = 1; day <= lastDate; day++) {
                const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const data = availableDates[dateStr] || null;
                
                const amSlots = data ? (data.am_slots - data.am_booked) : 0;
                const pmSlots = data ? (data.pm_slots - data.pm_booked) : 0;
                const hasAvailableSlots = amSlots > 0 || pmSlots > 0;
                
                const div = $(`<div class='calendar-day ${data && hasAvailableSlots ? "available" : data && !hasAvailableSlots ? "unavailable" : ""}' data-date='${dateStr}'></div>`);
                
                div.append(`<div class='day-number'>${day}</div>`);

                if (data) {
                    div.append(`<div class='badge ${amSlots > 0 ? 'badge-success' : 'badge-secondary'}'>AM: ${amSlots}</div>`);
                    div.append(`<div class='badge ${pmSlots > 0 ? 'badge-info' : 'badge-secondary'}'>PM: ${pmSlots}</div>`);

                    if (hasAvailableSlots) {
                        div.click(function() {
                            $('.calendar-day').removeClass('selected');
                            $(this).addClass('selected');

                            let slotsHtml = '';
                            
                            // AM Slot
                            if (amSlots > 0) {
                                slotsHtml += `
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="slot_period" id="slot_am_${data.id}" value="am" data-id="${data.id}" required>
                                        <label class="form-check-label" for="slot_am_${data.id}">
                                            <strong><i class="bx bx-sun"></i>Morning Slot (AM)</strong>
                                            <small class="d-block mt-1">${amSlots} slots available</small>
                                        </label>
                                    </div>
                                `;
                            } else {
                                slotsHtml += `
                                    <div class="form-check disabled">
                                        <input class="form-check-input" type="radio" name="slot_period" id="slot_am_${data.id}" value="am" data-id="${data.id}" disabled>
                                        <label class="form-check-label" for="slot_am_${data.id}">
                                            <strong><i class="bx bx-sun"></i>Morning Slot (AM)</strong>
                                            <small class="d-block mt-1">No slots available</small>
                                        </label>
                                    </div>
                                `;
                            }

                            // PM Slot
                            if (pmSlots > 0) {
                                slotsHtml += `
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="slot_period" id="slot_pm_${data.id}" value="pm" data-id="${data.id}" required>
                                        <label class="form-check-label" for="slot_pm_${data.id}">
                                            <strong><i class="bx bx-moon"></i>Afternoon Slot (PM)</strong>
                                            <small class="d-block mt-1">${pmSlots} slots available</small>
                                        </label>
                                    </div>
                                `;
                            } else {
                                slotsHtml += `
                                    <div class="form-check disabled">
                                        <input class="form-check-input" type="radio" name="slot_period" id="slot_pm_${data.id}" value="pm" data-id="${data.id}" disabled>
                                        <label class="form-check-label" for="slot_pm_${data.id}">
                                            <strong><i class="bx bx-moon"></i>Afternoon Slot (PM)</strong>
                                            <small class="d-block mt-1">No slots available</small>
                                        </label>
                                    </div>
                                `;
                            }

                            $('#slotSelector').html(slotsHtml);

                            $('input[name="slot_period"]:not(:disabled)').on('change', function() {
                                $('#available_date_id').val($(this).data('id'));
                            });
                        });
                    }
                }
                calendar.append(div);
            }
        }

        // Calendar navigation
// Update the calendar navigation to use event delegation and prevent multiple clicks
$(document).off('click', '#prevMonth').on('click', '#prevMonth', function(e) {
    e.preventDefault();
    if ($(this).prop('disabled')) return; // Prevent clicks during loading
    
    $(this).prop('disabled', true);
    currentMonth--; 
    if (currentMonth < 1) { currentMonth = 12; currentYear--; } 
    loadCalendar($('#department_id').val());
    
    setTimeout(() => $(this).prop('disabled', false), 500); // Re-enable after delay
});

$(document).off('click', '#nextMonth').on('click', '#nextMonth', function(e) {
    e.preventDefault();
    if ($(this).prop('disabled')) return; // Prevent clicks during loading
    
    $(this).prop('disabled', true);
    currentMonth++; 
    if (currentMonth > 12) { currentMonth = 1; currentYear++; } 
    loadCalendar($('#department_id').val());
    
    setTimeout(() => $(this).prop('disabled', false), 500); // Re-enable after delay
});

// Also add a reset when modal is hidden
$('#appointmentModal').on('hidden.bs.modal', function () {
    currentMonth = new Date().getMonth() + 1;
    currentYear = new Date().getFullYear();
    $('#appointment-form')[0].reset();
    $('#slotSelector').empty();
    $('#calendar').empty();
});

        // Back button - using event delegation for dynamically loaded content
        $(document).on('click', '#backButton', function(e) {
            e.preventDefault();
            $.ajax({
                url: "residents_view_departments.php",
                type: "GET",
                success: function(response) {
                    $("#content-area").html(response);
                },
                error: function() {
                    alert("Failed to load departments list.");
                }
            });
        });

        // Service card click - show service modal
        $(document).on("click", ".service-card", function() {
            const serviceId = $(this).data("id");
            const serviceName = $(this).data("name");
            const requirements = $(this).data("req");

            $("#serviceName").text(serviceName);
            $("#serviceRequirements").empty();

            if (requirements.length > 0) {
                requirements.forEach(r => {
                    $("#serviceRequirements").append(
                        `<li class="mb-2 d-flex align-items-start"><i class="bx bx-check-circle text-success mr-2" style="margin-top: 2px;"></i><span>${r}</span></li>`
                    );
                });
            } else {
                $("#serviceRequirements").html('<p class="text-muted">No specific requirements listed.</p>');
            }

            $("#bookNowBtn").data("service-id", serviceId);
            $("#serviceModal").modal("show");
        });

        // Book now button - open appointment modal
        $(document).on('click', '#bookNowBtn', function(e) {
            const serviceId = $(this).data("service-id");
            $("#serviceModal").modal("hide");

            const deptId = <?= $department['id'] ?>;
            openBooking(deptId);

            // Preselect service after services are loaded
            setTimeout(() => {
                $("#service").val(serviceId);
            }, 500);
        });

        // Form submission
        $(document).on('submit', '#appointment-form', function(e) {
            e.preventDefault();
            
            // Validate slot selection
            const selectedSlot = $('input[name="slot_period"]:checked');
            
            if (!selectedSlot.length) {
                alert('⚠️ Please select a date and time slot.');
                return;
            }

            const selectedSlotId = selectedSlot.data('id');
            const slotPeriod = selectedSlot.val();

            if (!selectedSlotId || !slotPeriod) {
                alert('⚠️ Invalid slot selection. Please try again.');
                return;
            }

            // Set hidden field
            $('#available_date_id').val(selectedSlotId);

            // Validate other fields
            const departmentId = $('#department_id').val();
            const serviceId = $('#service').val();
            const reason = $('#reason').val();
            const fileInput = $('#valid_id')[0];

            if (!fileInput.files || fileInput.files.length === 0) {
                alert('⚠️ Please upload a valid ID.');
                return;
            }

            if (!departmentId || !serviceId || !reason.trim()) {
                alert('⚠️ Please fill in all required fields.');
                return;
            }

            // Create FormData
            const formData = new FormData(this);
            formData.append('slot_period', slotPeriod);

            // Disable submit button
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="bx bx-loader-circle bx-spin mr-2"></i> Processing...');

            // Submit via AJAX
            $.ajax({
                url: 'residents_submit_appointment.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    submitBtn.prop('disabled', false).html(originalText);

                    if (res.status === 'success') {
                        // Close appointment modal
                        $('#appointmentModal').modal('hide');
                        
                        // Reset form
                        $('#appointment-form')[0].reset();
                        $('#slotSelector').empty();
                        $('#calendar').empty();
                        
                        // Show transaction modal
                        const transactionNum = res.transaction_id || res.transaction_number || 'N/A';
                        $('#transactionNumber').text(transactionNum);
                        
                        // Wait for appointment modal to close, then show transaction modal
                        setTimeout(function() {
                            $('#transactionModal').modal('show');
                        }, 500);
                        
                        // Reload calendar
                        const deptId = $('#department_id').val();
                        if (deptId) {
                            loadCalendar(deptId);
                        }
                    } else {
                        alert('❌ ' + (res.message || 'Booking failed. Please try again.'));
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.prop('disabled', false).html(originalText);
                    
                    let errorMsg = 'Failed to book appointment. ';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg += response.message || error;
                    } catch(e) {
                        errorMsg += 'Please check your connection and try again.';
                    }
                    alert('❌ ' + errorMsg);
                }
            });
        });

        // Download transaction slip
        $(document).on('click', '#downloadTransactionBtn', function(e) {
            const modalContent = document.getElementById("transactionModalContent");
            const footer = modalContent.querySelector(".no-capture");
            
            footer.classList.add("no-capture-capturing");

            html2canvas(modalContent, { scale: 2 }).then((canvas) => {
                footer.classList.remove("no-capture-capturing");
                const link = document.createElement("a");
                link.download = "appointment_slip_" + $('#transactionNumber').text() + ".png";
                link.href = canvas.toDataURL("image/png");
                link.click();
            });
        });
    </script>
</body>
</html>