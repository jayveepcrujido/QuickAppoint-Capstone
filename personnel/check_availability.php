<?php
session_start();
include '../conn.php';

// Only allow LGU Personnel
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Get Department ID of the logged-in user
$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$department_id = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date'])) {
    $selectedDate = $_POST['date'];

    // Query available_dates table
    $query = "SELECT id, am_slots, pm_slots, am_booked, pm_booked 
              FROM available_dates 
              WHERE department_id = ? AND DATE(date_time) = ? AND status = 'available'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$department_id, $selectedDate]);
    $dateData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dateData) {
        $am_open = ($dateData['am_slots'] > $dateData['am_booked']);
        $pm_open = ($dateData['pm_slots'] > $dateData['pm_booked']);

        if (!$am_open && !$pm_open) {
            echo json_encode(['status' => 'error', 'message' => 'All slots for this date are fully booked.']);
        } else {
            echo json_encode([
                'status' => 'available',
                'date_id' => $dateData['id'],
                'am_open' => $am_open,
                'pm_open' => $pm_open
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No schedule set by Admin for this date.']);
    }
}
?>