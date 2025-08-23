<?php
session_start(); // Start session

$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_q_a";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$deptQuery = "SELECT department_id FROM users WHERE id = ?";
$stmt = $conn->prepare($deptQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($departmentId);
$stmt->fetch();
$stmt->close();

// Total Appointments
$result = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE department_id = $departmentId");
$totalAppointments = $result->fetch_assoc()['total'];

// Pending Appointments
$result = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE department_id = $departmentId AND status='Pending'");
$pendingAppointments = $result->fetch_assoc()['total'];

// Completed Appointments
$result = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE department_id = $departmentId AND status='Completed'");
$completedAppointments = $result->fetch_assoc()['total'];

// Appointments per Service
$servicesData = [];
$serviceQuery = "SELECT ds.service_name, COUNT(a.id) AS total 
                 FROM department_services ds
                 LEFT JOIN appointments a ON ds.id = a.service_id
                 WHERE ds.department_id = $departmentId
                 GROUP BY ds.service_name";
$res = $conn->query($serviceQuery);
while ($row = $res->fetch_assoc()) {
    $servicesData[$row['service_name']] = (int)$row['total'];
}

// Gender distribution
$genderData = [];
$genderQuery = "SELECT u.sex, COUNT(*) AS total
                FROM users u
                JOIN appointments a ON u.id = a.user_id
                WHERE a.department_id = $departmentId AND u.sex IS NOT NULL
                GROUP BY u.sex";
$res = $conn->query($genderQuery);
while ($row = $res->fetch_assoc()) {
    $genderData[$row['sex']] = (int)$row['total'];
}

// Slots (AM/PM booked by weekday)
$slotData = [
    "dates" => ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
    "am" => [],
    "pm" => []
];

$weekdays = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
foreach ($weekdays as $day) {
    $query = "SELECT 
                SUM(am_booked) as am_total, 
                SUM(pm_booked) as pm_total 
              FROM available_dates 
              WHERE DAYNAME(date_time) = '$day' AND department_id = $departmentId";
    $res = $conn->query($query);
    $row = $res->fetch_assoc();
    $slotData['am'][] = (int) $row['am_total'];
    $slotData['pm'][] = (int) $row['pm_total'];
}

$monthlyAppointments = array_fill(1, 12, 0);
$monthlyQuery = "SELECT MONTH(scheduled_for) as month, COUNT(*) as count
                 FROM appointments 
                 WHERE department_id = $departmentId AND YEAR(scheduled_for) = YEAR(CURDATE())
                 GROUP BY MONTH(scheduled_for)";
$res = $conn->query($monthlyQuery);
while ($row = $res->fetch_assoc()) {
    $monthlyAppointments[(int)$row['month']] = (int)$row['count'];
}
$monthLabels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

$feedbackAverages = [];
$feedbackQuery = "SELECT d.name, COUNT(f.id) / COUNT(DISTINCT a.id) as avg_feedback
                  FROM departments d
                  LEFT JOIN appointments a ON a.department_id = d.id
                  LEFT JOIN feedback f ON f.appointment_id = a.id
                  GROUP BY d.name";
$res = $conn->query($feedbackQuery);
while ($row = $res->fetch_assoc()) {
    $feedbackAverages[$row['name']] = round($row['avg_feedback'], 2);
}

$peakQuery = "SELECT SUM(am_booked) as am_total, SUM(pm_booked) as pm_total
              FROM available_dates
              WHERE department_id = $departmentId";
$res = $conn->query($peakQuery);
$peakData = $res->fetch_assoc();
$amTotal = (int)$peakData['am_total'];
$pmTotal = (int)$peakData['pm_total'];


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>LGU Personnel Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>
    .card { border-left: 5px solid #5a5cb7; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
    h4 { font-weight: 600; }
    .chart-container { padding: 1rem; background: #fff; border-radius: 10px; }
  </style>
</head>
<body class="bg-light">
<div class="container my-4">
  <h2 class="mb-4 text-center text-primary d-flex align-items-center justify-content-center" style="gap: 10px; font-weight: 600; font-size: 2rem;">
    <i class='bx bxs-dashboard bx-tada' style="font-size: 2.5rem;"></i>
    LGU Personnel Dashboard
  </h2>

  <!-- Cards -->
  <div class="row text-center">
  <!-- Total Appointments -->
  <div class="col-md-4 mb-3">
    <div class="card py-3 px-2 d-flex align-items-center">
      <i class='bx bx-calendar text-primary' style="font-size: 2rem;"></i>
      <h6 class="mt-2 mb-1">Total Appointments</h6>
      <p class="h4 mb-0"><?= $totalAppointments ?></p>
    </div>
  </div>

  <!-- Pending Appointments -->
  <div class="col-md-4 mb-3">
    <div class="card py-3 px-2 d-flex align-items-center">
      <i class='bx bx-time text-warning' style="font-size: 2rem;"></i>
      <h6 class="mt-2 mb-1">Pending</h6>
      <p class="h4 text-warning mb-0"><?= $pendingAppointments ?></p>
    </div>
  </div>

  <!-- Completed Appointments -->
  <div class="col-md-4 mb-3">
    <div class="card py-3 px-2 d-flex align-items-center">
      <i class='bx bx-check-circle text-success' style="font-size: 2rem;"></i>
      <h6 class="mt-2 mb-1">Completed</h6>
      <p class="h4 text-success mb-0"><?= $completedAppointments ?></p>
    </div>
  </div>
</div>

  <div class="row">
  <!-- Monthly Appointments -->
  <div class="col-md-6 mb-4">
    <div class="chart-container">
      <canvas id="monthlyChart"></canvas>
    </div>
  </div>


  <!-- Services Chart -->
  <div class="col-md-6 mb-4">
    <div class="chart-container">
      <canvas id="servicesChart"></canvas>
    </div>
  </div>

  <!-- Peak Booking -->
  <div class="col-md-6 mb-4">
    <div class="chart-container">
      <canvas id="peakChart"></canvas>
    </div>
  </div>


  <!-- Gender Chart -->
  <div class="col-md-6 mb-4">
    <div class="chart-container">
      <canvas id="genderChart"></canvas>
    </div>
  </div>

  <!-- AM/PM Slot Chart (Full width optional) -->
  <div class="col-12 mb-4">
    <div class="chart-container">
      <canvas id="slotChart"></canvas>
    </div>
  </div>

  <!-- Feedback Chart (Admin Only) -->
  <?php if ($_SESSION['role'] === 'Admin'): ?>
  <div class="col-md-6 mb-4">
    <div class="chart-container">
      <canvas id="feedbackChart"></canvas>
    </div>
  </div>
  <?php endif; ?>
</div>



<script>
  // Services Chart
  new Chart(document.getElementById('servicesChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($servicesData)) ?>,
      datasets: [{
        label: 'Appointments',
        data: <?= json_encode(array_values($servicesData)) ?>,
        backgroundColor: '#5a5cb7'
      }]
    },
    options: {
      responsive: true,
      title: { display: true, text: 'Appointments by Service' }
    }
  });

  // Gender Chart
  new Chart(document.getElementById('genderChart'), {
    type: 'pie',
    data: {
      labels: <?= json_encode(array_keys($genderData)) ?>,
      datasets: [{
        data: <?= json_encode(array_values($genderData)) ?>,
        backgroundColor: ['#007bff', '#ff6384']
      }]
    },
    options: {
      responsive: true,
      title: { display: true, text: 'Gender Distribution' }
    }
  });

  // Slot Chart
  new Chart(document.getElementById('slotChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($slotData['dates']) ?>,
      datasets: [
        {
          label: 'AM Booked',
          data: <?= json_encode($slotData['am']) ?>,
          borderColor: '#5a5cb7',
          fill: false
        },
        {
          label: 'PM Booked',
          data: <?= json_encode($slotData['pm']) ?>,
          borderColor: '#28a745',
          fill: false
        }
      ]
    },
    options: {
      responsive: true,
      title: { display: true, text: 'Slots Booked Over Days' }
    }
    
  });

  new Chart(document.getElementById('monthlyChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($monthLabels) ?>,
    datasets: [{
      label: 'Monthly Appointments',
      data: <?= json_encode(array_values($monthlyAppointments)) ?>,
      backgroundColor: '#17a2b8'
    }]
  },
  options: {
    responsive: true,
    title: { display: true, text: 'Monthly Appointments (Current Year)' }
  }
});

<?php if ($_SESSION['role'] === 'Admin'): ?>
new Chart(document.getElementById('feedbackChart'), {
  type: 'horizontalBar',
  data: {
    labels: <?= json_encode(array_keys($feedbackAverages)) ?>,
    datasets: [{
      label: 'Avg. Feedback per Appointment',
      data: <?= json_encode(array_values($feedbackAverages)) ?>,
      backgroundColor: '#ffc107'
    }]
  },
  options: {
    responsive: true,
    title: { display: true, text: 'Feedback Average per Department' },
    scales: {
      x: { beginAtZero: true }
    }
  }
});
<?php endif; ?>

new Chart(document.getElementById('peakChart'), {
  type: 'doughnut',
  data: {
    labels: ['AM Booked', 'PM Booked'],
    datasets: [{
      data: [<?= $amTotal ?>, <?= $pmTotal ?>],
      backgroundColor: ['#007bff', '#28a745']
    }]
  },
  options: {
    responsive: true,
    title: { display: true, text: 'Peak Booking Hours' }
  }
});

</script>
</body>
</html>
