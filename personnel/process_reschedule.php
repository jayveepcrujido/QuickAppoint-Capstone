<?php
session_start();
include '../conn.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get department_id
$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$department_id = $stmt->fetchColumn();

if (!$department_id) {
    echo json_encode(['success' => false, 'message' => 'No department assigned']);
    exit();
}

// Validate input
$appointment_id = $_POST['appointment_id'] ?? null;
// Old values might be empty if the appointment wasn't scheduled yet
$old_date_id = !empty($_POST['old_date_id']) ? $_POST['old_date_id'] : null;
$old_time_slot = !empty($_POST['old_time_slot']) ? $_POST['old_time_slot'] : null;
$new_date_id = $_POST['new_date_id'] ?? null;
$new_time_slot = $_POST['new_time_slot'] ?? null;

if (!$appointment_id || !$new_date_id || !$new_time_slot) {
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields',
        'debug' => $_POST
    ]);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // ==================================================================================
    // 1. VERIFY APPOINTMENT (Universal Check)
    // We changed the status check to IN ('No Show', 'Pending') so it works for both files
    // ==================================================================================
    $checkStmt = $pdo->prepare("
        SELECT id, available_date_id, scheduled_for, status
        FROM appointments 
        WHERE id = ? 
        AND department_id = ? 
        AND status IN ('No Show', 'Pending')
    ");
    $checkStmt->execute([$appointment_id, $department_id]);
    $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        throw new Exception('Appointment not found, unauthorized, or status is not editable.');
    }
    
    // 2. Get the new date details
    $dateStmt = $pdo->prepare("
        SELECT 
            id,
            DATE(date_time) as date,
            am_slots, pm_slots,
            am_booked, pm_booked
        FROM available_dates
        WHERE id = ? AND department_id = ?
    ");
    $dateStmt->execute([$new_date_id, $department_id]);
    $newDate = $dateStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$newDate) {
        throw new Exception('Selected date not found.');
    }
    
    // 3. Check slot availability
    $isAM = ($new_time_slot === '09:00:00');
    $available = $isAM 
        ? ($newDate['am_slots'] - $newDate['am_booked']) > 0
        : ($newDate['pm_slots'] - $newDate['pm_booked']) > 0;
    
    if (!$available) {
        throw new Exception('Selected time slot is fully booked.');
    }
    
    // 4. Decrease booking count on OLD date (Only if it was previously scheduled)
    if ($old_date_id && $old_time_slot) {
        $oldIsAM = ($old_time_slot === '09:00:00');
        $oldColumn = $oldIsAM ? 'am_booked' : 'pm_booked';
        
        $decreaseStmt = $pdo->prepare("
            UPDATE available_dates 
            SET $oldColumn = GREATEST(0, $oldColumn - 1)
            WHERE id = ?
        ");
        $decreaseStmt->execute([$old_date_id]);
    }
    
    // 5. Increase booking count on NEW date
    $newColumn = $isAM ? 'am_booked' : 'pm_booked';
    $increaseStmt = $pdo->prepare("
        UPDATE available_dates 
        SET $newColumn = $newColumn + 1
        WHERE id = ?
    ");
    $increaseStmt->execute([$new_date_id]);
    
    // 6. Update the appointment
    // Note: We force status to 'Pending' regardless of whether it was 'No Show' before.
    $newScheduledFor = $newDate['date'] . ' ' . $new_time_slot;
    
    $updateStmt = $pdo->prepare("
        UPDATE appointments 
        SET available_date_id = ?,
            scheduled_for = ?,
            status = 'Pending',
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$new_date_id, $newScheduledFor, $appointment_id]);
    
    $pdo->commit();
    
    // Format the date nicely for response
    $formattedDate = date('F j, Y', strtotime($newDate['date']));
    $formattedTime = date('g:i A', strtotime($new_time_slot));
    
    echo json_encode([
        'success' => true,
        'message' => "Appointment successfully rescheduled to {$formattedDate} at {$formattedTime}",
        'new_date' => $newDate['date'],
        'new_time' => $new_time_slot
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error for debugging
    error_log("Reschedule Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>