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

// Fetch services for this department
$serviceStmt = $pdo->prepare("SELECT * FROM department_services WHERE department_id = ?");
$serviceStmt->execute([$id]);
$services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch requirements for all services in this department
$requirementsByService = [];
if ($services) {
    $serviceIds = array_column($services, 'id');
    $in  = str_repeat('?,', count($serviceIds) - 1) . '?';
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
    <title><?= htmlspecialchars($department['acronym'] ?: $department['name']) ?> - Department Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f9fafc;
            font-family: "Segoe UI", Tahoma, sans-serif;
        }
        .page-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1rem 1rem;
            border-radius:  1rem 1rem;
            margin-bottom: 1rem;
            text-align: left;
        }
        .page-header h2 {
            font-weight: 600;
        }
        .card {
            border-radius: 1rem;
            border: none;
        }
        .service-card:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .service-card .card-header {
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .icon-text {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .book-btn {
            font-size: 1.1rem;
            padding: 0.8rem 2rem;
            border-radius: 2rem;
        }
    </style>
</head>
<body>
    <div id="content-area">

    <a href="#" class="btn btn-outline-primary d-inline-flex align-items-center px-4 py-2 rounded-pill shadow-sm mb-4" id="backButton">
        <i class="bx bx-arrow-back mr-2"></i> 
        <span>Back to Departments</span>
    </a>


    <!-- Header -->
    <div class="page-header">
        <h2><i class="bx bx-building-house"></i> <?= htmlspecialchars($department['name']) ?></h2>
        <p class="lead mb-0">
            <?= $department['description'] ? htmlspecialchars($department['description']) : '<em>No description provided.</em>' ?>
        </p>
    </div>
    
    <div class="container">


        <!-- Services Section -->
        <h5 class="mb-3 text-secondary"><i class="bx bx-cog"></i> Services & Requirements</h4>

        <?php if ($services): ?>
            <div class="row">
                <?php foreach ($services as $svc): ?>
                <div class="col-md-6 mb-4">
                    <div class="card service-card shadow-sm">
                        <div class="card-header bg-info text-white d-flex align-items-center">
                            <i class="bx bx-cog mr-2"></i>
                            <strong><?= htmlspecialchars($svc['service_name']) ?></strong>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($requirementsByService[$svc['id']])): ?>
                                <ul class="list-unstyled mb-0 small text-dark">
                                    <?php foreach ($requirementsByService[$svc['id']] as $req): ?>
                                        <li class="mb-1 icon-text">
                                            <i class="bx bx-check-circle text-success"></i>
                                            <?= htmlspecialchars($req) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">No specific requirements listed.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light border text-muted">No services available for this department.</div>
        <?php endif; ?>

        <!-- Book Appointment -->
        <div class="text-center mt-5 mb-5">
            <button class="btn btn-outline-primary book-btn" onclick="openBooking(<?= $department['id'] ?>)">
                <i class='bx bx-calendar'></i> Book Appointment
            </button>
        </div>
    </div>

    <!-- Book Appointment Modal -->
    <div class="modal fade" id="appointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="appointment-form" class="modal-content shadow-sm border-0" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bx bx-calendar-check mr-2"></i> Book Appointment</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="department_id" id="department_id">
                    <input type="hidden" name="available_date_id" id="available_date_id">

                    <div class="form-group">
                        <label for="service">Select Service</label>
                        <select class="form-control" name="service" id="service" required></select>
                    </div>

                    <div class="form-group">
                        <label for="valid_id">Upload Valid ID</label>
                        <input type="file" class="form-control-file" name="valid_id" id="valid_id" accept="image/*" required>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Select Available Date</label>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="prevMonth">Previous</button>
                            <strong id="calendar-header" class="mx-2"></strong>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="nextMonth">Next</button>
                        </div>
                        <div id="calendar"></div>
                        <div id="slotSelector" class="mt-3"></div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label for="reason">Reason for Appointment</label>
                        <textarea class="form-control" name="reason" id="reason" rows="3" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-block">
                        <i class="bx bx-check-circle mr-1"></i> Confirm Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>

    </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

<script>
function openBooking(departmentId) {
    $('#appointmentModal').modal('show');
    $('#department_id').val(departmentId);
    $('#available_date_id').val('');
    $('#calendar').empty();
    $('#slotSelector').empty();

    $.get('get_services_by_department.php', { department_id: departmentId }, function(data) {
        $('#service').html(data);
    });

    loadCalendar(departmentId);
}

$(document).on('click', '#backButton', function(e) {
    e.preventDefault(); // stop default link navigation
    
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
</script>

</script>
</body>
</html>