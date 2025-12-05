<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';

// Get personnel department using auth_id
$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$personnel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personnel) {
    echo "<div class='alert alert-danger'>Invalid user.</div>";
    exit();
}

$departmentId = $personnel['department_id'];

// Fetch appointments with resident info
$stmt = $pdo->prepare("
    SELECT a.id, a.transaction_id, a.status, a.scheduled_for, a.reason, a.requested_at,
           r.first_name, r.last_name
    FROM appointments a
    JOIN residents r ON a.resident_id = r.id
    WHERE a.department_id = ?
    ORDER BY a.scheduled_for DESC
");
$stmt->execute([$departmentId]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 1rem 0;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-top: -1.5rem;
        }

        .page-header h2 {
            color: white;
            font-weight: 600;
            margin: 0;
            font-size: 1.5rem;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0.5rem 0 0 0;
            font-size: 0.9rem;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .filter-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        .filter-label i {
            margin-right: 0.5rem;
            color: #3498db;
        }

        #statusFilter {
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        #statusFilter:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        /* Desktop Table View */
        .desktop-table {
            display: none;
        }

        #appointmentsTable {
            margin-bottom: 0;
            border: none;
        }

        #appointmentsTable thead th {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        #appointmentsTable tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f3f7;
        }

        #appointmentsTable tbody tr:hover {
            background: linear-gradient(to right, #f8f9fa 0%, #e9ecef 100%);
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        #appointmentsTable tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #4a5568;
            font-size: 0.9rem;
            border: none;
        }

        .transaction-id {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2c3e50;
            background: #ecf0f1;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            display: inline-block;
        }

        .resident-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .reason-text {
            color: #5a6c7d;
            font-style: italic;
        }

        .scheduled-time {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .scheduled-time i {
            color: #3498db;
        }

        .badge {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }

        .badge-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }

        .badge-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }

        .requested-date {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        /* Mobile Card View */
        .mobile-cards {
            display: block;
        }

        .appointment-mobile-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .appointment-mobile-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }

        .mobile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f3f7;
        }

        .mobile-card-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .mobile-card-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 110px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mobile-card-label i {
            color: #3498db;
            width: 20px;
            text-align: center;
        }

        .mobile-card-value {
            color: #4a5568;
            flex: 1;
        }

        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state p {
            font-size: 1rem;
            margin: 0;
        }

        /* Tablet breakpoint (768px and up) */
        @media (min-width: 768px) {
            body {
                padding: 1.5rem 0;
            }

            .page-header h2 {
                font-size: 1.6rem;
            }

            .empty-state {
                padding: 3rem;
            }

            .empty-state i {
                font-size: 3.5rem;
            }

            .empty-state p {
                font-size: 1.05rem;
            }
        }

        /* Desktop breakpoint (992px and up) */
        @media (min-width: 992px) {
            body {
                padding: 2rem 0;
            }

            .page-header {
                padding: 2rem;
            }

            .page-header h2 {
                font-size: 1.8rem;
            }

            .page-header p {
                font-size: 0.95rem;
            }

            .filter-section {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .filter-label {
                margin-bottom: 0;
                margin-right: 1rem;
            }

            #statusFilter {
                width: auto;
                min-width: 200px;
            }

            .mobile-cards {
                display: none;
            }

            .desktop-table {
                display: block;
            }

            .empty-state {
                padding: 3rem;
            }

            .empty-state i {
                font-size: 4rem;
            }

            .empty-state p {
                font-size: 1.1rem;
            }
        }

        /* Large desktop (1200px and up) */
        @media (min-width: 1200px) {
            .table-responsive {
                border-radius: 12px;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid main-container px-3 px-md-4">
    <div class="page-header">
        <h2><i class="fas fa-calendar-check"></i> Department Appointments</h2>
        <p>Manage and track all appointments for your department</p>
    </div>

    <div class="filter-section">
        <div class="filter-label">
            <i class="fas fa-filter"></i>
            <span>Filter by Status:</span>
        </div>
        <select id="statusFilter" class="form-control">
            <option value="">All Appointments</option>
            <option value="Pending">Pending</option>
            <option value="Completed">Completed</option>
        </select>
    </div>

    <!-- Desktop Table View -->
    <div class="table-card desktop-table">
        <div class="table-responsive">
            <table id="appointmentsTable" class="table">
                <thead>
                    <tr>
                        <th class="text-center">Transaction ID</th>
                        <th class="text-center">Resident Name</th>
                        <th class="text-center">Reason</th>
                        <th class="text-center">Scheduled For</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Requested At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($appointments)): ?>
                        <?php foreach ($appointments as $row): ?>
                            <?php
                                $status = htmlspecialchars($row['status']);
                                $badgeClass = $status === 'Pending' ? 'warning' : 
                                              ($status === 'Completed' ? 'success' : 'secondary');
                            ?>
                            <tr data-status="<?= $status ?>">
                                <td class="text-center">
                                    <span class="transaction-id"><?= htmlspecialchars($row['transaction_id']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="resident-name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="reason-text"><?= htmlspecialchars($row['reason'] ?? 'N/A') ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="scheduled-time">
                                        <i class="far fa-clock"></i>
                                        <span>
                                            <?= $row['scheduled_for'] 
                                                ? date('F j, Y • g:i A', strtotime($row['scheduled_for'])) 
                                                : 'Not Scheduled'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-<?= $badgeClass ?>"><?= $status ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="requested-date"><?= date('M d, Y g:i A', strtotime($row['requested_at'])) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="far fa-calendar-times"></i>
                                    <p>No appointments found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile Card View -->
    <div class="mobile-cards">
        <?php if (!empty($appointments)): ?>
            <?php foreach ($appointments as $row): ?>
                <?php
                    $status = htmlspecialchars($row['status']);
                    $badgeClass = $status === 'Pending' ? 'warning' : 
                                  ($status === 'Completed' ? 'success' : 'secondary');
                ?>
                <div class="appointment-mobile-card" data-status="<?= $status ?>">
                    <div class="mobile-card-header">
                        <span class="transaction-id"><?= htmlspecialchars($row['transaction_id']) ?></span>
                        <span class="badge badge-<?= $badgeClass ?>"><?= $status ?></span>
                    </div>
                    
                    <div class="mobile-card-row">
                        <div class="mobile-card-label">
                            <i class="fas fa-user"></i>
                            <span>Name:</span>
                        </div>
                        <div class="mobile-card-value resident-name">
                            <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                        </div>
                    </div>

                    <div class="mobile-card-row">
                        <div class="mobile-card-label">
                            <i class="fas fa-sticky-note"></i>
                            <span>Reason:</span>
                        </div>
                        <div class="mobile-card-value reason-text">
                            <?= htmlspecialchars($row['reason'] ?? 'N/A') ?>
                        </div>
                    </div>

                    <div class="mobile-card-row">
                        <div class="mobile-card-label">
                            <i class="far fa-clock"></i>
                            <span>Scheduled:</span>
                        </div>
                        <div class="mobile-card-value">
                            <?= $row['scheduled_for'] 
                                ? date('M j, Y • g:i A', strtotime($row['scheduled_for'])) 
                                : 'Not Scheduled'; ?>
                        </div>
                    </div>

                    <div class="mobile-card-row">
                        <div class="mobile-card-label">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Requested:</span>
                        </div>
                        <div class="mobile-card-value requested-date">
                            <?= date('M d, Y g:i A', strtotime($row['requested_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-calendar-times"></i>
                <p>No appointments found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
  'use strict';
  
  // Create unique namespace for this page
  const NAMESPACE = 'appointmentStatus_' + Date.now();
  console.log('Initializing appointment status page with namespace:', NAMESPACE);
  
  // Clean up previous instance if exists
  if (window.appointmentStatusCleanup) {
    console.log('Cleaning up previous appointment status instance...');
    try {
      window.appointmentStatusCleanup();
    } catch (e) {
      console.error('Error during cleanup:', e);
    }
  }
  
  let isInitialized = false;
  
  // Initialize the page
  function initializePage() {
    if (isInitialized) {
      console.log('Page already initialized, skipping...');
      return;
    }
    
    console.log('Setting up appointment status event handlers...');
    
    // Status filter with namespaced event
    $("#statusFilter").off('change.' + NAMESPACE).on('change.' + NAMESPACE, function() {
      const selectedStatus = $(this).val();
      console.log('Filtering by status:', selectedStatus || 'All');

      // Filter desktop table rows
      $("#appointmentsTable tbody tr").each(function() {
        const rowStatus = $(this).data("status");

        if (!selectedStatus || rowStatus === selectedStatus) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });

      // Filter mobile cards
      $(".appointment-mobile-card").each(function() {
        const cardStatus = $(this).data("status");

        if (!selectedStatus || cardStatus === selectedStatus) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });
    });
    
    isInitialized = true;
    console.log('Appointment status page initialized successfully');
  }
  
  // CRITICAL: Cleanup function
  window.appointmentStatusCleanup = function() {
    console.log('=== Cleaning up appointment status page ===');
    console.log('Namespace:', NAMESPACE);
    
    // Remove all event handlers with this namespace
    $("#statusFilter").off('.' + NAMESPACE);
    $(document).off('.' + NAMESPACE);
    
    // Reset initialization flag
    isInitialized = false;
    
    console.log('Appointment status cleanup complete');
  };
  
  // Initialize when document is ready
  if (document.readyState === 'loading') {
    $(document).ready(function() {
      console.log('Document ready, initializing appointment status page...');
      initializePage();
    });
  } else {
    console.log('Document already ready, initializing immediately...');
    initializePage();
  }
  
  // Backup cleanup on beforeunload
  window.addEventListener('beforeunload', function() {
    if (window.appointmentStatusCleanup) {
      window.appointmentStatusCleanup();
    }
  });
  
})();
</script>
</body>
</html>