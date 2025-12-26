<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo "<script>alert('Unauthorized access!'); window.location.href='../login.php';</script>";
    exit();
}

$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$department_id = $stmt->fetchColumn();

if (!$department_id) {
    echo "<script>alert('No department assigned!'); window.location.href='../login.php';</script>";
    exit();
}

// Auto-mark No Show
$updateStmt = $pdo->prepare("UPDATE appointments SET status = 'No Show' WHERE status = 'Pending' AND scheduled_for < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND department_id = ?");
$updateStmt->execute([$department_id]);

// Fetch Data
$query = "SELECT a.id, a.transaction_id, a.status, a.reason, a.scheduled_for, a.requested_at, a.available_date_id, r.first_name, r.middle_name, r.last_name, r.address, r.phone_number, au.email, ds.service_name FROM appointments a JOIN residents r ON a.resident_id = r.id JOIN auth au ON r.auth_id = au.id LEFT JOIN department_services ds ON a.service_id = ds.id WHERE a.department_id = ? AND a.status = 'No Show' ORDER BY a.scheduled_for DESC";
$appointments = $pdo->prepare($query);
$appointments->execute([$department_id]);
$appointmentData = $appointments->fetchAll(PDO::FETCH_ASSOC);

$statsQuery = $pdo->prepare("SELECT COUNT(*) as total_noshow, COUNT(CASE WHEN DATE(scheduled_for) = CURDATE() THEN 1 END) as today_noshow, COUNT(CASE WHEN YEARWEEK(scheduled_for) = YEARWEEK(NOW()) THEN 1 END) as week_noshow, COUNT(CASE WHEN MONTH(scheduled_for) = MONTH(NOW()) AND YEAR(scheduled_for) = YEAR(NOW()) THEN 1 END) as month_noshow FROM appointments WHERE department_id = ? AND status = 'No Show'");
$statsQuery->execute([$department_id]);
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No-Show Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%); font-family: 'Segoe UI', sans-serif; min-height: 100vh; padding: 2rem 0; }
        .page-header { background: linear-gradient(135deg, #0D92F4, #27548A); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; color: white; box-shadow: 0 10px 30px rgba(60, 131, 231, 0.3); }
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.08); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card i { font-size: 2.5rem; color: #e74c3c; margin-bottom: 0.5rem; }
        .table-responsive { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 20px rgba(0,0,0,0.08); }
        thead { background: linear-gradient(135deg, #0D92F4, #27548A); color: white; }
        th { border: none; padding: 1rem; }
        td { vertical-align: middle !important; }
        .table-success { background-color: #d4edda !important; transition: background-color 1s ease; }

        /* === DATE CARD STYLES === */
        .dates-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; max-height: 400px; overflow-y: auto; padding: 0.5rem; }
        .date-card { border: 2px solid #e0e6ed; border-radius: 12px; padding: 1rem; cursor: default; background: white; position: relative; transition: all 0.2s; }
        .date-card.active-date { border-color: #3498db; background: #f0f7ff; box-shadow: 0 0 10px rgba(52, 152, 219, 0.2); }
        .time-slot-option { display: flex; justify-content: space-between; padding: 0.5rem; margin-top: 0.5rem; border: 1px solid #ddd; border-radius: 6px; cursor: pointer; background: #f8f9fa; }
        .time-slot-option:hover { background: #e2e6ea; border-color: #adb5bd; }
        .time-slot-option.selected { background: #d4edda; border-color: #28a745; color: #155724; font-weight: bold; }
        .time-slot-option.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
    </style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <h2><i class="fas fa-user-times mr-2"></i> No-Show Appointments</h2>
        <p>Manage missed appointments</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-3"><div class="stat-card"><i class="fas fa-exclamation-triangle"></i><h3 id="statTotal"><?= $stats['total_noshow'] ?></h3><p>Total</p></div></div>
        <div class="col-md-3"><div class="stat-card"><i class="fas fa-calendar-day"></i><h3><?= $stats['today_noshow'] ?></h3><p>Today</p></div></div>
        <div class="col-md-3"><div class="stat-card"><i class="fas fa-calendar-week"></i><h3><?= $stats['week_noshow'] ?></h3><p>Week</p></div></div>
        <div class="col-md-3"><div class="stat-card"><i class="fas fa-calendar-alt"></i><h3><?= $stats['month_noshow'] ?></h3><p>Month</p></div></div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover" id="mainTable">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Name</th>
                    <th>Service</th>
                    <th>Missed Date</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="appointmentsBody">
                <?php if (!empty($appointmentData)): ?>
                    <?php foreach ($appointmentData as $app): ?>
                        <tr id="row_<?= $app['id'] ?>">
                            <td><span class="badge badge-danger"><?= $app['transaction_id'] ?></span></td>
                            <td><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></td>
                            <td><?= htmlspecialchars($app['service_name']) ?></td>
                            <td class="schedule-cell">
                                <i class="fas fa-calendar-day text-danger mr-1"></i>
                                <?= date('M j, Y', strtotime($app['scheduled_for'])) ?><br>
                                <small class="text-muted">
                                    <?= ((int)date('H', strtotime($app['scheduled_for'])) < 12) ? 'Morning' : 'Afternoon' ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <?php 
                                    $curDate = date('M j, Y', strtotime($app['scheduled_for']));
                                    $curTime = ((int)date('H', strtotime($app['scheduled_for'])) < 12) ? ' (Morning)' : ' (Afternoon)';
                                    $fullCurrentStr = $curDate . $curTime;
                                ?>
                                <button class="btn btn-sm btn-warning btn-open-reschedule"
                                        data-id="<?= $app['id'] ?>"
                                        data-old-date-id="<?= $app['available_date_id'] ?>"
                                        data-old-time="<?= $app['scheduled_for'] ? date('H:i:s', strtotime($app['scheduled_for'])) : '' ?>"
                                        data-current-date="<?= $fullCurrentStr ?>"
                                        data-name="<?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>">
                                    <i class="fas fa-redo"></i> Reschedule
                                </button>
                                <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $app['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="noRecordsRow"><td colspan="5" class="text-center p-4">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="sharedRescheduleModal" tabindex="-1" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-alt mr-2"></i> Reschedule Appointment</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="sharedRescheduleForm">
                    <input type="hidden" id="reschApptId" name="appointment_id">
                    <input type="hidden" id="reschOldDateId" name="old_date_id">
                    <input type="hidden" id="reschOldTime" name="old_time_slot">
                    <input type="hidden" id="reschNewDateId" name="new_date_id">
                    <input type="hidden" id="reschNewTime" name="new_time_slot">

                    <h6 class="text-muted mb-3">Rescheduling for: <strong id="modalResidentName" class="text-dark"></strong></h6>

                    <div class="alert alert-info mb-3">
                        <strong>Was Scheduled:</strong> <span id="reschCurrentDisplay"></span>
                    </div>

                    <div id="reschLoading" class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2">Loading available dates...</p>
                    </div>
                    <div id="reschError" class="alert alert-danger" style="display:none;"></div>

                    <div id="reschGridContainer" style="display:none;">
                        <p class="small text-muted mb-2">Select a new date and time slot:</p>
                        <div id="sharedDatesGrid" class="dates-grid">
                            </div>
                    </div>

                    <div id="reschSummary" class="alert alert-success mt-3" style="display:none;">
                        <strong>Selected:</strong> <span id="reschSummaryText"></span>
                    </div>

                    <div class="text-right mt-4">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" id="btnConfirmReschedule" class="btn btn-warning" disabled>
                            <i class="fas fa-save mr-2"></i> Confirm Reschedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {

    // Helper: Refresh UI
    function removeRowAndRefresh(id) {
        $('#row_' + id).fadeOut(400, function() {
            $(this).remove();
            if($('#appointmentsBody tr').length === 0) $('#appointmentsBody').html('<tr id="noRecordsRow"><td colspan="5" class="text-center p-4">No records found.</td></tr>');
        });
        const totalEl = $('#statTotal');
        let currentTotal = parseInt(totalEl.text());
        if(!isNaN(currentTotal) && currentTotal > 0) totalEl.text(currentTotal - 1);
    }

    // 1. OPEN MODAL & FETCH DATES
    $('.btn-open-reschedule').click(function() {
        const btn = $(this);
        
        // Reset Everything
        $('#reschApptId').val(btn.data('id'));
        $('#reschOldDateId').val(btn.data('old-date-id'));
        $('#reschOldTime').val(btn.data('old-time'));
        $('#modalResidentName').text(btn.data('name'));
        $('#reschCurrentDisplay').text(btn.data('current-date'));
        
        $('#reschNewDateId').val('');
        $('#reschNewTime').val('');
        
        $('#reschLoading').show();
        $('#reschGridContainer').hide();
        $('#reschError').hide();
        $('#reschSummary').hide();
        $('#btnConfirmReschedule').prop('disabled', true).html('<i class="fas fa-save mr-2"></i> Confirm Reschedule');
        
        $('#sharedRescheduleModal').modal('show');

        // Fetch Dates via AJAX
        $.ajax({
            url: 'get_available_dates.php', // Make sure this file exists!
            type: 'POST',
            dataType: 'json',
            success: function(res) {
                $('#reschLoading').hide();
                if(res.success && res.dates.length > 0) {
                    renderDates(res.dates);
                    $('#reschGridContainer').fadeIn();
                } else {
                    $('#reschError').text(res.message || 'No dates available.').show();
                }
            },
            error: function() {
                $('#reschLoading').hide();
                $('#reschError').text('Error connecting to server.').show();
            }
        });
    });

    // 2. RENDER DATE CARDS
    function renderDates(dates) {
        const grid = $('#sharedDatesGrid');
        grid.empty();

        dates.forEach(date => {
            const amSlot = createSlotHtml('AM', 'Morning', date.am_open, date.am_remaining, '09:00:00');
            const pmSlot = createSlotHtml('PM', 'Afternoon', date.pm_open, date.pm_remaining, '14:00:00');
            
            const dateObj = new Date(date.date);
            const displayDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', weekday: 'short' });

            const cardHtml = `
                <div class="date-card" data-date-id="${date.date_id}" data-date-str="${displayDate}">
                    <div class="font-weight-bold text-primary mb-2"><i class="fas fa-calendar-day"></i> ${displayDate}</div>
                    ${amSlot} ${pmSlot}
                </div>`;
            grid.append(cardHtml);
        });
    }

    function createSlotHtml(label, timeStr, isOpen, remaining, timeValue) {
        if(!isOpen) return `<div class="time-slot-option disabled"><span>${timeStr}</span> <span class="badge badge-secondary">Full</span></div>`;
        return `<div class="time-slot-option clickable-slot" data-time="${timeValue}" data-label="${timeStr}"><span>${timeStr}</span> <span class="badge badge-success">${remaining} left</span></div>`;
    }

    // 3. HANDLE SLOT CLICK
    $(document).on('click', '.clickable-slot', function() {
        // Visuals
        $('.clickable-slot').removeClass('selected');
        $('.date-card').removeClass('active-date');
        $(this).addClass('selected');
        const parentCard = $(this).closest('.date-card');
        parentCard.addClass('active-date');

        // Set Values
        const dateId = parentCard.data('date-id');
        const timeVal = $(this).data('time');
        const dateStr = parentCard.data('date-str');
        const timeLabel = $(this).data('label');

        $('#reschNewDateId').val(dateId);
        $('#reschNewTime').val(timeVal);

        // Summary & Enable Button
        $('#reschSummaryText').text(`${dateStr} at ${timeLabel}`);
        $('#reschSummary').fadeIn();
        $('#btnConfirmReschedule').prop('disabled', false);
    });

    // 4. SUBMIT FORM
    $('#sharedRescheduleForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnConfirmReschedule');
        const originalText = btn.html();
        const apptId = $('#reschApptId').val();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'process_reschedule.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.success) {
                    $('#sharedRescheduleModal').modal('hide');
                    removeRowAndRefresh(apptId);
                    alert(res.message);
                } else {
                    alert('Error: ' + res.message);
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('System error occurred.');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 5. DELETE BUTTON
    $(document).on('click', '.btn-delete', function() {
        const id = $(this).data('id');
        if(confirm('Permanently delete this record?')) {
            $.post('delete_appointment.php', { appointment_id: id }, function(res) {
                removeRowAndRefresh(id);
            });
        }
    });
});
</script>
</body>
</html>