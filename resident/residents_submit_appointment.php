//RESIDENT SUBMIT APPOINTMENT
<?php
session_start();
include '../conn.php';
ob_clean();
header('Content-Type: application/json');

// ✅ Must be logged in as Resident and request must be POST
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$auth_id = $_SESSION['auth_id'];

// ✅ Get the corresponding resident_id and name from auth_id
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM residents WHERE auth_id = ? LIMIT 1");
$stmt->execute([$auth_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    echo json_encode(['status' => 'error', 'message' => 'Resident profile not found']);
    exit();
}
$resident_id = $resident['id'];
$resident_name = $resident['first_name'] . ' ' . $resident['last_name'];

// ✅ Collect form inputs
$department_id = $_POST['department_id'] ?? null;
$available_date_id = $_POST['available_date_id'] ?? null;
$service_id = $_POST['service'] ?? null;
$reason = $_POST['reason'] ?? '';
$slot_period = $_POST['slot_period'] ?? null; // 'am' or 'pm'
$uploadDir = 'uploads/appointments/';

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

function generateTransactionId($pdo, $department_id) {
    // Fetch department acronym
    $stmt = $pdo->prepare("SELECT acronym FROM departments WHERE id = ? LIMIT 1");
    $stmt->execute([$department_id]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);

    $acronym = $dept && $dept['acronym'] ? strtoupper($dept['acronym']) : 'GEN';

    do {
        // Format: APPT-ACRONYM-YYYYMMDD-RANDOM6
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $transactionId = 'APPT-' . $acronym . '-' . date('Ymd') . '-' . $random;

        // Ensure uniqueness
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        $exists = $stmt->fetchColumn();
    } while ($exists);

    return $transactionId;
}

// ✅ Upload valid ID
$filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES['valid_id']['name'])); // sanitize filename
$targetPath = $uploadDir . uniqid() . '_' . $filename;

if (!move_uploaded_file($_FILES['valid_id']['tmp_name'], $targetPath)) {
    echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
    exit();
}

try {
    // ✅ Begin transaction for data consistency
    $pdo->beginTransaction();

    // ✅ Fetch the selected available date row
    $stmt = $pdo->prepare("SELECT * FROM available_dates WHERE id = ?");
    $stmt->execute([$available_date_id]);
    $available = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$available) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Invalid available date']);
        exit();
    }

    $slot_key = $slot_period . '_slots';
    $booked_key = $slot_period . '_booked';

    if ($available[$booked_key] >= $available[$slot_key]) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'No available slots for selected period']);
        exit();
    }

    $base_date = date('Y-m-d', strtotime($available['date_time']));
    $scheduled_for = $base_date . ($slot_period === 'am' ? ' 09:00:00' : ' 14:00:00');

    // ✅ Assign random personnel from the same department
    $stmt = $pdo->prepare("
        SELECT lp.id, lp.auth_id
        FROM lgu_personnel lp
        JOIN auth a ON lp.auth_id = a.id
        WHERE lp.department_id = ? AND a.role = 'LGU Personnel'
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute([$department_id]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$personnel) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'No LGU Personnel found']);
        exit();
    }

    $personnel_id = $personnel['id'];
    $personnel_auth_id = $personnel['auth_id'];

    $transactionId = generateTransactionId($pdo, $department_id);

    // ✅ Insert appointment
    $stmt = $pdo->prepare("INSERT INTO appointments (
        transaction_id, resident_id, department_id, service_id, available_date_id,
        valid_id_path, reason, status, requested_at, personnel_id, scheduled_for
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");

    $stmt->execute([
        $transactionId,
        $resident_id,
        $department_id,
        $service_id,
        $available_date_id,
        $targetPath,
        $reason,
        'Pending',
        $personnel_id,
        $scheduled_for
    ]);

    $appointmentId = $pdo->lastInsertId();

    // ✅ Update booked slots
    $pdo->prepare("UPDATE available_dates SET {$booked_key} = {$booked_key} + 1 WHERE id = ?")
        ->execute([$available_date_id]);

    // ✅ Create notification for assigned personnel
    $slot_text = strtoupper($slot_period);
    $formatted_date = date('F d, Y', strtotime($base_date));
    $notification_message = "New appointment booked by {$resident_name} for {$formatted_date} ({$slot_text} slot)";

    $notificationStmt = $pdo->prepare("
        INSERT INTO notifications 
        (appointment_id, resident_id, message, created_at, is_read) 
        VALUES (?, ?, ?, NOW(), 0)
    ");
    
    $notificationStmt->execute([
        $appointmentId,
        $resident_id,
        $notification_message
    ]);

    // ✅ Commit transaction
    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'appointment_id' => $appointmentId,
        'transaction_id' => $transactionId,
        'message' => 'Appointment booked successfully!'
    ]);

} catch (Exception $e) {
    // ✅ Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Delete uploaded file if transaction failed
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    
    error_log("Appointment booking error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>