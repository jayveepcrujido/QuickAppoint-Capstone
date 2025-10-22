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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resident Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border-radius: 15px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(78, 115, 223, 0.3);
        }

        .page-header h4 {
            font-weight: 700;
            margin: 0;
            font-size: 1.75rem;
        }

        .page-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.95;
            font-size: 1rem;
        }

        .stats-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            flex: 1;
            min-width: 200px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #4e73df;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(78, 115, 223, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4e73df;
            font-size: 24px;
            margin-bottom: 1rem;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }

        .stat-card .stat-label {
            color: #718096;
            font-size: 0.875rem;
            margin: 0;
        }

        .main-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 1.5rem;
            border: none;
        }

        .card-header-custom h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .search-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            border-radius: 10px;
            padding-left: 2.5rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            z-index: 10;
        }

        /* Modern Table Styles */
        .table-modern {
            margin: 0;
        }

        .table-modern thead th {
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 1rem;
            white-space: nowrap;
        }

        .table-modern tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-modern tbody tr:hover {
            background: #f7fafc;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .table-modern tbody td {
            padding: 1rem;
            vertical-align: middle;
            border: none;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95rem;
        }

        .user-details small {
            color: #718096;
        }

        .badge-custom {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-email {
            background: #e6f2ff;
            color: #2b6cb0;
        }

        .badge-date {
            background: #f0f4ff;
            color: #5a67d8;
        }

        .btn-delete {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 62, 62, 0.4);
            color: white;
        }

        .btn-delete:active {
            transform: translateY(0);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i {
            font-size: 5rem;
            color: #cbd5e0;
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #a0aec0;
        }

        /* Mobile Card View */
        #mobileCards {
            display: none;
            padding: 1rem;
        }

        .mobile-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #4e73df;
            transition: all 0.3s ease;
        }

        .mobile-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .mobile-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .mobile-card-body {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .mobile-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-card-label {
            color: #718096;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .mobile-card-value {
            color: #2d3748;
            font-weight: 500;
            text-align: right;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-header h4 {
                font-size: 1.5rem;
            }

            .stat-card {
                min-width: calc(50% - 0.5rem);
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.25rem;
            }

            .page-header h4 {
                font-size: 1.25rem;
            }

            .page-header p {
                font-size: 0.875rem;
            }

            .stat-card {
                min-width: 100%;
            }

            .table-responsive {
                display: none;
            }

            #mobileCards {
                display: block;
            }

            .search-container {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 0.5rem;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .mobile-card {
                padding: 1rem;
            }
        }

        /* Loading Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .main-card, .stat-card, .mobile-card {
            animation: fadeIn 0.5s ease-in;
        }
    </style>
</head>
<body class="p-3 p-md-4">
<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h4><i class='bx bx-group'></i> Manage Resident Accounts</h4>
        <p>View and manage all registered resident accounts in the system</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon">
                <i class='bx bx-user'></i>
            </div>
            <h3 class="stat-number"><?= count($residents) ?></h3>
            <p class="stat-label">Total Residents</p>
        </div>
    </div>

    <!-- Search Box -->
    <div class="search-container">
        <div class="search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email...">
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="main-card">
        <div class="card-header-custom">
            <h5><i class='bx bx-table'></i> Resident Accounts List</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($residents)): ?>
                <!-- Desktop Table View -->
                <div class="table-responsive">
                    <table class="table table-modern" id="residentsTable">
                        <thead>
                            <tr>
                                <th>Resident</th>
                                <th>Email Address</th>
                                <th>Date Registered</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($residents as $resident): 
                                $fullName = trim($resident['first_name'] . ' ' . $resident['middle_name'] . ' ' . $resident['last_name']);
                                $initials = strtoupper(substr($resident['first_name'], 0, 1) . substr($resident['last_name'], 0, 1));
                            ?>
                                <tr class="resident-row">
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar"><?= $initials ?></div>
                                            <div class="user-details">
                                                <h6><?= htmlspecialchars($fullName) ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-email">
                                            <i class='bx bx-envelope'></i>
                                            <?= htmlspecialchars($resident['email']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-date">
                                            <i class='bx bx-calendar'></i>
                                            <?= date('M j, Y', strtotime($resident['created_at'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-delete btn-sm" onclick="deleteResident(<?= $resident['id'] ?>, '<?= htmlspecialchars($fullName) ?>')">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div id="mobileCards">
                    <?php foreach ($residents as $resident): 
                        $fullName = trim($resident['first_name'] . ' ' . $resident['middle_name'] . ' ' . $resident['last_name']);
                        $initials = strtoupper(substr($resident['first_name'], 0, 1) . substr($resident['last_name'], 0, 1));
                    ?>
                        <div class="mobile-card resident-card" data-name="<?= htmlspecialchars(strtolower($fullName)) ?>" data-email="<?= htmlspecialchars(strtolower($resident['email'])) ?>">
                            <div class="mobile-card-header">
                                <div class="user-avatar"><?= $initials ?></div>
                                <div class="user-details flex-grow-1">
                                    <h6><?= htmlspecialchars($fullName) ?></h6>
                                </div>
                            </div>
                            <div class="mobile-card-body">
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">
                                        <i class='bx bx-envelope'></i> Email
                                    </span>
                                    <span class="mobile-card-value">
                                        <?= htmlspecialchars($resident['email']) ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">
                                        <i class='bx bx-calendar'></i> Registered
                                    </span>
                                    <span class="mobile-card-value">
                                        <?= date('M j, Y', strtotime($resident['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row mt-2">
                                    <button class="btn btn-delete btn-block" onclick="deleteResident(<?= $resident['id'] ?>, '<?= htmlspecialchars($fullName) ?>')">
                                        <i class='bx bx-trash'></i> Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-user-x'></i>
                    <h5>No Resident Accounts Found</h5>
                    <p>There are currently no registered resident accounts in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class='bx bx-trash'></i> Confirm Deletion
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center py-4">
                <i class='bx bx-error-circle' style="font-size: 4rem; color: #e53e3e;"></i>
                <h5 class="mt-3 mb-2">Are you sure?</h5>
                <p class="text-muted mb-0">You are about to delete the account for:</p>
                <p class="font-weight-bold" id="residentName"></p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center border-0">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">
                    <i class='bx bx-x'></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDelete" style="border-radius: 8px;">
                    <i class='bx bx-trash'></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let deleteId = null;

function deleteResident(id, name) {
    deleteId = id;
    $('#residentName').text(name);
    $('#deleteModal').modal('show');
}

$('#confirmDelete').click(function() {
    if (deleteId) {
        $.post('admin_manage_residents_accounts.php', { delete_id: deleteId }, function(response) {
            if (response.status === 'success') {
                $('#deleteModal').modal('hide');
                
                // Show success message
                $('body').append(`
                    <div class="alert alert-success alert-dismissible fade show position-fixed" 
                         style="top: 20px; right: 20px; z-index: 9999; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.2);">
                        <i class='bx bx-check-circle'></i> Resident account deleted successfully!
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                `);
                
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                alert('Failed to delete resident account.');
            }
        }, 'json').fail(function() {
            alert('An error occurred. Please try again.');
        });
    }
});

// Search functionality
$('#searchInput').on('keyup', function() {
    const value = $(this).val().toLowerCase().trim();
    
    if (value === '') {
        // Show all items when search is cleared
        $('#residentsTable tbody tr').show();
        $('.mobile-card').show();
    } else {
        // Filter table rows
        $('#residentsTable tbody tr').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(value) > -1);
        });
        
        // Filter mobile cards
        $('.mobile-card').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(value) > -1);
        });
    }
});
</script>
</body>
</html>