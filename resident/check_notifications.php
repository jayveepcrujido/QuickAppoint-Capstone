<?php
session_start();

// --- IMPORTANT ---
// Using the PDO connection file
include '../conn.php'; 
// ---------------

// Check if user is logged in
if (!isset($_SESSION['auth_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$authId = $_SESSION['auth_id'];

// --- ADDED ---
// Close the session to prevent blocking other requests
session_write_close(); 
// --- END ---

$unreadCount = 0;
$residentId = null;

try {
    // Step 1: Get the resident_id from auth_id 
    // This is still needed to link auth to the appointments table
    $residentStmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ? LIMIT 1");
    $residentStmt->execute([$authId]);
    $residentData = $residentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$residentData) {
        // Can't find resident, so can't find notifications
        http_response_code(404);
        echo json_encode(['error' => 'Resident profile not found']);
        exit();
    }
    
    $residentId = $residentData['id'];

    // Step 2: Count unseen completed appointments from the 'appointments' table
    // This query is now changed to use 'is_seen_by_resident'
    $sql = "SELECT COUNT(*) as count 
            FROM appointments 
            WHERE resident_id = ? 
            AND status = 'Completed'
            AND is_seen_by_resident = 0"; // Check for unseen completed appointments
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$residentId]);
    
    // fetchColumn() is efficient for getting a single value
    $unreadCount = (int)$stmt->fetchColumn(); 

    // Send the count back as JSON
    header('Content-Type: application/json');
    echo json_encode(['unreadCount' => $unreadCount]);

} catch (PDOException $e) { // Catch PDOException
    // Handle database errors
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>


