<?php
// add_vehicle.php - Add new vehicle for user

require_once '../database/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['make', 'model', 'plate', 'type', 'color'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

// Get database connection
$conn = getDBConnection();

try {
    // Get user ID from session
    $user_id = $_SESSION['user_id'];
    
    // Sanitize inputs
    $make = sanitizeInput($data['make']);
    $model = sanitizeInput($data['model']);
    $plate = strtoupper(sanitizeInput($data['plate'])); // Uppercase plate number
    $type = sanitizeInput($data['type']);
    $color = sanitizeInput($data['color']);
    
    // Check if plate number already exists
    $check_sql = "SELECT vehicle_id FROM vehicles WHERE plate_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $plate);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        throw new Exception("This plate number is already registered");
    }
    
    // Insert vehicle
    $insert_sql = "INSERT INTO vehicles (user_id, make, model, plate_number, vehicle_type, color) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("isssss", $user_id, $make, $model, $plate, $type, $color);
    
    if ($insert_stmt->execute()) {
        $vehicle_id = $conn->insert_id;
        
        // Get the newly added vehicle details
        $details_sql = "SELECT vehicle_id, make, model, plate_number, vehicle_type, color 
                        FROM vehicles WHERE vehicle_id = ?";
        $details_stmt = $conn->prepare($details_sql);
        $details_stmt->bind_param("i", $vehicle_id);
        $details_stmt->execute();
        $vehicle_details = $details_stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Vehicle added successfully',
            'vehicle' => $vehicle_details
        ]);
    } else {
        throw new Exception("Failed to add vehicle");
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>