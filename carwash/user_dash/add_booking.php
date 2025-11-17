<?php
// add_booking.php - Handle new booking creation from user dashboard

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
$required_fields = ['vehicle_id', 'service_id', 'booking_date', 'booking_time', 'total_amount'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

// Get database connection
$conn = getDBConnection();

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get user ID from session
    $user_id = $_SESSION['user_id'];
    
    // Sanitize inputs
    $vehicle_id = intval($data['vehicle_id']);
    $service_id = intval($data['service_id']);
    $booking_date = sanitizeInput($data['booking_date']);
    $booking_time = sanitizeInput($data['booking_time']);
    $total_amount = floatval($data['total_amount']);
    $notes = isset($data['notes']) ? sanitizeInput($data['notes']) : '';
    
    // Verify vehicle belongs to user
    $verify_sql = "SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND user_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $vehicle_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        throw new Exception("Invalid vehicle selection");
    }
    
    // Check if booking time slot is available
    $check_sql = "SELECT booking_id FROM bookings 
                  WHERE booking_date = ? AND booking_time = ? 
                  AND status NOT IN ('Cancelled', 'Completed')";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $booking_date, $booking_time);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows >= 3) { // Maximum 3 bookings per time slot
        throw new Exception("Time slot is fully booked. Please select another time.");
    }
    
    // Determine bay number based on availability
    $bay_numbers = ['Bay 1', 'Bay 2', 'Bay 3'];
    $used_bays_sql = "SELECT bay_number FROM bookings 
                      WHERE booking_date = ? AND booking_time = ? 
                      AND status NOT IN ('Cancelled', 'Completed')";
    $used_bays_stmt = $conn->prepare($used_bays_sql);
    $used_bays_stmt->bind_param("ss", $booking_date, $booking_time);
    $used_bays_stmt->execute();
    $used_bays_result = $used_bays_stmt->get_result();
    
    $used_bays = [];
    while ($row = $used_bays_result->fetch_assoc()) {
        $used_bays[] = $row['bay_number'];
    }
    
    $available_bay = null;
    foreach ($bay_numbers as $bay) {
        if (!in_array($bay, $used_bays)) {
            $available_bay = $bay;
            break;
        }
    }
    
    if (!$available_bay) {
        throw new Exception("No bays available for this time slot");
    }
    
    // Insert booking
    $insert_sql = "INSERT INTO bookings (user_id, vehicle_id, service_id, booking_date, booking_time, 
                   total_amount, bay_number, status, notes) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)";
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("iiissdss", $user_id, $vehicle_id, $service_id, $booking_date, 
                              $booking_time, $total_amount, $available_bay, $notes);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to create booking");
    }
    
    $booking_id = $conn->insert_id;
    
    // Get booking details for response
    $details_sql = "SELECT b.booking_id, b.booking_date, b.booking_time, b.bay_number, b.total_amount,
                           s.service_name, v.make, v.model, v.plate_number, u.full_name
                    FROM bookings b
                    JOIN services s ON b.service_id = s.service_id
                    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                    JOIN users u ON b.user_id = u.user_id
                    WHERE b.booking_id = ?";
    
    $details_stmt = $conn->prepare($details_sql);
    $details_stmt->bind_param("i", $booking_id);
    $details_stmt->execute();
    $booking_details = $details_stmt->get_result()->fetch_assoc();
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking' => $booking_details
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Close connection
    $conn->close();
}
?>