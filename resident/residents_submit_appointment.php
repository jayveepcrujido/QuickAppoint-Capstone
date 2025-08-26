// residents_submit_appointment.php
<?php
session_start();
include '../conn.php';
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$department_id = $_POST['department_id'] ?? null;
$available_date_id = $_POST['available_date_id'] ?? null;
$service_id = $_POST['service'] ?? null;
$reason = $_POST['reason'] ?? '';
$slot_period = $_POST['slot_period'] ?? null; // 'am' or 'pm'
$uploadDir = 'uploads/ids/';

if (!$department_id || !$available_date_id || !$service_id || !$reason || !$slot_period || !isset($_FILES['valid_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

if (!in_array($slot_period, ['am', 'pm'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid slot period']);
    exit();
}

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Upload valid ID
$filename = basename($_FILES['valid_id']['name']);
$targetPath = $uploadDir . uniqid() . '_' . $filename;

if (!move_uploaded_file($_FILES['valid_id']['tmp_name'], $targetPath)) {
    echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
    exit();
}

try {
    // Fetch the selected available date row
    $stmt = $pdo->prepare("SELECT * FROM available_dates WHERE id = ?");
    $stmt->execute([$available_date_id]);
    $available = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$available) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid available date']);
        exit();
    }

    $slot_key = $slot_period . '_slots';
    $booked_key = $slot_period . '_booked';

    if ($available[$booked_key] >= $available[$slot_key]) {
        echo json_encode(['status' => 'error', 'message' => 'No available slots for selected period']);
        exit();
    }

    $base_date = date('Y-m-d', strtotime($available['date_time']));
    $scheduled_for = $base_date . ($slot_period === 'am' ? ' 09:00:00' : ' 14:00:00');

    // Assign random personnel
    $stmt = $pdo->prepare("SELECT u.id FROM users u JOIN auth a ON u.id = a.user_id WHERE u.department_id = ? AND a.role = 'LGU Personnel' ORDER BY RAND() LIMIT 1");
    $stmt->execute([$department_id]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$personnel) {
        echo json_encode(['status' => 'error', 'message' => 'No LGU Personnel found']);
        exit();
    }

    $personnel_id = $personnel['id'];

    // Insert appointment
    $stmt = $pdo->prepare("INSERT INTO appointments (
    user_id, department_id, service_id, available_date_id, valid_id_path, reason, status, requested_at, personnel_id, scheduled_for
) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
$stmt->execute([
    $user_id,
    $department_id,
    $service_id,
    $available_date_id,
    $targetPath,
    $reason,
    'Pending', // include status
    $personnel_id,
    $scheduled_for
]);


    $appointmentId = $pdo->lastInsertId();

    // Update booked slots
    $pdo->prepare("UPDATE available_dates SET {$booked_key} = {$booked_key} + 1 WHERE id = ?")
        ->execute([$available_date_id]);

    echo json_encode(['status' => 'success', 'appointment_id' => $appointmentId]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}