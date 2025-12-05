
<?php
session_start();
include '../conn.php';

// Ensure only Admin / LGU Personnel can access
if (!isset($_SESSION['auth_id']) || !in_array($_SESSION['role'], ['Admin','LGU Personnel'])) {
    header("Location: ../login.php");
    exit();
}

$authId = $_SESSION['auth_id'];
$role   = $_SESSION['role'];

$departmentId = null;
$departmentName = null;

if ($role === 'LGU Personnel') {
    $stmt = $pdo->prepare("SELECT lp.department_id, d.name 
                           FROM lgu_personnel lp 
                           JOIN departments d ON lp.department_id = d.id 
                           WHERE lp.auth_id = ?");
    $stmt->execute([$authId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $departmentId = $row['department_id'];
    $departmentName = $row['name'];
}

// ================== TOTALS ==================
$query = "SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) AS completed
          FROM appointments";
if ($departmentId) $query .= " WHERE department_id = :dept";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$totals = $stmt->fetch(PDO::FETCH_ASSOC);

// ================== TODAY'S APPOINTMENTS ==================
$query = "SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_for) = CURDATE()";
if ($departmentId) $query .= " AND department_id = :dept";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$todayAppointments = $stmt->fetchColumn();

// ================== APPOINTMENT STATUS ==================
$query = "SELECT status, COUNT(*) as total FROM appointments";
if ($departmentId) $query .= " WHERE department_id = :dept";
$query .= " GROUP BY status";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// ================== APPOINTMENTS BY SERVICE ==================
$query = "SELECT s.service_name, COUNT(a.id) as total
          FROM appointments a
          JOIN department_services s ON a.service_id = s.id";
if ($departmentId) $query .= " WHERE a.department_id = :dept";
$query .= " GROUP BY s.id";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$serviceAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================== MONTHLY TRENDS ==================
$query = "SELECT DATE_FORMAT(requested_at, '%Y-%m') as month, COUNT(*) as total
          FROM appointments";
if ($departmentId) $query .= " WHERE department_id = :dept";
$query .= " GROUP BY month ORDER BY month ASC";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$monthlyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

$months = array_column($monthlyTrends, 'month');
$totalsMonthly = array_column($monthlyTrends, 'total');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Personnel Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Page Header Styles - Enhanced with Blue Schema */
        header {
            padding: 0;
            margin-top: -1.3rem;
            background: transparent;
            border: none;
        }

        .page-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #3d6bb3 100%);
            padding: 25px 30px;
            margin: 20px;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(30, 60, 114, 0.25),
                        0 4px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            50% {
                transform: translate(-20px, -20px) scale(1.1);
            }
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .title-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4);
            transition: all 0.3s ease;
        }

        .title-icon:hover {
            transform: translateY(-3px) rotate(5deg);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.6);
        }

        .title-icon i {
            font-size: 28px;
            color: #ffffff;
        }

        .title-content h2 {
            font-weight: 700;
            font-size: 1.75rem;
            color: #ffffff;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            letter-spacing: 0.5px;
        }

        .title-content p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.85);
            margin: 5px 0 0 0;
            font-weight: 500;
        }

        .header-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .info-card i {
            font-size: 20px;
            color: #ffffff;
            opacity: 0.9;
        }

        .info-card .info-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .info-card .info-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-card .info-value {
            font-size: 0.95rem;
            color: #ffffff;
            font-weight: 700;
        }

        .department-badge {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            padding: 10px 20px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .department-badge i {
            font-size: 18px;
            color: #ffffff;
        }

        .department-badge span {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
        }

        main { 
            padding: 20px; 
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: #fff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-left: 6px solid transparent;
        }

        .summary-card.purple { border-left-color: #1e3c72; }
        .summary-card.pink { border-left-color: #2a5298; }
        .summary-card.blue { border-left-color: #4a90e2; }
        .summary-card.green { border-left-color: #357abd; }

        .summary-card .icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: #fff;
            font-size: 20px;
        }

        .summary-card .icon.purple {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
        }
        .summary-card .icon.pink {
            background: linear-gradient(135deg, #2a5298, #3d6bb3);
        }
        .summary-card .icon.blue {
            background: linear-gradient(135deg, #4a90e2, #357abd);
        }
        .summary-card .icon.green {
            background: linear-gradient(135deg, #357abd, #2a5298);
        }

        .summary-card .value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .summary-card .label {
            font-size: 13px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 8px;
        }

        .card h3 i {
            font-size: 18px;
            color: #2a5298;
        }

        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        canvas {
            max-width: 100%;
        }

        /* Tablet devices */
        @media (max-width: 768px) {
            .page-header {
                padding: 20px;
                margin: 15px;
                border-radius: 16px;
            }

            .page-header::before,
            .page-header::after {
                width: 250px;
                height: 250px;
            }

            .title-section {
                gap: 12px;
                width: 100%;
            }

            .title-icon {
                width: 50px;
                height: 50px;
            }

            .title-icon i {
                font-size: 24px;
            }

            .title-content h2 {
                font-size: 1.4rem;
            }

            .title-content p {
                font-size: 0.85rem;
            }

            .header-info {
                gap: 12px;
                width: 100%;
            }

            .info-card {
                padding: 10px 16px;
                flex: 1;
                min-width: 140px;
            }

            .info-card i {
                font-size: 18px;
            }

            .info-card .info-label {
                font-size: 0.65rem;
            }

            .info-card .info-value {
                font-size: 0.9rem;
            }

            .department-badge {
                padding: 8px 16px;
                width: 100%;
                justify-content: center;
            }

            .summary {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .summary-card {
                padding: 15px;
            }

            .summary-card .value {
                font-size: 20px;
            }

            .summary-card .label {
                font-size: 11px;
            }

            .grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .chart-container {
                height: 220px;
            }
        }

        /* Mobile devices */
        @media (max-width: 480px) {
            .page-header {
                padding: 16px;
                margin: 12px;
                border-radius: 14px;
            }

            .page-header::before,
            .page-header::after {
                width: 180px;
                height: 180px;
            }

            .header-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .title-section {
                gap: 10px;
            }

            .title-icon {
                width: 45px;
                height: 45px;
            }

            .title-icon i {
                font-size: 22px;
            }

            .title-content h2 {
                font-size: 1.2rem;
            }

            .title-content p {
                font-size: 0.8rem;
            }

            .header-info {
                flex-direction: column;
                gap: 10px;
                width: 100%;
            }

            .info-card {
                width: 100%;
                padding: 12px 16px;
            }

            .info-card i {
                font-size: 20px;
            }

            .info-card .info-text {
                flex: 1;
            }

            .info-card .info-label {
                font-size: 0.65rem;
            }

            .info-card .info-value {
                font-size: 0.95rem;
            }

            .department-badge {
                padding: 10px 16px;
                width: 100%;
            }

            .department-badge i {
                font-size: 16px;
            }

            .department-badge span {
                font-size: 0.9rem;
            }

            main {
                padding: 15px;
            }

            .summary {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .summary-card {
                padding: 15px;
                flex-direction: row;
                align-items: center;
                gap: 15px;
            }

            .summary-card .icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
                flex-shrink: 0;
            }

            .summary-card .content {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .summary-card .value {
                font-size: 22px;
            }

            .summary-card .label {
                font-size: 10px;
            }

            .card {
                padding: 15px;
            }

            .card h3 {
                font-size: 14px;
                gap: 6px;
            }

            .card h3 i {
                font-size: 16px;
            }

            .chart-container {
                height: 200px;
            }
        }

        /* Very small devices */
        @media (max-width: 360px) {
            .page-header {
                padding: 14px;
                margin: 10px;
            }

            .title-content h2 {
                font-size: 1.1rem;
            }

            .title-icon {
                width: 40px;
                height: 40px;
            }

            .title-icon i {
                font-size: 20px;
            }

            .info-card {
                padding: 10px 14px;
            }

            .summary-card .value {
                font-size: 20px;
            }

            .chart-container {
                height: 180px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="page-header">
            <div class="header-container">
                <div class="title-section">
                    <div class="title-icon">
                        <i class='bx bxs-dashboard bx-tada'></i>
                    </div>
                    <div class="title-content">
                        <h2>LGU Personnel Analytics</h2>
                        <p>Real-time dashboard & insights</p>
                    </div>
                </div>

                <div class="header-info">
                    <div class="info-card">
                        <i class='bx bx-calendar'></i>
                        <div class="info-text">
                            <span class="info-label">Date</span>
                            <span class="info-value" id="today-date"></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <i class='bx bx-time-five'></i>
                        <div class="info-text">
                            <span class="info-label">Time</span>
                            <span class="info-value" id="clock"></span>
                        </div>
                    </div>

                    <?php if ($departmentName): ?>
                    <div class="department-badge">
                        <i class='bx bx-building'></i>
                        <span><?= htmlspecialchars($departmentName) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main>
        <!-- Summary Cards -->
        <div class="summary">
            <div class="summary-card purple">
                <div class="icon purple"><i class="fas fa-calendar-check"></i></div>
                <div class="content">
                    <div class="value"><?= $totals['total'] ?? 0 ?></div>
                    <div class="label">Total Appointments</div>
                </div>
            </div>

            <div class="summary-card pink">
                <div class="icon pink"><i class="fas fa-clock"></i></div>
                <div class="content">
                    <div class="value"><?= $totals['pending'] ?? 0 ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>

            <div class="summary-card blue">
                <div class="icon blue"><i class="fas fa-check-circle"></i></div>
                <div class="content">
                    <div class="value"><?= $totals['completed'] ?? 0 ?></div>
                    <div class="label">Completed</div>
                </div>
            </div>

            <div class="summary-card green">
                <div class="icon green"><i class="fas fa-calendar-day"></i></div>
                <div class="content">
                    <div class="value"><?= $todayAppointments ?? 0 ?></div>
                    <div class="label">Appointments Today</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid">
            <div class="card">
                <h3>
                    <i class='bx bx-pie-chart-alt-2'></i> Appointments Status
                </h3>
                <div class="chart-container">
                    <canvas id="apptChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>
                    <i class='bx bx-category-alt'></i> Appointments by Service
                </h3>
                <div class="chart-container">
                    <canvas id="serviceChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>
                    <i class='bx bx-line-chart'></i> Appointments Over Time (Monthly)
                </h3>
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </main>

<script>
function initializeCharts() {
    // Appointment Status - Doughnut Chart with Animation
    new Chart(document.getElementById('apptChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($appointments)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($appointments)) ?>,
                backgroundColor: ['#4caf50', '#ff9800'],
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 15
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 2000,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    position: window.innerWidth < 480 ? 'bottom' : 'right',
                    labels: {
                        padding: 15,
                        font: {
                            size: 13,
                            weight: 'bold'
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return ` ${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Appointments by Service - Bar Chart with Animation
    new Chart(document.getElementById('serviceChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($serviceAppointments, 'service_name')) ?>,
            datasets: [{
                label: 'Appointments',
                data: <?= json_encode(array_column($serviceAppointments, 'total')) ?>,
                backgroundColor: '#2a5298',
                borderRadius: 8,
                borderSkipped: false,
                hoverBackgroundColor: '#1e3c72'
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            indexAxis: 'y',
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart',
                delay: (context) => {
                    let delay = 0;
                    if (context.type === 'data' && context.mode === 'default') {
                        delay = context.dataIndex * 150;
                    }
                    return delay;
                }
            },
            scales: { 
                x: { 
                    beginAtZero: true,
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(42, 82, 152, 0.9)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return ` Appointments: ${context.parsed.y}`;
                        }
                    }
                }
            }
        }
    });
}

// Add fade-in animation for chart containers
function animateChartContainers() {
    const chartContainers = document.querySelectorAll('.chart-container, .card');
    chartContainers.forEach((container, index) => {
        container.style.opacity = '0';
        container.style.transform = 'translateY(30px)';
        container.style.transition = 'all 0.6s ease';
        
        setTimeout(() => {
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        }, index * 150);
    });
}

// Animate header on load
function animateHeader() {
    const pageHeader = document.querySelector('.page-header');
    if (pageHeader) {
        pageHeader.style.opacity = '0';
        pageHeader.style.transform = 'translateY(-20px)';
        pageHeader.style.transition = 'all 0.8s ease';
        
        setTimeout(() => {
            pageHeader.style.opacity = '1';
            pageHeader.style.transform = 'translateY(0)';
        }, 100);
    }
}

// Initialize charts after DOM is ready and visible
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            animateHeader();
            animateChartContainers();
            initializeCharts();
        }, 100);
    });
} else {
    setTimeout(() => {
        animateHeader();
        animateChartContainers();
        initializeCharts();
    }, 100);
}

function updateDateTime() {
    const now = new Date();
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    const dateElement = document.getElementById("today-date");
    const clockElement = document.getElementById("clock");
    
    if (dateElement) {
        dateElement.textContent = now.toLocaleDateString('en-US', options);
    }
    if (clockElement) {
        clockElement.textContent = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
    }
}

setInterval(updateDateTime, 1000);
updateDateTime();
</script>