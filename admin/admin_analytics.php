<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// DB Connection
$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ====== SUMMARY COUNTS ======
function getCount($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row ? reset($row) : 0;
}

$totalAppointments = getCount($conn, "SELECT COUNT(*) FROM appointments");
$completedAppointments = getCount($conn, "SELECT COUNT(*) FROM appointments WHERE status='Completed'");
$pendingAppointments = getCount($conn, "SELECT COUNT(*) FROM appointments WHERE status='Pending'");
$todaysAppointments = getCount($conn, "SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_for) = CURDATE()");
$registeredResidents = getCount($conn, "SELECT COUNT(*) FROM auth WHERE role='Resident'");
$lguPersonnel = getCount($conn, "SELECT COUNT(*) FROM auth WHERE role='LGU Personnel'");
$totalDepartments = getCount($conn, "SELECT COUNT(*) FROM departments");
// $totalFeedbacks = getCount($conn, "SELECT COUNT(*) FROM feedback");
// $totalCommendations = getCount($conn, "SELECT COUNT(*) FROM commendations");
// $totalComplaints = getCount($conn, "SELECT COUNT(*) FROM complaints");

// ====== APPOINTMENTS BY DEPARTMENT ======
$deptLabels = [];
$deptCounts = [];
$res = $conn->query("SELECT d.name, COUNT(a.id) AS total FROM appointments a 
                     JOIN departments d ON a.department_id = d.id 
                     GROUP BY d.id ORDER BY d.name");
while ($row = $res->fetch_assoc()) {
    $deptLabels[] = $row['name'];
    $deptCounts[] = $row['total'];
}

// ====== APPOINTMENTS BY SERVICE ======
$serviceLabels = [];
$serviceCounts = [];
$res = $conn->query("SELECT s.service_name, COUNT(a.id) AS total 
                     FROM appointments a 
                     JOIN department_services s ON a.service_id = s.id 
                     GROUP BY s.id ORDER BY total DESC LIMIT 8");
while ($row = $res->fetch_assoc()) {
    $serviceLabels[] = $row['service_name'];
    $serviceCounts[] = $row['total'];
}

// ====== MONTHLY TREND (Current Year) ======
$monthlyData = array_fill(1, 12, 0);
$res = $conn->query("SELECT MONTH(requested_at) AS m, COUNT(*) AS c 
                     FROM appointments 
                     WHERE YEAR(requested_at)=YEAR(CURDATE()) 
                     GROUP BY MONTH(requested_at)");
while ($row = $res->fetch_assoc()) {
    $monthlyData[(int)$row['m']] = (int)$row['c'];
}
$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// ====== RESIDENT DEMOGRAPHICS ======
$sexLabels = [];
$sexCounts = [];
$res = $conn->query("SELECT sex, COUNT(*) as total FROM residents GROUP BY sex");
while ($row = $res->fetch_assoc()) {
    $sexLabels[] = ucfirst($row['sex']);
    $sexCounts[] = $row['total'];
}

// Age Groups
$ageGroups = [
    '<18' => 0,
    '18-30' => 0,
    '31-50' => 0,
    '50+' => 0
];
$res = $conn->query("SELECT TIMESTAMPDIFF(YEAR, birthday, CURDATE()) AS age FROM residents WHERE birthday IS NOT NULL");
while ($row = $res->fetch_assoc()) {
    $age = (int)$row['age'];
    if ($age < 18) $ageGroups['<18']++;
    elseif ($age <= 30) $ageGroups['18-30']++;
    elseif ($age <= 50) $ageGroups['31-50']++;
    else $ageGroups['50+']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Enhanced Header & Responsive Analytics CSS */

body { 
    font-family: "Segoe UI", sans-serif; 
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    margin: 0;
    padding: 0;
}

/* Enhanced Header */
header { 
    background: linear-gradient(135deg, #0D92F4 0%, #27548A 100%);
    padding: 20px 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    position: relative;
    top: 1;
    backdrop-filter: blur(10px);
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1400px;
    margin: 0 auto;
    flex-wrap: wrap;
    gap: 15px;
}




.title { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    font-weight: 700; 
    font-size: 2rem; 
    color: #ffffff;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.title:hover {
    transform: translateY(-2px);
}

.title i { 
    font-size: 2.5rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.datetime { 
    font-size: 1rem; 
    font-weight: 600; 
    color: rgba(255, 255, 255, 0.95);
    background: rgba(255, 255, 255, 0.15);
    padding: 10px 20px;
    border-radius: 25px;
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 8px;
}

.datetime i {
    font-size: 1.1rem;
}

/* Summary Cards - Responsive Grid */
.summary { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
    gap: 20px; 
    margin: 30px;
    padding: 0 10px;
}

.summary-card { 
    background: #fff; 
    padding: 20px; 
    border-radius: 15px; 
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); 
    text-align: left; 
    display: flex; 
    flex-direction: column; 
    gap: 12px; 
    border-left: 6px solid transparent; 
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, rgba(0, 0, 0, 0.03) 0%, transparent 70%);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.summary-card:hover { 
    transform: translateY(-8px); 
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
}

.summary-card .icon { 
    width: 50px; 
    height: 50px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    border-radius: 12px; 
    color: #fff; 
    font-size: 22px;
    transition: all 0.3s ease;
}

.summary-card:hover .icon {
    transform: scale(1.1) rotate(5deg);
}

.summary-card .value { 
    font-size: 28px; 
    font-weight: 700; 
    color: #333;
    line-height: 1;
}

.summary-card .label { 
    font-size: 14px; 
    font-weight: 600; 
    color: #666; 
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Color Themes */
.purple { border-left-color: #6a11cb; } 
.purple .icon { background: linear-gradient(135deg, #6a11cb, #2575fc); }

.pink { border-left-color: #ff6a88; } 
.pink .icon { background: linear-gradient(135deg, #ff6a88, #ff99ac); }

.blue { border-left-color: #36d1dc; } 
.blue .icon { background: linear-gradient(135deg, #36d1dc, #5b86e5); }

.green { border-left-color: #2ecc71; } 
.green .icon { background: linear-gradient(135deg, #2ecc71, #27ae60); }

.orange { border-left-color: #ff9800; } 
.orange .icon { background: linear-gradient(135deg, #ff9800, #ffb74d); }

.red { border-left-color: #e74c3c; } 
.red .icon { background: linear-gradient(135deg, #e74c3c, #ff6b6b); }

/* Small Cards Variant */
.summary.small-cards {
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    margin-top: 10px;
}

.summary-card.small {
    padding: 15px;
    border-radius: 10px;
    gap: 8px;
}

.summary-card.small .icon {
    width: 38px;
    height: 38px;
    font-size: 18px;
    border-radius: 8px;
}

.summary-card.small .value {
    font-size: 20px;
}

.summary-card.small .label {
    font-size: 12px;
}

/* Charts Section - Responsive Grid */
.charts { 
    margin: 30px; 
    padding: 0 10px;
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
    gap: 20px;
}

.card { 
    background: #fff; 
    padding: 20px; 
    border-radius: 15px; 
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    transform: translateY(-4px);
}

.card h5 { 
    font-weight: 700; 
    font-size: 16px; 
    color: #333; 
    margin-bottom: 15px; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    border-bottom: 2px solid #f0f0f0; 
    padding-bottom: 10px;
}

.card h5 i { 
    color: #0D92F4; 
    font-size: 18px;
}

canvas { 
    height: 220px !important;
    max-width: 100%;
}

/* Tablet Responsive (768px - 1024px) */
@media (max-width: 1024px) {
    .summary { 
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
        gap: 15px;
        margin: 20px;
    }
    
    .charts { 
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        margin: 20px;
    }
    
    header {
        padding: 18px 20px;
    }
    
    .title {
        font-size: 1.75rem;
    }
    
    .title i {
        font-size: 2.2rem;
    }
}

/* Mobile Responsive (up to 767px) */
@media (max-width: 767px) {
    header {
        padding: 15px;
    }
    
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .title {
        font-size: 1.5rem;
        gap: 10px;
    }
    
    .title i {
        font-size: 1.8rem;
    }
    
    .datetime {
        font-size: 0.9rem;
        padding: 8px 16px;
        width: 100%;
        justify-content: center;
    }
    
    /* Single column layout for summary cards */
    .summary {
        grid-template-columns: 1fr;
        gap: 15px;
        margin: 15px;
        padding: 0;
    }
    
    .summary-card {
        padding: 18px;
    }
    
    .summary-card .value {
        font-size: 24px;
    }
    
    /* Small cards on mobile */
    .summary.small-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .summary-card.small {
        padding: 12px;
    }
    
    .summary-card.small .value {
        font-size: 18px;
    }
    
    /* Single column for charts */
    .charts {
        grid-template-columns: 1fr;
        gap: 15px;
        margin: 15px;
        padding: 0;
    }
    
    .card {
        padding: 15px;
    }
    
    .card h5 {
        font-size: 15px;
    }
    
    canvas {
        height: 200px !important;
    }
}

/* Extra small devices (up to 480px) */
@media (max-width: 480px) {
    .summary {
        margin: 10px;
    }
    
    .charts {
        margin: 10px;
    }
    
    .summary-card {
        padding: 15px;
        gap: 10px;
    }
    
    .summary-card .icon {
        width: 42px;
        height: 42px;
        font-size: 20px;
    }
    
    .summary-card .value {
        font-size: 22px;
    }
    
    .summary-card .label {
        font-size: 13px;
    }
    
    /* Keep 2 columns for small cards even on tiny screens */
    .summary.small-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .summary-card.small .icon {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }
    
    canvas {
        height: 180px !important;
    }
}

/* Landscape mobile adjustments */
@media (max-width: 767px) and (orientation: landscape) {
    .summary {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .charts {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Animation for cards on load */
.summary-card {
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
}

.summary-card:nth-child(1) { animation-delay: 0.1s; }
.summary-card:nth-child(2) { animation-delay: 0.2s; }
.summary-card:nth-child(3) { animation-delay: 0.3s; }
.summary-card:nth-child(4) { animation-delay: 0.4s; }
.summary-card:nth-child(5) { animation-delay: 0.5s; }
.summary-card:nth-child(6) { animation-delay: 0.6s; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
    </style>
</head>
<body>
<header>
    <div class="header-container">
        <h2 class="title"><i class='bx bxs-dashboard bx-tada'></i>Admin Dashboard</h2>
        <div class="datetime"><span id="today-date"></span> | <span id="clock"></span></div>
    </div>
</header>

<!-- Summary Cards -->
<div class="summary">
    <div class="summary-card purple"><div class="icon"><i class='bx bx-calendar-check'></i></div><div class="value"><?= $totalAppointments ?></div><div class="label">Appointments</div></div>
    <!-- <div class="summary-card green"><div class="icon"><i class='bx bx-check-circle'></i></div><div class="value"><?= $completedAppointments ?></div><div class="label">Completed</div></div> -->
    <!-- <div class="summary-card orange"><div class="icon"><i class='bx bx-time'></i></div><div class="value"><?= $pendingAppointments ?></div><div class="label">Pending</div></div> -->
    <!-- <div class="summary-card blue"><div class="icon"><i class='bx bx-calendar-day'></i></div><div class="value"><?= $todaysAppointments ?></div><div class="label">Today</div></div> -->
    <div class="summary-card pink"><div class="icon"><i class='bx bx-user'></i></div><div class="value"><?= $registeredResidents ?></div><div class="label">Registered Residents</div></div>
    <div class="summary-card purple"><div class="icon"><i class='bx bx-id-card'></i></div><div class="value"><?= $lguPersonnel ?></div><div class="label">LGU Personnel</div></div>
    <div class="summary-card green"><div class="icon"><i class='bx bx-building'></i></div><div class="value"><?= $totalDepartments ?></div><div class="label">Departments</div></div>   
</div>

<!-- Feedback Row -->
<!-- <div class="summary small-cards">
    <div class="summary-card orange small">
        <div class="icon"><i class='bx bx-message-detail'></i></div>
        <div class="value"><?= $totalFeedbacks ?></div>
        <div class="label">Feedback</div>
    </div>
    <div class="summary-card blue small">
        <div class="icon"><i class='bx bx-like'></i></div>
        <div class="value"><?= $totalCommendations ?></div>
        <div class="label">Commendations</div>
    </div>
    <div class="summary-card red small">
        <div class="icon"><i class='bx bx-dislike'></i></div>
        <div class="value"><?= $totalComplaints ?></div>
        <div class="label">Complaints</div>
    </div>
</div> -->

<!-- Charts Section -->
<div class="charts">
    <div class="card">
        <h5><i class='bx bx-bar-chart-alt-2'></i> Appointments by Department</h5>
        <canvas id="deptChart"></canvas>
    </div>
    <div class="card">
        <h5><i class='bx bx-category-alt'></i> Appointments by Service</h5>
        <canvas id="serviceChart"></canvas>
    </div>
    <div class="card">
        <h5><i class='bx bx-line-chart'></i> Monthly Appointment Trend</h5>
        <canvas id="monthChart"></canvas>
    </div>
    <div class="card">
        <h5><i class='bx bx-male-female'></i> Residents by Sex</h5>
        <canvas id="sexChart"></canvas>
    </div>
    <div class="card">
        <h5><i class='bx bx-group'></i> Residents by Age Group</h5>
        <canvas id="ageChart"></canvas>
    </div>
</div>

<script>
// Date & Time
function updateDateTime() {
    const dateEl = document.getElementById("today-date");
    const clockEl = document.getElementById("clock");
    if (dateEl && clockEl) {
        const now = new Date();
        const options = { weekday:'long', year:'numeric', month:'long', day:'numeric' };
        dateEl.textContent = now.toLocaleDateString('en-PH', options);
        clockEl.textContent = now.toLocaleTimeString('en-PH', { hour12:false });
    }
}
setInterval(updateDateTime, 1000); 
updateDateTime();

// Store chart instances to prevent duplicates
window.chartInstances = window.chartInstances || {};

// Charts initialization function (make it global)
window.initializeCharts = function() {
    // Destroy existing charts first
    Object.values(window.chartInstances).forEach(chart => {
        if (chart) chart.destroy();
    });
    window.chartInstances = {};

    // Check if elements exist
    if (!document.getElementById('deptChart')) return;

    window.chartInstances.deptChart = new Chart(document.getElementById('deptChart'), {
        type: 'bar',
        data: { labels: <?= json_encode($deptLabels) ?>, datasets: [{ label: 'Appointments', data: <?= json_encode($deptCounts) ?>, backgroundColor:'#36d1dc' }] },
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{precision:0}} } }
    });

    window.chartInstances.serviceChart = new Chart(document.getElementById('serviceChart'), {
        type: 'bar',
        data: { labels: <?= json_encode($serviceLabels) ?>, datasets: [{ label: 'Appointments', data: <?= json_encode($serviceCounts) ?>, backgroundColor:'#9c27b0' }] },
        options: { indexAxis:'y', responsive:true, plugins:{legend:{display:false}}, scales:{ x:{beginAtZero:true,ticks:{precision:0}} } }
    });

    window.chartInstances.monthChart = new Chart(document.getElementById('monthChart'), {
        type: 'line',
        data: { labels: <?= json_encode($monthLabels) ?>, datasets: [{ label:'Appointments', data: <?= json_encode(array_values($monthlyData)) ?>, borderColor:'#673ab7', backgroundColor:'rgba(103,58,183,0.2)', fill:true, tension:0.3 }] },
        options: { responsive:true, scales:{ y:{beginAtZero:true,ticks:{precision:0}} } }
    });

    window.chartInstances.sexChart = new Chart(document.getElementById('sexChart'), {
        type: 'pie',
        data: { labels: <?= json_encode($sexLabels) ?>, datasets: [{ data: <?= json_encode($sexCounts) ?>, backgroundColor:['#4caf50','#2196f3','#ff9800'] }] }
    });

    window.chartInstances.ageChart = new Chart(document.getElementById('ageChart'), {
        type: 'bar',
        data: { labels: <?= json_encode(array_keys($ageGroups)) ?>, datasets: [{ label: 'Residents', data: <?= json_encode(array_values($ageGroups)) ?>, backgroundColor:'#ff9800' }] },
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{precision:0}} } }
    });
};

// Try to initialize if page is already loaded
initializeCharts();
</script>
</body>
</html>
