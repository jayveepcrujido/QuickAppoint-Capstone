<?php
session_start();
include 'conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
        exit;
    } else {
        echo "<div class='alert alert-danger'>Unauthorized access.</div>";
        exit();
    }
}

$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !$user['department_id']) {
        die("<div class='alert alert-danger'>Assigned department not found.</div>");
    }
    $departmentId = $user['department_id'];
} catch (Exception $e) {
    die("<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['available_date'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO available_dates (department_id, date_time, status, created_at, am_slots, pm_slots)
            VALUES (?, ?, 'available', NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE am_slots = VALUES(am_slots), pm_slots = VALUES(pm_slots)
        ");

        foreach ($_POST['available_date'] as $date => $entry) {
            $am = (int)$entry['am'];
            $pm = (int)$entry['pm'];
            $dateObj = new DateTime($date);
            $standardizedDate = $dateObj->format('Y-m-d') . " 00:00:00";

            $stmt->execute([$departmentId, $standardizedDate, $am, $pm]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Available dates and slots added.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch available dates
$stmt = $pdo->prepare("SELECT date_time, DATE(date_time) as date, am_slots, pm_slots, am_booked, pm_booked FROM available_dates WHERE department_id = ?");
$stmt->execute([$departmentId]);

$availableDates = [];
$existingDates = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $date = $row['date'];
    $availableDates[$date] = [
        'am_slots' => (int)$row['am_slots'],
        'pm_slots' => (int)$row['pm_slots'],
        'am_booked' => (int)$row['am_booked'],
        'pm_booked' => (int)$row['pm_booked']
    ];
    $existingDates[] = $date;
}
?>


<!DOCTYPE html>
<html lang="en">
  <style>
  #calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    margin-top: 10px;
  }

  .calendar-day {
    padding: 15px;
    border: 1px solid #dee2e6;
    text-align: center;
    background-color: #f8f9fa;
    cursor: pointer;
    border-radius: 5px;
    transition: all 0.2s ease-in-out;
  }

  .calendar-day:hover:not(.disabled):not(.selected) {
    background-color: #e2f0d9;
  }

  .calendar-day.disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
    color: #999;
  }

  .calendar-day.selected {
    background-color: #28a745;
    color: white;
    font-weight: bold;
  }

  .calendar-header {
    font-weight: bold;
    background-color: #e9ecef;
    text-align: center;
    padding: 10px 0;
    border-radius: 5px;
  }

  .month-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
  }

  .month-nav button {
    min-width: 100px;
  }

  .badge {
    font-size: 0.75rem;
    margin-top: 5px;
    display: block;
  }
</style>
<head>
  <meta charset="UTF-8">
  <title>Create Available Dates</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mb-5">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex align-items-center mb-3">
        <i class="fas fa-calendar-plus fa-lg text-primary mr-2"></i>
        <h4 class="mb-0 text-primary font-weight-bold">Create Available Dates</h4>
      </div>

      <div id="response-msg"></div>

      <form method="post" id="available-dates-form">
        <div class="form-group">
          <label for="selected-dates" class="font-weight-bold">Selected Dates and Slots:</label>
          <div id="dateInputs" class="p-2 bg-light rounded border"></div>
        </div>

        <div class="form-group">
          <label class="font-weight-bold">Select Dates from Calendar</label>
          <div class="month-nav">
            <button type="button" id="prev" class="btn btn-outline-secondary btn-sm">
              <i class="fas fa-chevron-left"></i> Previous
            </button>
            <h5 id="calendar-header" class="mb-0 text-center font-weight-bold"></h5>
            <button type="button" id="next" class="btn btn-outline-secondary btn-sm">
              Next <i class="fas fa-chevron-right"></i>
            </button>
          </div>

          <div id="calendar"></div>
        </div>

        <div class="text-right">
          <button type="submit" class="btn btn-primary mt-3 px-4">
            <i class="fas fa-check-circle mr-1"></i> Submit Available Dates
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const existingDates = <?php echo json_encode($existingDates); ?>;
const availableDates = <?php echo json_encode($availableDates); ?>;

let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

function generateCalendar(month, year) {
  const calendar = $('#calendar');
  calendar.empty();

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const firstDay = new Date(year, month, 1);
  const lastDate = new Date(year, month + 1, 0).getDate();
  const startDay = firstDay.getDay();
  const monthName = firstDay.toLocaleString('default', { month: 'long' });
  $('#calendar-header').text(`${monthName} ${year}`);

  const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  daysOfWeek.forEach(day => calendar.append(`<div class="calendar-header">${day}</div>`));

  for (let i = 0; i < startDay; i++) calendar.append('<div></div>');

  for (let date = 1; date <= lastDate; date++) {
    const dateObj = new Date(year, month, date);
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
    const cell = $(`<div class="calendar-day">${date}</div>`);

    const amData = availableDates[dateStr]?.am_slots ?? 0;
    const amUsed = availableDates[dateStr]?.am_booked ?? 0;
    const pmData = availableDates[dateStr]?.pm_slots ?? 0;
    const pmUsed = availableDates[dateStr]?.pm_booked ?? 0;

    const remainingAM = amData - amUsed;
    const remainingPM = pmData - pmUsed;

    cell.append(`<div class="badge">AM: ${remainingAM} PM: ${remainingPM}</div>`);

    if (dateObj < today || dateObj.getDay() === 0 || dateObj.getDay() === 6 || existingDates.includes(dateStr)) {
      cell.addClass('disabled');
    } else {
      cell.attr('data-date', dateStr);
      cell.click(function () {
        if (!$(this).hasClass('selected')) {
          $(this).addClass('selected');
          const inputHTML = `
            <div class="mb-2 border p-2" data-date="${dateStr}">
              <strong>${dateStr}</strong>
              <div class="form-row">
                <div class="col">
                  <label>AM Slots</label>
                  <input type="number" name="available_date[${dateStr}][am]" class="form-control" min="0" value="5">
                </div>
                <div class="col">
                  <label>PM Slots</label>
                  <input type="number" name="available_date[${dateStr}][pm]" class="form-control" min="0" value="5">
                </div>
              </div>
              <input type="hidden" name="available_date[${dateStr}][date]" value="${dateStr}">
              <button type="button" class="btn btn-sm btn-danger mt-2 remove-btn">Remove</button>
            </div>`;
          $('#dateInputs').append(inputHTML);
        }
      });
    }
    calendar.append(cell);
  }

  // 🔁 Rebind previous and next button events
}
$(document).ready(function () {
  // Global delegated handlers
  $(document).on('click', '#prev', function () {
    currentMonth--;
    if (currentMonth < 0) {
      currentMonth = 11;
      currentYear--;
    }
    generateCalendar(currentMonth, currentYear);
  });

  $(document).on('click', '#next', function () {
    currentMonth++;
    if (currentMonth > 11) {
      currentMonth = 0;
      currentYear++;
    }
    generateCalendar(currentMonth, currentYear);
  });
});

// $('#prevMonth').click(() => { currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } generateCalendar(currentMonth, currentYear); });
// $('#nextMonth').click(() => { currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } generateCalendar(currentMonth, currentYear); });

$(document).on('click', '.remove-btn', function () {
  const parent = $(this).closest('[data-date]');
  const date = parent.data('date');
  $(`.calendar-day[data-date="${date}"]`).removeClass('selected');
  parent.remove();
});

$(document).ready(() => {
  generateCalendar(currentMonth, currentYear);
  $('#available-dates-form').on('submit', function (e) {
    e.preventDefault();
    $.ajax({
      url: 'create_available_dates.php',
      method: 'POST',
      data: $(this).serialize(),
      success: function (res) {
        let result;
        try { result = JSON.parse(res); } catch { $('#response-msg').html('<div class="alert alert-danger">Unexpected server response.</div>'); return; }
        if (result.status === 'success') {
          $('#response-msg').html('<div class="alert alert-success">' + result.message + '</div>');
          $('#dateInputs').empty();
          $('.calendar-day.selected').removeClass('selected');
        } else {
          $('#response-msg').html('<div class="alert alert-danger">' + result.message + '</div>');
        }
      },
      error: function () {
        $('#response-msg').html('<div class="alert alert-danger">Error submitting form.</div>');
      }
    });
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
