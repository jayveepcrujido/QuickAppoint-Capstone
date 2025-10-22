<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
include '../conn.php';

// Fetch departments with services
$stmt = $pdo->query("SELECT d.*, 
                            GROUP_CONCAT(s.service_name ORDER BY s.id SEPARATOR ', ') AS services
                     FROM departments d
                     LEFT JOIN department_services s ON d.id = s.department_id
                     GROUP BY d.id ORDER BY d.created_at DESC");
$departments = $stmt->fetchAll();

// Fetch all services and requirements per department
$serviceMap = [];
$stmt = $pdo->query("SELECT ds.id AS service_id, ds.department_id, ds.service_name, sr.requirement
                     FROM department_services ds
                     LEFT JOIN service_requirements sr ON ds.id = sr.service_id
                     ORDER BY ds.department_id, ds.id");

while ($row = $stmt->fetch()) {
    $deptId = $row['department_id'];
    $serviceId = $row['service_id'];

    if (!isset($serviceMap[$deptId][$serviceId])) {
        $serviceMap[$deptId][$serviceId] = [
            'name' => $row['service_name'],
            'requirements' => []
        ];
    }

    if ($row['requirement']) {
        $serviceMap[$deptId][$serviceId]['requirements'][] = $row['requirement'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --primary-light: #3498db;
            --primary-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            --secondary-gradient: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            --success-gradient: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            --warning-gradient: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            --danger-color: #e74c3c;
            --card-shadow: 0 8px 25px rgba(44, 62, 80, 0.12);
            --hover-shadow: 0 12px 35px rgba(44, 62, 80, 0.18);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 3rem;
        }

        /* Header Styles */
        .page-header {
            border-radius: 20px;
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 20px rgba(44, 62, 80, 0.15);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.6;
        }

        .page-header .container {
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 2rem;
        }

        .page-title i {
            font-size: 2.5rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .btn-add-dept {
            background: white;
            color: var(--primary-dark);
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
            font-size: 0.95rem;
        }

        .btn-add-dept:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.4);
            color: var(--primary-dark);
        }

        .btn-add-dept i {
            font-size: 1.25rem;
        }

        /* Stats Cards - Side-by-Side Layout */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            height: auto;
            display: flex;
            align-items: center;
            gap: 1rem; /* spacing between icon and number */
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            opacity: 0.05;
            border-radius: 0 16px 0 100%;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--hover-shadow);
        }

        /* Icon beside number */
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-icon.primary {
            background: var(--primary-gradient);
        }

        .stat-icon.secondary {
            background: var(--secondary-gradient);
        }

        /* Number and label container */
        .stat-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 0;
            line-height: 1.1;
        }

        .stat-label {
            margin: 0.25rem 0 0;
            color: #7f8c8d;
            font-weight: 500;
            font-size: 0.9rem;
        }


        /* Search Section */
        .search-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2.5rem;
        }

        .search-wrapper {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            font-size: 1.25rem;
            z-index: 2;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1.5rem 1rem 3.75rem;
            border: 2px solid #ecf0f1;
            border-radius: 50px;
            font-size: 1rem;
            transition: var(--transition);
            background: #f8f9fa;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            background: white;
        }

        .btn-clear {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        .btn-clear:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
            color: white;
        }

        /* Department Cards */
        .dept-card-wrapper {
            margin-bottom: 1.5rem;
        }

        .dept-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            cursor: pointer;
            height: 100%;
            border: 2px solid transparent;
        }

        .dept-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--hover-shadow);
            border-color: var(--primary-light);
        }

        .dept-card-header {
            background: var(--primary-gradient);
            padding: 1.25rem 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .dept-card-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 150px;
            height: 120px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .dept-acronym {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .dept-acronym i {
            color: white !important;
            font-size: 1.75rem;
        }

        .dept-name {
            color: white;
            font-size: 0.875rem;
            opacity: 0.95;
            margin: 0.5rem 0 0;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

        .dept-card-body {
            padding: 1.25rem 1.5rem;
            min-height: 100px;
        }

        .service-badge {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            background: linear-gradient(135deg, #ecf0f1 0%, #d5dbdb 100%);
            color: var(--primary-dark);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin: 0.2rem;
            transition: var(--transition);
            border: 1px solid #d5dbdb;
        }

        .service-badge:hover {
            background: var(--secondary-gradient);
            color: white;
            transform: scale(1.08);
            border-color: transparent;
        }

        .empty-services {
            color: #95a5a6;
            font-style: italic;
            text-align: center;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .empty-services i {
            font-size: 1.25rem;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            border: none;
            padding: 2rem 2.5rem;
            position: relative;
        }

        .modal-header.primary {
            background: var(--primary-gradient);
            color: white;
        }

        .modal-header.success {
            background: var(--success-gradient);
            color: white;
        }

        .modal-header.warning {
            background: var(--warning-gradient);
            color: white;
        }

        .modal-header h5 {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            margin: 0;
        }

        .modal-header h5 i {
            font-size: 1.75rem;
        }

        .modal-header .close {
            color: white;
            opacity: 1;
            text-shadow: none;
            font-size: 2rem;
            font-weight: 300;
            transition: var(--transition);
        }

        .modal-header .close:hover {
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }

        .info-label {
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }

        .info-label i {
            color: var(--primary-light);
            font-size: 1.25rem;
        }

        .service-item {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-light);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border-radius: 12px;
            transition: var(--transition);
        }

        .service-item:hover {
            background: #ecf0f1;
            transform: translateX(5px);
        }

        .service-name {
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 1.15rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .service-name i {
            color: var(--primary-light);
            font-size: 1.25rem;
        }

        .requirement-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .requirement-list li {
            color: #5a6c7d;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: start;
            gap: 0.75rem;
            padding-left: 1rem;
        }

        .requirement-list li i {
            color: var(--primary-light);
            margin-top: 0.25rem;
            font-size: 1rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.75rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .form-label i {
            color: var(--primary-light);
            font-size: 1.125rem;
        }

        .form-control-custom {
            border: 2px solid #ecf0f1;
            border-radius: 12px;
            padding: 0.875rem 1.25rem;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .form-control-custom:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .service-edit-block {
            background: #f8f9fa;
            border: 2px solid #ecf0f1;
            border-radius: 15px;
            padding: 1.75rem;
            margin-bottom: 1.25rem;
            transition: var(--transition);
        }

        .service-edit-block:hover {
            border-color: var(--primary-light);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.1);
        }

        /* Button Styles */
        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.875rem 2.25rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
            color: white;
        }

        .btn-success-custom {
            background: var(--success-gradient);
            border: none;
            color: white;
            padding: 0.875rem 2.25rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-success-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
            color: white;
        }

        .btn-warning-custom {
            background: var(--warning-gradient);
            border: none;
            color: white;
            padding: 0.875rem 2.25rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-warning-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
            color: white;
        }

        .btn-outline-custom {
            background: transparent;
            border: 2px solid var(--primary-light);
            color: var(--primary-light);
            padding: 0.75rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline-custom:hover {
            background: var(--primary-light);
            color: white;
            transform: translateY(-2px);
        }

        .btn-sm-custom {
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
        }

        .remove-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 0.625rem 1rem;
            border-radius: 10px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remove-btn:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        .remove-btn i {
            font-size: 1.125rem;
        }

        .modal-footer {
            border: none;
            padding: 1.75rem 2.5rem;
            background: #f8f9fa;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
            display: block;
        }

        .empty-state h4 {
            color: var(--primary-dark);
            margin-bottom: 0.75rem;
        }

        /* Loading Animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .page-title {
                font-size: 1.75rem;
            }

            .page-title i {
                font-size: 2rem;
            }

            .stat-number {
                font-size: 2.25rem;
            }

           .dept-acronym i {
                color: white !important;
                font-size: 1.75rem;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem 0;
            }

            .page-title {
                font-size: 1.5rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .btn-add-dept {
                width: 100%;
                justify-content: center;
                margin-top: 1rem;
            }

            .stats-overview {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .search-section {
                padding: 1.5rem;
            }

            .btn-clear {
                margin-top: 1rem;
            }

            .dept-card-wrapper {
                margin-bottom: 1.5rem;
            }

            .dept-card-header {
                padding: 1.5rem;
            }

            .dept-acronym {
                font-size: 1.5rem;
            }

            .dept-card-body {
                padding: 1.5rem;
                min-height: auto;
            }

            .modal-body {
                padding: 1.75rem;
            }

            .modal-header {
                padding: 1.5rem;
            }

            .modal-footer {
                padding: 1.5rem;
                flex-direction: column;
            }

            .modal-footer .btn {
                width: 100%;
            }

            .service-edit-block {
                padding: 1.25rem;
            }
        }

        @media (max-width: 576px) {
            .page-header {
                padding: 1.25rem 0;
            }

            .page-title {
                font-size: 1.25rem;
            }

            .page-title i {
                font-size: 1.5rem;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                font-size: 1.75rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .dept-acronym {
                font-size: 1.35rem;
            }

            .dept-acronym i {
                color: white !important;
                font-size: 1.75rem;
            }

            .service-badge {
                font-size: 0.8rem;
                padding: 0.4rem 0.9rem;
            }

            .modal-header h5 {
                font-size: 1.25rem;
            }

            .info-label {
                font-size: 1rem;
            }

            .service-name {
                font-size: 1rem;
            }
        }

        /* Tablet Landscape */
        @media (min-width: 768px) and (max-width: 1024px) {
            .dept-card-wrapper {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="page-title">
                    <i class='bx bx-buildings'></i>
                    <span>Department Management</span>
                </h3>
                <button class="btn btn-add-dept" data-toggle="modal" data-target="#addModal">
                    <i class='bx bx-plus-circle'></i>
                    Add Department
                </button>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class='bx bx-buildings'></i>
                </div>
                <h2 class="stat-number"><?= count($departments) ?></h2>
                <p class="stat-label">Total Departments</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class='bx bx-list-ul'></i>
                </div>
                <h2 class="stat-number"><?= array_sum(array_map(function($d) use ($serviceMap) { return isset($serviceMap[$d['id']]) ? count($serviceMap[$d['id']]) : 0; }, $departments)) ?></h2>
                <p class="stat-label">Total Services</p>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <div class="row align-items-center">
                <div class="col-md-9 mb-3 mb-md-0">
                    <div class="search-wrapper">
                        <i class='bx bx-search search-icon'></i>
                        <input type="text" class="search-input" id="searchInput" placeholder="Search departments, services, or descriptions...">
                    </div>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-clear" id="clearFilters">
                        <i class='bx bx-x'></i>
                        Clear Search
                    </button>
                </div>
            </div>
        </div>

        <!-- Department Cards -->
        <div class="row" id="departmentGrid">
            <?php foreach ($departments as $d): 
                $searchText = strtolower($d['name'] . ' ' . $d['acronym'] . ' ' . $d['description'] . ' ' . $d['services']);
                $services = array_filter(array_map('trim', explode(',', $d['services'])));
            ?>
            <div class="col-lg-4 col-md-6 dept-card-wrapper" data-search="<?= htmlspecialchars($searchText) ?>">
                <div class="dept-card" data-toggle="modal" data-target="#viewModal<?= $d['id'] ?>">
                    <div class="dept-card-header">
                        <h4 class="dept-acronym">
                            <i class='bx bx-building-house'></i>
                            <?= htmlspecialchars($d['acronym']) ?>
                        </h4>
                        <p class="dept-name"><?= htmlspecialchars($d['name']) ?></p>
                    </div>
                    <div class="dept-card-body">
                        <?php if ($services): ?>
                            <div>
                                <?php foreach (array_slice($services, 0, 3) as $s): ?>
                                    <span class="service-badge"><?= htmlspecialchars($s) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($services) > 3): ?>
                                    <span class="service-badge">+<?= count($services) - 3 ?> more</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-services">
                                <i class='bx bx-info-circle'></i>
                                No services available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- View Modal -->
            <div class="modal fade" id="viewModal<?= $d['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header primary">
                            <h5 class="modal-title">
                                <i class='bx bx-info-circle'></i>
                                <?= htmlspecialchars($d['name']) ?>
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-4">
                                <p class="info-label">
                                    <i class='bx bx-detail'></i>
                                    Description
                                </p>
                                <p><?= htmlspecialchars($d['description']) ?: 'No description provided.' ?></p>
                            </div>

                            <div>
                                <p class="info-label">
                                    <i class='bx bx-list-check'></i>
                                    Services & Requirements
                                </p>
                                <?php if (isset($serviceMap[$d['id']])): ?>
                                    <?php foreach ($serviceMap[$d['id']] as $svc): ?>
                                        <div class="service-item">
                                            <div class="service-name">
                                                <i class='bx bx-check-circle'></i>
                                                <?= htmlspecialchars($svc['name']) ?>
                                            </div>
                                            <?php if (!empty($svc['requirements'])): ?>
                                                <ul class="requirement-list">
                                                    <?php foreach ($svc['requirements'] as $req): ?>
                                                        <li>
                                                            <i class='bx bx-right-arrow-alt'></i>
                                                            <span><?= htmlspecialchars($req) ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p class="text-muted mb-0"><em>No specific requirements</em></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class='bx bx-package'></i>
                                        <h4>No Services Available</h4>
                                        <p>This department has no services listed yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-custom" data-dismiss="modal">
                                <i class='bx bx-x'></i>
                                Close
                            </button>
                            <button class="btn btn-warning-custom" data-dismiss="modal" data-toggle="modal" data-target="#editModal<?= $d['id'] ?>">
                                <i class='bx bx-edit'></i>
                                Edit Department
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?= $d['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content" method="post" action="ajax_update_department.php">
                        <div class="modal-header warning">
                            <h5 class="modal-title">
                                <i class='bx bx-edit'></i>
                                Edit Department
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="department_id" value="<?= $d['id'] ?>">

                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-building'></i>
                                    Department Name
                                </label>
                                <input name="name" class="form-control form-control-custom" value="<?= htmlspecialchars($d['name']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-tag'></i>
                                    Acronym
                                </label>
                                <input name="acronym" class="form-control form-control-custom" value="<?= htmlspecialchars($d['acronym']) ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-detail'></i>
                                    Description
                                </label>
                                <textarea name="description" class="form-control form-control-custom" rows="3"><?= htmlspecialchars($d['description']) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-list-ul'></i>
                                    Services & Requirements
                                </label>
                                <div class="service-edit-area">
                                    <?php if (isset($serviceMap[$d['id']])): ?>
                                        <?php foreach ($serviceMap[$d['id']] as $svcId => $svc): ?>
                                            <div class="service-edit-block">
                                                <input type="hidden" name="service_ids[]" value="<?= $svcId ?>">
                                                <input type="text" name="service_names[]" class="form-control form-control-custom mb-3" value="<?= htmlspecialchars($svc['name']) ?>" placeholder="Service Name" required>
                                                
                                                <div class="requirement-group">
                                                    <?php if (!empty($svc['requirements'])): ?>
                                                        <?php foreach ($svc['requirements'] as $req): ?>
                                                            <div class="input-group mb-2">
                                                                <input type="text" name="requirements[<?= $svcId ?>][]" class="form-control form-control-custom" value="<?= htmlspecialchars($req) ?>" placeholder="Requirement">
                                                                <div class="input-group-append">
                                                                    <button type="button" class="btn remove-btn remove-req">
                                                                        <i class='bx bx-x'></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                    <div class="input-group mb-2">
                                                        <input type="text" name="requirements[<?= $svcId ?>][]" class="form-control form-control-custom" placeholder="Add new requirement (optional)">
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn remove-btn remove-req">
                                                                <i class='bx bx-x'></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-outline-custom btn-sm-custom add-req">
                                                    <i class='bx bx-plus'></i>
                                                    Add Requirement
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-success-custom btn-sm-custom mt-2" id="addNewServiceBtn<?= $d['id'] ?>">
                                    <i class='bx bx-plus-circle'></i>
                                    Add New Service
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-custom" data-dismiss="modal">
                                <i class='bx bx-x'></i>
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-warning-custom">
                                <i class='bx bx-save'></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php endforeach; ?>
        </div>

        <?php if (empty($departments)): ?>
            <div class="empty-state">
                <i class='bx bx-building'></i>
                <h4>No Departments Yet</h4>
                <p>Start by adding your first department using the button above.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="addForm" class="modal-content">
                <div class="modal-header success">
                    <h5 class="modal-title">
                        <i class='bx bx-plus-circle'></i>
                        Add New Department
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">
                            <i class='bx bx-building'></i>
                            Department Name
                        </label>
                        <input name="name" class="form-control form-control-custom" placeholder="Enter department name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class='bx bx-tag'></i>
                            Acronym
                        </label>
                        <input name="acronym" class="form-control form-control-custom" placeholder="e.g., DOH, DILG">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class='bx bx-detail'></i>
                            Description
                        </label>
                        <textarea name="description" class="form-control form-control-custom" rows="3" placeholder="Brief description of the department"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class='bx bx-list-ul'></i>
                            Services Offered
                        </label>
                        <div id="serviceFields">
                            <div class="service-group mb-3">
                                <div class="input-group mb-2">
                                    <input type="text" name="services[]" class="form-control form-control-custom" placeholder="Enter a service" required>
                                    <div class="input-group-append">
                                        <button type="button" class="btn remove-btn removeService">
                                            <i class='bx bx-x'></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" name="requirements[]" class="form-control form-control-custom" placeholder="Requirement for above service (optional)">
                            </div>
                        </div>
                        <button type="button" id="addService" class="btn btn-outline-custom btn-sm-custom">
                            <i class='bx bx-plus'></i>
                            Add Another Service
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-dismiss="modal">
                        <i class='bx bx-x'></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success-custom">
                        <i class='bx bx-check-circle'></i>
                        Add Department
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Add Service in Add Modal
        $(document).on('click', '#addService', function() {
            const group = `
                <div class="service-group mb-3">
                    <div class="input-group mb-2">
                        <input type="text" name="services[]" class="form-control form-control-custom" placeholder="Enter a service" required>
                        <div class="input-group-append">
                            <button type="button" class="btn remove-btn removeService">
                                <i class='bx bx-x'></i>
                            </button>
                        </div>
                    </div>
                    <input type="text" name="requirements[]" class="form-control form-control-custom" placeholder="Requirement for above service (optional)">
                </div>`;
            $('#serviceFields').append(group);
        });

        // Remove Service
        $(document).on('click', '.removeService', function() {
            if ($('.service-group').length > 1) {
                $(this).closest('.service-group').remove();
            }
        });

        // Remove Requirement
        $(document).on('click', '.remove-req', function() {
            $(this).closest('.input-group').remove();
        });

        // Add Requirement in Edit Modal
        $(document).on('click', '.add-req', function() {
            const reqGroup = $(this).siblings('.requirement-group');
            const serviceId = $(this).closest('.service-edit-block').find('input[name="service_ids[]"]').val();
            const reqField = `
                <div class="input-group mb-2">
                    <input type="text" name="requirements[${serviceId}][]" class="form-control form-control-custom" placeholder="Requirement">
                    <div class="input-group-append">
                        <button type="button" class="btn remove-btn remove-req">
                            <i class='bx bx-x'></i>
                        </button>
                    </div>
                </div>`;
            reqGroup.append(reqField);
        });

        // Add New Service in Edit Modal
        $('[id^="addNewServiceBtn"]').click(function() {
            const deptId = $(this).attr('id').replace('addNewServiceBtn', '');
            const timestamp = Date.now();
            const block = `
                <div class="service-edit-block">
                    <input type="hidden" name="service_ids[]" value="new">
                    <input type="text" name="service_names[]" class="form-control form-control-custom mb-3" placeholder="Service Name" required>
                    <div class="requirement-group">
                        <div class="input-group mb-2">
                            <input type="text" name="requirements[new_${timestamp}][]" class="form-control form-control-custom" placeholder="Requirement">
                            <div class="input-group-append">
                                <button type="button" class="btn remove-btn remove-req">
                                    <i class='bx bx-x'></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-custom btn-sm-custom add-req">
                        <i class='bx bx-plus'></i>
                        Add Requirement
                    </button>
                </div>`;
            $(this).siblings('.service-edit-area').append(block);
        });

        // Submit Add Form
        $('#addForm').submit(function(e) {
            e.preventDefault();
            if (confirm("Add this department and services?")) {
                $.post('ajax_add_department_with_services.php', $(this).serialize(), function(response) {
                    location.reload();
                }).fail(function(xhr) {
                    alert("Error: " + xhr.responseText);
                });
            }
        });

        // Search Functionality
        $('#searchInput').on('input', function() {
            const val = $(this).val().toLowerCase();
            $('.dept-card-wrapper').each(function() {
                const searchable = $(this).data('search');
                $(this).toggle(searchable.includes(val));
            });
            
            // Show empty state if no results
            if ($('.dept-card-wrapper:visible').length === 0 && val !== '') {
                if ($('#noResultsMessage').length === 0) {
                    $('#departmentGrid').append(`
                        <div class="col-12 empty-state" id="noResultsMessage">
                            <i class='bx bx-search-alt'></i>
                            <h4>No Results Found</h4>
                            <p>Try adjusting your search terms</p>
                        </div>
                    `);
                }
            } else {
                $('#noResultsMessage').remove();
            }
        });

        // Clear Filters
        $('#clearFilters').click(function() {
            $('#searchInput').val('');
            $('.dept-card-wrapper').show();
            $('#noResultsMessage').remove();
        });

        // Submit Edit Forms
        $('form[action="ajax_update_department.php"]').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            if (confirm("Save changes to this department?")) {
                $.post(form.attr('action'), form.serialize(), function(response) {
                    form.closest('.modal').modal('hide');
                    setTimeout(() => { location.reload(); }, 500);
                }).fail(function(xhr) {
                    alert("Update failed: " + xhr.responseText);
                });
            }
        });

        // Smooth scroll to top when modal closes
        $('.modal').on('hidden.bs.modal', function() {
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        });
    });
    </script>
</body>
</html>