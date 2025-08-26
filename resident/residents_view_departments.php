<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id'])) {
    echo "<script>alert('Please log in first.'); window.location.href='../login.php';</script>";
    exit();
}

$query = "SELECT d.*, 
                s.id AS service_id,
                s.service_name,
                r.requirement
            FROM departments d
            LEFT JOIN department_services s ON d.id = s.department_id
            LEFT JOIN service_requirements r ON s.id = r.service_id
            ORDER BY d.name ASC, s.service_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$rawResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

$departments = [];

foreach ($rawResults as $row) {
    $deptId = $row['id'];
    if (!isset($departments[$deptId])) {
        $departments[$deptId] = [
            'id' => $deptId,
            'name' => $row['name'],
            'description' => $row['description'],
            'services' => []
        ];
    }

    $serviceName = $row['service_name'];
    $requirement = $row['requirement'];

    if ($serviceName) {
        $departments[$deptId]['services'][$serviceName][] = $requirement;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Departments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../assets/css/resident.css">
    <style>
        .calendar-day.available { background-color: #e6ffe6; cursor: pointer; }
        .calendar-day.available:hover { background-color: #ccffcc; }
        .calendar-day.selected { background-color:rgba(40, 167, 70, 0.68) !important; color: white; font-weight: bold; }
        .card:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        #calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        .calendar-day {
            min-height: 80px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background-color: #f9f9f9;
            position: relative;
        }
        .modal-dialog { max-width:45vw; }
        .modal-body {
            max-height: 80vh;
            overflow-y: auto;
        }
        .badge { font-size: 0.75rem; display: block; }
        .modal-content {
        transition: all 0.3s ease-in-out;
        }
        .modal-header {
        border-bottom: none;
        }
        .modal-body p {
            font-size: 1rem;
        }
        .custom-width {
            max-width: 450px; /* You can adjust to 500px or 550px if still too wide */
        }
        .enlarged-badge {
            font-size: 0.9rem;         /* Increase text size */
            padding: 0.6em 1em;       /* More padding inside badge */
            border-radius: 20px;      /* Pill shape */
            background-color: #17a2b8; /* Keep info color */
            color: #fff;
        }
        .hover-shadow:hover {
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
            transition: 0.3s ease;
        }

        .badge-info {
            background-color: #0dcaf0;
        }
        .hover-shadow:hover {
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
            transition: 0.3s ease;
        }

        .badge-info {
            background-color: #0dcaf0;
        }
        .modal-custom-sm {
            max-width: 500px; /* You can adjust this width as needed */
        }
        .badge-lg {
            font-size: 0.95rem;  /* Slightly larger text */
            padding: 0.5em 0.75em;  /* More space inside */
        }


    </style>
</head>
<body class="p-4">


<div class="container">
    <h3 class="mb-4">Departments</h3>
    <div class="input-group mb-4">
        <input type="text" class="form-control" id="searchInput" placeholder="Search department or service...">
        <div class="input-group-append">
            <button class="btn btn-outline-danger" id="clearSearch"><i class="fas fa-times-circle"></i> Clear</button>
        </div>
    </div>

   <!-- HTML View -->
<div class="row" id="departmentList">
    <?php foreach ($departments as $d): ?>
        <div class="col-md-4 mb-3 department-card">
            <div class="card h-100" data-toggle="modal" data-target="#deptModal<?= $d['id'] ?>">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($d['name']) ?></h5>
                    <p class="card-text"><?= htmlspecialchars($d['description']) ?></p>
                    <div>
                        <?php foreach ($d['services'] as $serviceName => $requirements): ?>
                            <div class="mb-2">
                                <span class="badge badge-info mb-1"><?= htmlspecialchars($serviceName) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Modal -->
        <div class="modal fade" id="deptModal<?= $d['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered custom-width">
                <div class="modal-content shadow-lg rounded-3 border-0">
                    <div class="modal-header bg-primary text-white rounded-top">
                        <h5 class="modal-title font-weight-bold"><?= htmlspecialchars($d['name']) ?></h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-4">
                <p class="mb-4">
                    <strong class="text-secondary">Description:</strong><br>
                    <span class="text-dark"><?= htmlspecialchars($d['description']) ?></span>
                </p>

                <p class="mb-3"><strong class="text-secondary">Services & Requirements:</strong></p>

            <div class="mb-3">
                <?php foreach ($d['services'] as $serviceName => $requirements): ?>
                    <div class="card mb-3 border-left-info shadow-sm">
                        <div class="card-header bg-info text-white py-2 px-3 d-flex align-items-center">
                            <i class="bx bx-cog mr-2"></i>
                            <strong><?= htmlspecialchars($serviceName) ?></strong>
                        </div>
                        <div class="card-body py-2 px-3">
                            <?php if (!empty($requirements)): ?>
                                <ul class="list-unstyled mb-0 pl-3 small text-dark">
                                    <?php foreach ($requirements as $req): ?>
                                        <li class="mb-1">
                                            <i class="bx bx-check-circle text-success mr-1"></i>
                                            <?= htmlspecialchars($req) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">No specific requirements listed.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

                        <div class="text-center">
                            <button class="btn btn-outline-primary px-4 py-2" data-toggle="modal" data-target="#appointmentModal"
                                data-dismiss="modal" onclick="openBooking(<?= $d['id'] ?>)">
                                <i class='bx bx-calendar'></i> Book Appointment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</div>

<!-- Book Appointment Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="appointment-form" class="modal-content shadow-sm border-0" enctype="multipart/form-data">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-check mr-2"></i>Book Appointment</h5>
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

                <div class="form-group">
                    <label for="reason">Reason for Appointment</label>
                    <textarea class="form-control" name="reason" id="reason" rows="3" required></textarea>
                </div>

                <button type="submit" class="btn btn-success btn-block">
                    <i class="fas fa-check-circle mr-1"></i>Confirm Appointment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Transaction Number Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Transaction Number</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body text-center">
        <p>This is your transaction number:</p>
        <h3 id="transactionNumber" class="text-primary font-weight-bold"></h3>
        <p class="mt-3"><b>Please bring all the necessary requirements and present them to the assigned personnel when requested.<b></p>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentMonth = new Date().getMonth() + 1;
let currentYear = new Date().getFullYear();

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

function loadCalendar(departmentId) {
    $.get('get_available_dates.php', { department_id: departmentId, month: currentMonth, year: currentYear }, function(data) {
        const availableDates = JSON.parse(data);
        generateCalendar(availableDates);
    });
}

function generateCalendar(availableDates) {
    const calendar = $('#calendar');
    calendar.empty();

    const firstDay = new Date(currentYear, currentMonth - 1, 1);
    const lastDate = new Date(currentYear, currentMonth, 0).getDate();
    const startDay = firstDay.getDay();
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    $('#calendar-header').text(firstDay.toLocaleString('default', { month: 'long' }) + ' ' + currentYear);

    days.forEach(day => calendar.append(`<div class='font-weight-bold text-center'>${day}</div>`));
    for (let i = 0; i < startDay; i++) calendar.append('<div></div>');

    for (let day = 1; day <= lastDate; day++) {
        const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const data = availableDates[dateStr] || null;
        const div = $(`<div class='calendar-day text-center ${data ? "available" : ""}' data-date='${dateStr}'>${day}</div>`);

        if (data) {
            div.append(`<div class='badge badge-success'>AM: ${data.am_slots - data.am_booked}</div>`);
            div.append(`<div class='badge badge-info'>PM: ${data.pm_slots - data.pm_booked}</div>`);
            div.click(function () {
                $('.calendar-day').removeClass('selected');
                $(this).addClass('selected');
                $('#slotSelector').html(`
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="slot_period" value="am" data-id="${data.id}" required>
                    <label class="form-check-label">AM Slot</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="slot_period" value="pm" data-id="${data.id}" required>
                    <label class="form-check-label">PM Slot</label>
                  </div>`);
            });
        }
        calendar.append(div);
    }
}

$(document).on('change', 'input[name="slot_period"]', function() {
    const selectedId = $(this).data('id');
    $('#available_date_id').val(selectedId);
});

$('#prevMonth').click(() => { currentMonth--; if (currentMonth < 1) { currentMonth = 12; currentYear--; } loadCalendar($('#department_id').val()); });
$('#nextMonth').click(() => { currentMonth++; if (currentMonth > 12) { currentMonth = 1; currentYear++; } loadCalendar($('#department_id').val()); });

$('#appointment-form').submit(function(e) {
    e.preventDefault();

    const selectedSlot = $('input[name="slot_period"]:checked');
    const selectedSlotId = selectedSlot.data('id');
    const slotPeriod = selectedSlot.val();

    if (!selectedSlot.length || !selectedSlotId || !slotPeriod) {
        alert("Please select a date and slot period.");
        return;
    }

    $('#available_date_id').val(selectedSlotId);

    const formData = new FormData(this);
    formData.append('slot_period', slotPeriod);

    $.ajax({
        url: 'residents_submit_appointment.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function(res) {
            if (res.status === 'success') {
                $('#appointmentModal').modal('hide');
                $('#transactionNumber').text(res.appointment_id || 'N/A');
                $('#transactionModal').modal('show');
                $('#appointment-form')[0].reset();
                $('#slotSelector').empty();
            } else {
                alert(res.message || 'Something went wrong.');
            }
        },
        error: function(xhr) {
            alert("Error booking appointment: " + xhr.responseText);
        }
    });
});

$('#searchInput').on('input', function() {
    const val = $(this).val().toLowerCase();
    $('.department-card').each(function() {
        const keywords = $(this).data('search');
        $(this).toggle(keywords.includes(val));
    });
});

$('#clearSearch').click(function () {
    $('#searchInput').val('');
    $('.department-card').show();
});
</script>
</body>
</html>