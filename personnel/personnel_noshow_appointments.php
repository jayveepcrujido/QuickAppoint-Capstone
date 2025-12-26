<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo "<script>alert('Unauthorized access!'); window.location.href='../login.php';</script>";
    exit();
}

// Get the logged-in personnel's department_id
$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$department_id = $stmt->fetchColumn();

if (!$department_id) {
    echo "<script>alert('No department assigned!'); window.location.href='../login.php';</script>";
    exit();
}

// 1. Auto-mark past pending appointments as No Show
$updateStmt = $pdo->prepare("
    UPDATE appointments 
    SET status = 'No Show'
    WHERE status = 'Pending' 
    AND scheduled_for < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND department_id = ?
");
$updateStmt->execute([$department_id]);

// 2. Fetch No Show Appointments
$query = "
    SELECT 
        a.id, a.transaction_id, a.status, a.reason, a.scheduled_for, a.requested_at, a.available_date_id,
        r.first_name, r.middle_name, r.last_name, r.address, r.phone_number,
        au.email, ds.service_name
    FROM appointments a
    JOIN residents r ON a.resident_id = r.id
    JOIN auth au ON r.auth_id = au.id
    LEFT JOIN department_services ds ON a.service_id = ds.id
    WHERE a.department_id = ? AND a.status = 'No Show'
    ORDER BY a.scheduled_for DESC
";
$appointments = $pdo->prepare($query);
$appointments->execute([$department_id]);
$appointmentData = $appointments->fetchAll(PDO::FETCH_ASSOC);

// 3. Get Statistics
$statsQuery = $pdo->prepare("
    SELECT 
        COUNT(*) as total_noshow,
        COUNT(CASE WHEN DATE(scheduled_for) = CURDATE() THEN 1 END) as today_noshow,
        COUNT(CASE WHEN YEARWEEK(scheduled_for) = YEARWEEK(NOW()) THEN 1 END) as week_noshow,
        COUNT(CASE WHEN MONTH(scheduled_for) = MONTH(NOW()) AND YEAR(scheduled_for) = YEAR(NOW()) THEN 1 END) as month_noshow
    FROM appointments WHERE department_id = ? AND status = 'No Show'
");
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
        .stat-card h3 { font-size: 2rem; font-weight: 700; margin: 0.5rem 0; }
        .table-responsive { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 20px rgba(0,0,0,0.08); }
        thead { background: linear-gradient(135deg, #0D92F4, #27548A); color: white; }
        th { border: none; padding: 1rem; }
        td { vertical-align: middle !important; }
        
        .dates-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; max-height: 500px; overflow-y: auto; padding: 0.5rem; }
        .date-card { border: 2px solid #e0e6ed; border-radius: 12px; padding: 1rem; cursor: default; background: white; position: relative; transition: all 0.2s; }
        .date-card.active-date { border-color: #3498db; background: #f0f7ff; }
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
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-exclamation-triangle"></i>
                <h3 id="statTotal"><?= $stats['total_noshow'] ?></h3>
                <p>Total</p>
            </div>
        </div>
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
                    <th>Scheduled Date</th>
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
                            <td><?= date('M j, Y g:i A', strtotime($app['scheduled_for'])) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-warning btn-open-reschedule"
                                        data-id="<?= $app['id'] ?>"
                                        data-old-date-id="<?= $app['available_date_id'] ?>"
                                        data-old-time="<?= $app['scheduled_for'] ? date('H:i:s', strtotime($app['scheduled_for'])) : '' ?>"
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
                <h6 class="text-muted mb-3">Rescheduling for: <strong id="modalResidentName" class="text-dark"></strong></h6>
                
                <input type="hidden" id="modalApptId">
                <input type="hidden" id="modalOldDateId">
                <input type="hidden" id="modalOldTime">
                <input type="hidden" id="modalNewDateId">
                <input type="hidden" id="modalNewTime">

                <div id="modalLoading" class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Loading available dates...</p>
                </div>

                <div id="modalError" class="alert alert-danger" style="display:none;"></div>

                <div id="modalDatesContainer" style="display:none;">
                    <p class="small text-muted">Please select a new date and time slot:</p>
                    <div id="sharedDatesGrid" class="dates-grid"></div>
                </div>

                <div id="selectionSummary" class="alert alert-success mt-3" style="display:none;">
                    <strong>Selected:</strong> <span id="summaryText"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" id="btnConfirmReschedule" class="btn btn-primary" disabled>
                    Confirm Reschedule
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {

    // Helper to update table and stats without reload
    function removeRowAndRefresh(id) {
        const row = $('#row_' + id);
        
        // 1. Fade out and remove the row
        row.fadeOut(400, function() {
            $(this).remove();
            
            // 2. Check if table is empty
            if($('#appointmentsBody tr').length === 0) {
                $('#appointmentsBody').html('<tr id="noRecordsRow"><td colspan="5" class="text-center p-4">No records found.</td></tr>');
            }
        });

        // 3. Update the Total Count dynamically
        const totalEl = $('#statTotal');
        let currentTotal = parseInt(totalEl.text());
        if(!isNaN(currentTotal) && currentTotal > 0) {
            totalEl.text(currentTotal - 1);
        }
    }

    // 1. OPEN MODAL
    $('.btn-open-reschedule').click(function() {
        const btn = $(this);
        const id = btn.data('id');
        const name = btn.data('name');
        
        $('#modalApptId').val(id);
        $('#modalOldDateId').val(btn.data('old-date-id'));
        $('#modalOldTime').val(btn.data('old-time'));
        $('#modalResidentName').text(name);
        
        $('#modalNewDateId').val('');
        $('#modalNewTime').val('');
        $('#selectionSummary').hide();
        $('#btnConfirmReschedule').prop('disabled', true);
        
        $('#sharedRescheduleModal').modal('show');
        $('#modalLoading').show();
        $('#modalDatesContainer').hide();
        $('#modalError').hide();

        $.ajax({
            url: 'get_available_dates.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                $('#modalLoading').hide();
                if(response.success && response.dates.length > 0) {
                    renderDates(response.dates);
                    $('#modalDatesContainer').fadeIn();
                } else {
                    $('#modalError').text(response.message || 'No dates available').show();
                }
            },
            error: function(xhr) {
                $('#modalLoading').hide();
                $('#modalError').text('Error loading dates.').show();
            }
        });
    });

    // 2. RENDER DATES
    function renderDates(dates) {
        const grid = $('#sharedDatesGrid');
        grid.empty();

        dates.forEach(date => {
            const amSlot = createSlotHtml('AM', '9:00 AM', date.am_open, date.am_remaining, '09:00:00');
            const pmSlot = createSlotHtml('PM', '2:00 PM', date.pm_open, date.pm_remaining, '14:00:00');
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
        if(!isOpen) return `<div class="time-slot-option disabled"><span>${label} (${timeStr})</span> <span class="badge badge-secondary">Full</span></div>`;
        return `<div class="time-slot-option clickable-slot" data-time="${timeValue}" data-label="${timeStr}"><span>${label} (${timeStr})</span> <span class="badge badge-success">${remaining} left</span></div>`;
    }

    // 3. SELECT SLOT
    $(document).on('click', '.clickable-slot', function() {
        $('.clickable-slot').removeClass('selected');
        $('.date-card').removeClass('active-date');
        $(this).addClass('selected');
        const parentCard = $(this).closest('.date-card');
        parentCard.addClass('active-date');

        $('#modalNewDateId').val(parentCard.data('date-id'));
        $('#modalNewTime').val($(this).data('time'));
        $('#summaryText').text(`${parentCard.data('date-str')} at ${$(this).data('label')}`);
        $('#selectionSummary').fadeIn();
        $('#btnConfirmReschedule').prop('disabled', false);
    });

    // 4. CONFIRM RESCHEDULE (DYNAMIC REFRESH)
    $('#btnConfirmReschedule').click(function() {
        const btn = $(this);
        const originalText = btn.html();
        const apptId = $('#modalApptId').val();

        const payload = {
            appointment_id: apptId,
            old_date_id: $('#modalOldDateId').val(),
            old_time_slot: $('#modalOldTime').val(),
            new_date_id: $('#modalNewDateId').val(),
            new_time_slot: $('#modalNewTime').val()
        };

        if(!payload.new_date_id || !payload.new_time_slot) { alert('Please select a date and time.'); return; }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'process_reschedule.php',
            type: 'POST',
            data: payload,
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $('#sharedRescheduleModal').modal('hide');
                    
                    // === DYNAMIC UPDATE ===
                    // Instead of reload, we remove the row
                    removeRowAndRefresh(apptId);
                    
                    // Show a nice toast or alert
                    alert('Success! Appointment rescheduled.');
                } else {
                    alert('Error: ' + response.message);
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                alert('System error occurred.');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 5. DELETE BUTTON (DYNAMIC REFRESH)
    $(document).on('click', '.btn-delete', function() {
        const btn = $(this);
        const id = btn.data('id');
        
        if(confirm('Permanently delete this record?')) {
            $.post('delete_appointment.php', { appointment_id: id }, function(res) {
                // === DYNAMIC UPDATE ===
                removeRowAndRefresh(id);
            });
        }
    });

});
</script>
</body>
</html>