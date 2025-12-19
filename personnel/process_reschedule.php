<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appt_id = $_POST['appointment_id'];
    $old_date_id = $_POST['old_date_id'];
    $old_time_slot = $_POST['old_time_slot']; // '09:00:00' or '14:00:00'
    $new_date_id = $_POST['new_date_id'];
    $new_time_slot = $_POST['new_time_slot']; // '09:00:00' or '14:00:00'

    // Validate date format and combine with new time
    // We need to fetch the actual date string from the database using new_date_id
    $stmtDate = $pdo->prepare("SELECT date_time FROM available_dates WHERE id = ?");
    $stmtDate->execute([$new_date_id]);
    $dateRow = $stmtDate->fetch();
    
    if (!$dateRow) {
        echo json_encode(['success' => false, 'message' => 'Invalid date selected.']);
        exit();
    }

    // Create the new DATETIME string (YYYY-MM-DD HH:MM:SS)
    $baseDate = date('Y-m-d', strtotime($dateRow['date_time']));
    $newScheduledFor = $baseDate . ' ' . $new_time_slot;

    try {
        $pdo->beginTransaction();

        // 1. Decrement count on OLD date (if it exists)
        if ($old_date_id) {
            $isAmOld = (date('H', strtotime($old_time_slot)) < 12);
            $colToDec = $isAmOld ? 'am_booked' : 'pm_booked';
            
            $decStmt = $pdo->prepare("UPDATE available_dates SET $colToDec = GREATEST(0, $colToDec - 1) WHERE id = ?");
            $decStmt->execute([$old_date_id]);
        }

        // 2. Increment count on NEW date
        $isAmNew = (date('H', strtotime($new_time_slot)) < 12);
        $colToInc = $isAmNew ? 'am_booked' : 'pm_booked';
        $slotLimit = $isAmNew ? 'am_slots' : 'pm_slots';

        // Check availability one last time (race condition prevention)
        $checkStmt = $pdo->prepare("SELECT $colToInc, $slotLimit FROM available_dates WHERE id = ?");
        $checkStmt->execute([$new_date_id]);
        $availability = $checkStmt->fetch();

        if ($availability[$colToInc] >= $availability[$slotLimit]) {
            throw new Exception("The selected slot was just booked by someone else.");
        }

        $incStmt = $pdo->prepare("UPDATE available_dates SET $colToInc = $colToInc + 1 WHERE id = ?");
        $incStmt->execute([$new_date_id]);

        // 3. Update Appointment
        $updateAppt = $pdo->prepare("UPDATE appointments SET 
                                     scheduled_for = ?, 
                                     available_date_id = ?, 
                                     updated_at = NOW() 
                                     WHERE id = ?");
        $updateAppt->execute([$newScheduledFor, $new_date_id, $appt_id]);

        // 4. Send Notification to Resident (Optional but recommended)
        $resQuery = $pdo->prepare("SELECT resident_id FROM appointments WHERE id = ?");
        $resQuery->execute([$appt_id]);
        $resident_id = $resQuery->fetchColumn();
        
        $notifMsg = "Your appointment has been rescheduled to " . date('F j, Y g:i A', strtotime($newScheduledFor));
        $notifStmt = $pdo->prepare("INSERT INTO notifications (resident_id, appointment_id, message) VALUES (?, ?, ?)");
        $notifStmt->execute([$resident_id, $appt_id, $notifMsg]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>