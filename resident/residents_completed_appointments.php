<?php 
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';
$authId = $_SESSION['auth_id'];

// ✅ Resolve resident_id from auth_id
$stmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ? LIMIT 1");
$stmt->execute([$authId]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    die("Resident profile not found.");
}
$residentId = $resident['id'];

// ✅ Fetch completed appointments
$queryCompleted = "
    SELECT a.id, a.transaction_id, a.scheduled_for, a.has_sent_feedback, d.name AS department_name, s.service_name
    FROM appointments a
    JOIN departments d ON a.department_id = d.id
    JOIN department_services s ON a.service_id = s.id
    WHERE a.resident_id = :resident_id AND a.status = 'Completed'
    ORDER BY a.scheduled_for ASC
";
$stmtCompleted = $pdo->prepare($queryCompleted);
$stmtCompleted->execute(['resident_id' => $residentId]);
$completedAppointments = $stmtCompleted->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Modern Card Styles */
    .appointments-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1.5rem;
    }

    .page-header {
        background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        border-radius: 20px;
        padding: 2rem 2.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(52, 152, 219, 0.2);
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    .page-header h3 {
        color: white;
        font-weight: 700;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        position: relative;
        z-index: 1;
    }

    .page-header p {
        color: rgba(255, 255, 255, 0.9);
        margin: 0;
        font-size: 1rem;
        position: relative;
        z-index: 1;
    }

    .header-icon {
        background: rgba(255, 255, 255, 0.2);
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    /* Empty State */
    .empty-state {
        background: white;
        border-radius: 16px;
        padding: 3rem 2rem;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .empty-state-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.5rem;
        font-size: 3rem;
        color: #3498db;
    }

    .empty-state h5 {
        color: #2c3e50;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: #7f8c8d;
        margin: 0;
    }

    /* Appointment Cards */
    .appointment-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border-left: 4px solid #27ae60;
        position: relative;
        overflow: hidden;
    }

    .appointment-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), transparent);
        border-radius: 0 0 0 100%;
    }

    .appointment-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(39, 174, 96, 0.15);
    }

    .appointment-number {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: linear-gradient(135deg, #27ae60, #2ecc71);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
    }

    .card-header-section {
        margin-bottom: 1.25rem;
    }

    .transaction-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #27ae60, #2ecc71);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.9rem;
        box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
    }

    .info-grid {
        display: grid;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .info-icon {
        width: 38px;
        height: 38px;
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #27ae60;
        font-size: 1.1rem;
    }

    .info-content {
        flex: 1;
    }

    .info-label {
        font-size: 0.75rem;
        color: #7f8c8d;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .info-value {
        color: #2c3e50;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .schedule-highlight {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        padding: 0.75rem 1rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }

    .schedule-icon {
        width: 40px;
        height: 40px;
        background: #28a745;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }

    .schedule-text {
        flex: 1;
    }

    .schedule-date {
        font-weight: 700;
        color: #155724;
        font-size: 1rem;
        margin-bottom: 0.125rem;
    }

    .schedule-time {
        font-size: 0.85rem;
        color: #155724;
        opacity: 0.8;
    }

    /* Feedback Button */
    .feedback-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        border: none;
        cursor: pointer;
    }

    .feedback-btn:hover {
        background: linear-gradient(135deg, #2980b9, #21618c);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(52, 152, 219, 0.4);
        color: white;
        text-decoration: none;
    }

    .feedback-btn i {
        font-size: 1rem;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .appointments-container {
            padding: 1rem;
        }

        .page-header {
            padding: 1.5rem 1.25rem;
            border-radius: 16px;
        }

        .page-header h3 {
            font-size: 1.35rem;
        }

        .page-header p {
            font-size: 0.9rem;
        }

        .header-icon {
            width: 42px;
            height: 42px;
            font-size: 1.25rem;
        }

        .appointment-card {
            padding: 1.25rem;
        }

        .appointment-number {
            width: 35px;
            height: 35px;
            font-size: 0.85rem;
        }

        .transaction-badge {
            font-size: 0.85rem;
            padding: 0.4rem 0.85rem;
        }

        .info-grid {
            gap: 0.85rem;
        }

        .info-icon {
            width: 35px;
            height: 35px;
            font-size: 1rem;
        }

        .schedule-highlight {
            padding: 0.65rem 0.85rem;
        }

        .schedule-icon {
            width: 35px;
            height: 35px;
            font-size: 1.1rem;
        }

        .schedule-date {
            font-size: 0.95rem;
        }

        .feedback-btn {
            width: 100%;
            justify-content: center;
            padding: 0.65rem 1.25rem;
            font-size: 0.85rem;
        }
    }

    @media (max-width: 480px) {
        .page-header h3 {
            font-size: 1.2rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .info-item {
            flex-direction: column;
            gap: 0.5rem;
        }
    }

    @media (min-width: 769px) {
        .info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<div class="appointments-container">
    <!-- Page Header -->
    <div class="page-header">
        <h3>
            <div class="header-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <span>Completed Appointments</span>
        </h3>
        <p>View all your successfully completed appointments</p>
    </div>

    <?php if (empty($completedAppointments)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h5>No Completed Appointments</h5>
            <p>You have no completed appointments yet.</p>
        </div>
    <?php else: ?>
        <!-- Appointment Cards -->
        <?php foreach ($completedAppointments as $index => $appt): ?>
            <div class="appointment-card">
                <div class="appointment-number"><?= $index + 1 ?></div>

                <div class="card-header-section">
                    <div class="transaction-badge">
                        <i class="fas fa-hashtag"></i>
                        <?= htmlspecialchars($appt['transaction_id']) ?>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Department</div>
                            <div class="info-value"><?= htmlspecialchars($appt['department_name']) ?></div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-concierge-bell"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Service</div>
                            <div class="info-value"><?= htmlspecialchars($appt['service_name']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="schedule-highlight">
                    <div class="schedule-icon">
                        <i class="far fa-calendar-alt"></i>
                    </div>
                    <div class="schedule-text">
                        <div class="schedule-date">
                            <?= date('F d, Y', strtotime($appt['scheduled_for'])) ?>
                        </div>
                        <div class="schedule-time">
                            <i class="far fa-clock"></i>
                            <?= date('h:i A', strtotime($appt['scheduled_for'])) ?>
                        </div>
                    </div>
                </div>

            <?php if ($appt['has_sent_feedback'] == 1): ?>
                <button class="feedback-btn" style="background: linear-gradient(135deg, #95a5a6, #7f8c8d); cursor: not-allowed; opacity: 0.7;" disabled>
                    <i class="fas fa-check-circle"></i>
                    <span>Feedback Already Submitted</span>
                </button>
            <?php else: ?>
                <a href="feedback_form.php?appointment_id=<?= $appt['id'] ?>" class="feedback-btn">
                    <i class="fas fa-comment-dots"></i>
                    <span>Answer Feedback</span>
                </a>
            <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>