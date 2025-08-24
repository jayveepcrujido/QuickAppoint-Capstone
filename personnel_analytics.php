<?php
session_start();
if (!isset($_SESSION['auth_id']) || !in_array($_SESSION['role'], ['LGU Personnel','Admin'])) {
    header("Location: login.php");
    exit();
}
include 'conn.php';

// 1) Resolve department for the logged-in LGU Personnel
$authId = $_SESSION['auth_id'];
$role = $_SESSION['role'];

// If Personnel, find their department via lgu_personnel.auth_id
$departmentId = null;
if ($role === 'LGU Personnel') {
    $stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ? LIMIT 1");
    $stmt->execute([$authId]);
    $departmentId = $stmt->fetchColumn();
} else if ($role === 'Admin') {
    // Admin can optionally view a department via ?department_id=
    if (isset($_GET['department_id'])) {
        $departmentId = (int)$_GET['department_id'];
    }
}

// If not linked yet, keep page working with zeros
if (!$departmentId) {
    $departmentId = 0;
}

// 2) Totals
$tot = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE department_id = ?");
$tot->execute([$departmentId]);
$totalAppointments = (int)$tot->fetchColumn();

$pend = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE department_id = ? AND status='Pending'");
$pend->execute([$departmentId]);
$pendingAppointments = (int)$pend->fetchColumn();

$comp = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE department_id = ? AND status='Completed'");
$comp->execute([$departmentId]);
$completedAppointments = (int)$comp->fetchColumn();

// 3) Appointments per Service (include services with 0 appointments)
$servicesData = [];
$svc = $pdo->prepare("
    SELECT ds.service_name, COUNT(a.id) AS total
    FROM department_services ds
    LEFT JOIN appointments a 
        ON a.service_id = ds.id AND a.department_id = ds.department_id
    WHERE ds.department_id = ?
    GROUP BY ds.id, ds.service_name
    ORDER BY ds.service_name ASC
");
$svc->execute([$departmentId]);
foreach ($svc->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $servicesData[$row['service_name']] = (int)$row['total'];
}

// 4) Gender distribution (residents on appointments in this department)
$genderData = [];
$g = $pdo->prepare("
    SELECT r.sex, COUNT(*) AS total
    FROM appointments a
    JOIN residents r ON r.id = a.resident_id
    WHERE a.department_id = ? AND r.sex IS NOT NULL AND r.sex <> ''
    GROUP BY r.sex
");
$g->execute([$departmentId]);
foreach ($g->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $genderData[$row['sex']] = (int)$row['total'];
}

// 5) Slots (AM/PM booked by weekday, Mon–Fri) from available_dates.date_time
$slotData = [
    'dates' => ['Monday','Tuesday','Wednesday','Thursday','Friday'],
    'am' => [0,0,0,0,0],
    'pm' => [0,0,0,0,0]
];
$slots = $pdo->prepare("
    SELECT DAYNAME(date_time) as dname, SUM(am_booked) am_total, SUM(pm_booked) pm_total
    FROM available_dates
    WHERE department_id = ?
    GROUP BY DAYNAME(date_time)
");
$slots->execute([$departmentId]);
$byName = [];
foreach ($slots->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $byName[$row['dname']] = ['am' => (int)$row['am_total'], 'pm' => (int)$row['pm_total']];
}
foreach ($slotData['dates'] as $i => $day) {
    if (isset($byName[$day])) {
        $slotData['am'][$i] = $byName[$day]['am'];
        $slotData['pm'][$i] = $byName[$day]['pm'];
    }
}

// 6) Monthly appointments (current year)
$monthlyAppointments = array_fill(1, 12, 0);
$m = $pdo->prepare("
    SELECT MONTH(scheduled_for) AS m, COUNT(*) AS c
    FROM appointments
    WHERE department_id = ? AND scheduled_for IS NOT NULL AND YEAR(scheduled_for) = YEAR(CURDATE())
    GROUP BY MONTH(scheduled_for)
");
$m->execute([$departmentId]);
foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $monthlyAppointments[(int)$row['m']] = (int)$row['c'];
}
$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// 7) Peak booking totals
$p = $pdo->prepare("
    SELECT COALESCE(SUM(am_booked),0) am_total, COALESCE(SUM(pm_booked),0) pm_total
    FROM available_dates
    WHERE department_id = ?
");
$p->execute([$departmentId]);
$peak = $p->fetch(PDO::FETCH_ASSOC) ?: ['am_total'=>0,'pm_total'=>0];
$amTotal = (int)$peak['am_total'];
$pmTotal = (int)$peak['pm_total'];

// 8) Feedback density per department (Admin-only optional)
$feedbackAverages = [];
if ($role === 'Admin') {
    $f = $pdo->query("
        SELECT d.name,
               (COUNT(f.id) / NULLIF(COUNT(DISTINCT a.id),0)) AS avg_feedback
        FROM departments d
        LEFT JOIN appointments a ON a.department_id = d.id
        LEFT JOIN feedback f ON f.appointment_id = a.id
        GROUP BY d.id, d.name
        ORDER BY d.name
    ");
    foreach ($f->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $feedbackAverages[$row['name']] = round((float)$row['avg_feedback'], 3);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>LGU Personnel Analytics</title>
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
    LGU Personnel Analytics
  </h2>

  <?php if ($departmentId === 0): ?>
    <div class="alert alert-warning">Your personnel account is not linked to a department yet. Please contact the admin.</div>
  <?php endif; ?>

  <div class="row text-center">
    <div class="col-md-4 mb-3">
      <div class="card py-3 px-2 d-flex align-items-center">
        <i class='bx bx-calendar text-primary' style="font-size: 2rem;"></i>
        <h6 class="mt-2 mb-1">Total Appointments</h6>
        <p class="h4 mb-0"><?php echo $totalAppointments; ?></p>
      </div>
    </div>

    <div class="col-md-4 mb-3">
      <div class="card py-3 px-2 d-flex align-items-center">
        <i class='bx bx-time text-warning' style="font-size: 2rem;"></i>
        <h6 class="mt-2 mb-1">Pending</h6>
        <p class="h4 text-warning mb-0"><?php echo $pendingAppointments; ?></p>
      </div>
    </div>

    <div class="col-md-4 mb-3">
      <div class="card py-3 px-2 d-flex align-items-center">
        <i class='bx bx-check-circle text-success' style="font-size: 2rem;"></i>
        <h6 class="mt-2 mb-1">Completed</h6>
        <p class="h4 text-success mb-0"><?php echo $completedAppointments; ?></p>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 mb-4">
      <div class="chart-container">
        <canvas id="monthlyChart"></canvas>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="chart-container">
        <canvas id="servicesChart"></canvas>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="chart-container">
        <canvas id="peakChart"></canvas>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="chart-container">
        <canvas id="genderChart"></canvas>
      </div>
    </div>

    <div class="col-12 mb-4">
      <div class="chart-container">
        <canvas id="slotChart"></canvas>
      </div>
    </div>

    <?php if ($role === 'Admin'): ?>
    <div class="col-md-6 mb-4">
      <div class="chart-container">
        <canvas id="feedbackChart"></canvas>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
  const servicesData = <?php echo json_encode($servicesData); ?>;
  const genderData = <?php echo json_encode($genderData); ?>;
  const slotData = <?php echo json_encode($slotData); ?>;
  const monthlyData = <?php echo json_encode(array_values($monthlyAppointments)); ?>;
  const monthLabels = <?php echo json_encode($monthLabels); ?>;
  const feedbackData = <?php echo json_encode($feedbackAverages); ?>;

  new Chart(document.getElementById('servicesChart'), {
    type: 'bar',
    data: {
      labels: Object.keys(servicesData),
      datasets: [{ label: 'Appointments', data: Object.values(servicesData) }]
    },
    options: { responsive: true, plugins: { title: { display: true, text: 'Appointments by Service' } } }
  });

  new Chart(document.getElementById('genderChart'), {
    type: 'pie',
    data: {
      labels: Object.keys(genderData),
      datasets: [{ data: Object.values(genderData) }]
    },
    options: { responsive: true, plugins: { title: { display: true, text: 'Gender Distribution' } } }
  });

  new Chart(document.getElementById('slotChart'), {
    type: 'line',
    data: {
      labels: slotData.dates,
      datasets: [
        { label: 'AM Booked', data: slotData.am },
        { label: 'PM Booked', data: slotData.pm }
      ]
    },
    options: { responsive: true, plugins: { title: { display: true, text: 'Slots Booked Over Days' } } }
  });

  new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: { labels: monthLabels, datasets: [{ label: 'Monthly Appointments', data: monthlyData }] },
    options: { responsive: true, plugins: { title: { display: true, text: 'Monthly Appointments (Current Year)' } } }
  });

  <?php if ($role === 'Admin'): ?>
  new Chart(document.getElementById('feedbackChart'), {
    type: 'bar',
    data: { labels: Object.keys(feedbackData), datasets: [{ label: 'Feedback per Appointment', data: Object.values(feedbackData) }] },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { title: { display: true, text: 'Feedback Density per Department' } },
      scales: { x: { beginAtZero: true } }
    }
  });
  <?php endif; ?>

  new Chart(document.getElementById('peakChart'), {
    type: 'doughnut',
    data: {
      labels: ['AM Booked', 'PM Booked'],
      datasets: [{ data: [<?php echo $amTotal; ?>, <?php echo $pmTotal; ?>] }]
    },
    options: { responsive: true, plugins: { title: { display: true, text: 'Peak Booking Hours' } } }
  });
</script>
</body>
</html>
