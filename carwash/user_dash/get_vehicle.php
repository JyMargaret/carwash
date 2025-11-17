<?php
// get_vehicles.php - Fetch user's vehicles

require_once '../database/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get database connection
$conn = getDBConnection();

try {
    $user_id = $_SESSION['user_id'];
    
    // Fetch all vehicles for the user
    $sql = "SELECT vehicle_id, make, model, plate_number, vehicle_type, color, created_at
            FROM vehicles 
            WHERE user_id = ? 
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $vehicles = [];
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'vehicles' => $vehicles
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>