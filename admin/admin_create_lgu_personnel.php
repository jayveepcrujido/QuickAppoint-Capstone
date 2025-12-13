<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
include '../conn.php';

// Fetch departments
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

// Fetch LGU Personnel (joined with auth to get email) - UPDATED QUERY
$stmt = $pdo->prepare("
    SELECT lp.id, lp.first_name, lp.middle_name, lp.last_name, lp.department_id, 
           lp.is_department_head, lp.created_by_personnel_id,
           a.email, d.name AS dept_name 
    FROM lgu_personnel lp
    JOIN auth a ON lp.auth_id = a.id
    LEFT JOIN departments d ON lp.department_id = d.id
    WHERE a.role = 'LGU Personnel'
    ORDER BY lp.is_department_head DESC, lp.created_at DESC
");
$stmt->execute();
$personnel = $stmt->fetchAll();

// Count department heads
$dept_heads_count = count(array_filter($personnel, fn($p) => $p['is_department_head'] == 1));
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage LGU Personnel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --danger-gradient: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8edf2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 20px 20px 20px 20px;
            box-shadow: var(--card-shadow);
            margin-top: -1rem;
        }

        .page-header h4 {
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h4 i {
            font-size: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stats-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            background: var(--primary-gradient);
            color: white;
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0 0 0;
            color: #2d3748;
        }

        .stats-card p {
            color: #718096;
            margin: 0;
            font-size: 0.9rem;
        }

        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header-custom {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-header-custom h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add {
            background: white;
            color: #667eea;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
            color: #667eea;
        }

        .search-filter-bar {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            border-radius: 50px;
            padding: 0.75rem 1.25rem 0.75rem 3rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .table-container {
            padding: 2rem;
        }

        .custom-table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table thead th {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border: none;
            white-space: nowrap;
        }

        .custom-table thead th:first-child {
            border-radius: 10px 0 0 0;
        }

        .custom-table thead th:last-child {
            border-radius: 0 10px 0 0;
        }

        .custom-table tbody tr {
            transition: all 0.3s ease;
            background: white;
        }

        .custom-table tbody tr:hover {
            background: #f7fafc;
            transform: scale(1.01);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .custom-table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        .badge-dept {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
        }

        /* NEW: Badge for department head */
        .badge-head {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-left: 0.5rem;
        }

        .badge-created-by {
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .badge-admin {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-personnel {
            background: #d1fae5;
            color: #065f46;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 2px solid;
            margin: 0 0.25rem;
        }

        .btn-edit {
            background: white;
            color: #3182ce;
            border-color: #3182ce;
        }

        .btn-edit:hover {
            background: #3182ce;
            color: white;
            transform: scale(1.1);
        }

        .btn-delete {
            background: white;
            color: #e53e3e;
            border-color: #e53e3e;
        }

        .btn-delete:hover {
            background: #e53e3e;
            color: white;
            transform: scale(1.1);
        }

        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 1.5rem 2rem;
        }

        .modal-header .close {
            color: white;
            opacity: 1;
            font-size: 1.5rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control-custom {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* NEW: Checkbox styling */
        .custom-control-label {
            cursor: pointer;
            user-select: none;
        }

        .dept-head-checkbox {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .alert-info-custom {
            background: #e0f2fe;
            border: 2px solid #7dd3fc;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: start;
            gap: 0.75rem;
        }

        .alert-info-custom i {
            color: #0284c7;
            font-size: 1.25rem;
            margin-top: 0.125rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem 0;
                margin-bottom: 1.5rem;
            }

            .page-header h4 {
                font-size: 1.25rem;
            }

            .stats-card {
                margin-bottom: 1rem;
            }

            .card-header-custom {
                padding: 1rem 1.5rem;
            }

            .search-filter-bar {
                padding: 1rem 1.5rem;
            }

            .table-container {
                padding: 0;
            }

            .table-responsive {
                border-radius: 0;
            }

            .custom-table {
                font-size: 0.875rem;
            }

            .custom-table thead {
                display: none;
            }

            .custom-table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border-radius: 10px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .custom-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 1rem;
                border: none;
                border-bottom: 1px solid #e2e8f0;
            }

            .custom-table tbody td:last-child {
                border-bottom: none;
            }

            .custom-table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #4a5568;
            }

            .custom-table tbody td:last-child {
                justify-content: center;
            }

            .modal-body {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .btn-add {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h4><i class='bx bx-user-circle'></i> Manage LGU Personnel</h4>
        </div>
    </div>

    <div class="container">
        <!-- Stats Card - UPDATED -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class='bx bx-group'></i>
                        </div>
                        <div class="ml-3">
                            <h3><?= count($personnel) ?></h3>
                            <p>Total Personnel</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="icon" style="background: var(--success-gradient);">
                            <i class='bx bx-buildings'></i>
                        </div>
                        <div class="ml-3">
                            <h3><?= count($departments) ?></h3>
                            <p>Departments</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class='bx bx-crown'></i>
                        </div>
                        <div class="ml-3">
                            <h3><?= $dept_heads_count ?></h3>
                            <p>Department Heads</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="icon" style="background: var(--danger-gradient);">
                            <i class='bx bx-user-check'></i>
                        </div>
                        <div class="ml-3">
                            <h3><?= count(array_filter($personnel, fn($p) => $p['dept_name'])) ?></h3>
                            <p>Assigned</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table Card -->
        <div class="main-card">
            <div class="card-header-custom">
                <h5><i class='bx bx-list-ul'></i> Personnel Directory</h5>
                <button class="btn btn-add" data-toggle="modal" data-target="#addModal">
                    <i class='bx bx-plus-circle'></i> Add New Personnel
                </button>
            </div>

            <div class="search-filter-bar">
                <div class="search-box">
                    <i class='bx bx-search'></i>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by name, email, or department...">
                </div>
            </div>

            <div class="table-container">
                <div class="table-responsive">
                    <table class="table custom-table" id="personnelTable">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Email Address</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($personnel as $p): ?>
                                <tr data-id="<?= $p['id'] ?>">
                                    <td data-label="Full Name">
                                        <strong><?= htmlspecialchars("{$p['first_name']} {$p['middle_name']} {$p['last_name']}") ?></strong>
                                        <?php if ($p['is_department_head']): ?>
                                            <span class="badge-head">
                                                <i class='bx bx-crown'></i> Head
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Email">
                                        <?= htmlspecialchars($p['email']) ?>
                                    </td>
                                    <td data-label="Department">
                                        <span class="badge-dept"><?= htmlspecialchars($p['dept_name']) ?></span>
                                    </td>
                                    <td data-label="Role">
                                        <?php if ($p['is_department_head']): ?>
                                            <span class="badge-created-by badge-admin">
                                                <i class='bx bx-shield-alt'></i> Department Head
                                            </span>
                                        <?php elseif ($p['created_by_personnel_id']): ?>
                                            <span class="badge-created-by badge-personnel">
                                                <i class='bx bx-user'></i> Co-Personnel
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-created-by" style="background: #e0e7ff; color: #4338ca;">
                                                <i class='bx bx-user-check'></i> Personnel
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class="text-center">
                                        <button class="btn btn-action btn-edit editBtn"
                                            title="Edit Personnel"
                                            data-id="<?= $p['id'] ?>"
                                            data-first="<?= htmlspecialchars($p['first_name']) ?>"
                                            data-middle="<?= htmlspecialchars($p['middle_name']) ?>"
                                            data-last="<?= htmlspecialchars($p['last_name']) ?>"
                                            data-email="<?= htmlspecialchars($p['email']) ?>"
                                            data-dept="<?= $p['department_id'] ?>"
                                            data-is-head="<?= $p['is_department_head'] ?>">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                        <button class="btn btn-action btn-delete deleteBtn" 
                                                data-id="<?= $p['id'] ?>"
                                                data-name="<?= htmlspecialchars("{$p['first_name']} {$p['last_name']}") ?>"
                                                title="Delete Personnel">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($personnel)): ?>
                        <div class="empty-state">
                            <i class='bx bx-user-x'></i>
                            <h5>No Personnel Found</h5>
                            <p>Start by adding your first LGU personnel member.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal - UPDATED -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="addForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel"><i class='bx bx-user-plus'></i> Add New Personnel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>First Name <span class="text-danger">*</span></label>
                        <input name="first_name" class="form-control form-control-custom" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input name="middle_name" class="form-control form-control-custom" placeholder="Enter middle name">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="text-danger">*</span></label>
                        <input name="last_name" class="form-control form-control-custom" placeholder="Enter last name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input name="email" type="email" class="form-control form-control-custom" placeholder="Enter email address" required>
                    </div>
                    <div class="form-group">
                        <label>Password <span class="text-danger">*</span></label>
                        <input name="password" type="password" class="form-control form-control-custom" placeholder="Enter password" minlength="6" required>
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="form-group">
                        <label>Department <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-control form-control-custom" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="dept-head-checkbox">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is_department_head" name="is_department_head">
                            <label class="custom-control-label" for="is_department_head">
                                <strong><i class='bx bx-crown'></i> Set as Department Head</strong>
                            </label>
                        </div>
                        <small class="form-text text-muted mt-2">
                            <i class='bx bx-info-circle'></i> Department heads can create and manage co-personnel within their department
                        </small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-submit">
                        <i class='bx bx-save'></i> Add Personnel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal - UPDATED -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form id="editForm" class="modal-content">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-edit'></i> Edit Personnel</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert-info-custom" id="edit-info-box" style="display: none;">
                        <i class='bx bx-info-circle'></i>
                        <div>
                            <strong>Note:</strong>
                            <p class="mb-0" style="font-size: 0.9rem;" id="edit-info-text"></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>First Name <span class="text-danger">*</span></label>
                        <input name="first_name" class="form-control form-control-custom" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input name="middle_name" class="form-control form-control-custom" placeholder="Enter middle name">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="text-danger">*</span></label>
                        <input name="last_name" class="form-control form-control-custom" placeholder="Enter last name" required>
                    </div>
                    <div class="form-group">
                        <label>Department <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-control form-control-custom" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input name="email" type="email" class="form-control form-control-custom" placeholder="Enter email address" required>
                    </div>
                    
                    <div class="dept-head-checkbox">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="edit_is_department_head" name="is_department_head">
                            <label class="custom-control-label" for="edit_is_department_head">
                                <strong><i class='bx bx-crown'></i> Department Head Status</strong>
                            </label>
                        </div>
                        <small class="form-text text-muted mt-2">
                            <i class='bx bx-info-circle'></i> Check to grant or maintain department head privileges
                        </small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-submit">
                        <i class='bx bx-save'></i> Update Personnel
                    </button>
                </div>
            </form>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality
$("#searchInput").on("keyup", function() {
    const value = $(this).val().toLowerCase();
    $("#personnelTable tbody tr").filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
});

// Function to reload just the personnel table
function loadPersonnelTable() {
    $.ajax({
        url: 'admin_create_lgu_personnel.php',
        method: 'GET',
        success: function(html) {
            // Extract and update the table
            const newTable = $(html).find('#personnelTable tbody').html();
            $('#personnelTable tbody').html(newTable);
            
            // Update stats cards
            const newStats = $(html).find('.stats-card h3');
            $('.stats-card h3').each(function(index) {
                $(this).text($(newStats[index]).text());
            });
        },
        error: function() {
            console.error('Failed to reload table');
            // Fallback to full page reload if dynamic load fails
            location.reload();
        }
    });
}

// ADD with validation and dynamic update
$("#addForm").submit(function(e) {
    e.preventDefault();
    
    const isDeptHead = $('#is_department_head').is(':checked');
    
    let confirmMsg = "Are you sure you want to add this personnel";
    if (isDeptHead) {
        confirmMsg += " as a Department Head";
    }
    confirmMsg += "?";
    
    if (confirm(confirmMsg)) {
        $.ajax({
            url: "ajax/ajax_create_personnel.php",
            method: "POST",
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close modal properly and remove backdrop
                    $('#addModal').modal('hide');
                    $('#addForm')[0].reset();
                    
                    // Remove modal backdrop and reset body
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    $('body').css('padding-right', '');
                    
                    alert(response.message);
                    
                    // Now reload the table
                    loadPersonnelTable();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    alert('Error: ' + response.message);
                } catch(e) {
                    console.error('Raw error:', xhr.responseText);
                    alert('An error occurred. Check console for details.');
                }
            }
        });
    }
});

// FILL EDIT MODAL
$(document).on("click", ".editBtn", function() {
    const btn = $(this);
    const isDeptHead = btn.data("is-head") == 1;
    
    $("#editForm [name=id]").val(btn.data("id"));
    $("#editForm [name=first_name]").val(btn.data("first"));
    $("#editForm [name=middle_name]").val(btn.data("middle"));
    $("#editForm [name=last_name]").val(btn.data("last"));
    $("#editForm [name=email]").val(btn.data("email"));
    $("#editForm [name=department_id]").val(btn.data("dept"));
    
    // Set department head checkbox
    $("#edit_is_department_head").prop('checked', isDeptHead);
    
    // Show info if department head
    if (isDeptHead) {
        $("#edit-info-box").show();
        $("#edit-info-text").html("This personnel is currently a <strong>Department Head</strong>. Unchecking will remove their ability to manage co-personnel.");
    } else {
        $("#edit-info-box").hide();
    }
    
    $("#editModal").modal("show");
});

// EDIT with validation and dynamic update
$("#editForm").submit(function(e) {
    e.preventDefault();
    
    const isDeptHead = $('#edit_is_department_head').is(':checked');
    let confirmMsg = "Are you sure you want to update this personnel";
    if (isDeptHead) {
        confirmMsg += " with Department Head status";
    }
    confirmMsg += "?";
    
    if (confirm(confirmMsg)) {
        $.ajax({
            url: "ajax/ajax_update_personnel.php",
            method: "POST",
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close modal properly and remove backdrop
                    $('#editModal').modal('hide');
                    
                    // Remove modal backdrop and reset body
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    $('body').css('padding-right', '');
                    
                    alert(response.message);
                    
                    // Reload the table dynamically
                    loadPersonnelTable();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    alert('Error: ' + response.message);
                } catch(e) {
                    alert('Error: ' + xhr.responseText);
                }
            }
        });
    }
});

// DELETE with dynamic update
$(document).on("click", ".deleteBtn", function() {
    const id = $(this).data("id");
    const name = $(this).data("name");
    
    if (confirm("Are you sure you want to delete " + name + "?\n\nThis will permanently remove their account and all associated data.")) {
        $.ajax({
            url: "ajax/ajax_delete_personnel.php",
            method: "POST",
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    
                    // Reload the table dynamically (no modal to close for delete)
                    loadPersonnelTable();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    alert('Error: ' + response.message);
                } catch(e) {
                    alert('Error: ' + xhr.responseText);
                }
            }
        });
    }
});
</script>
</body>
</html>