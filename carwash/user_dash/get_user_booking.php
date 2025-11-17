<?php
// get_user_bookings.php - Fetch user's bookings with details

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
    
    // Fetch all bookings for the user
    $sql = "SELECT b.booking_id, b.booking_date, b.booking_time, b.status, b.bay_number,
                   b.total_amount, b.payment_status, b.notes,
                   s.service_name, s.duration_minutes,
                   v.make, v.model, v.plate_number,
                   st.full_name as staff_name
            FROM bookings b
            JOIN services s ON b.service_id = s.service_id
            JOIN vehicles v ON b.vehicle_id = v.vehicle_id
            LEFT JOIN staff st ON b.assigned_staff_id = st.staff_id
            WHERE b.user_id = ?
            ORDER BY b.booking_date DESC, b.booking_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    // Get statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_bookings,
                    SUM(CASE WHEN status IN ('Pending', 'Confirmed') THEN 1 ELSE 0 END) as upcoming_bookings,
                    SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0 END) as total_spent
                  FROM bookings 
                  WHERE user_id = ?";
    
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    
    // Get user info including loyalty points
    $user_sql = "SELECT full_name, loyalty_points, membership_tier FROM users WHERE user_id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_info = $user_stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'bookings' => $bookings,
        'stats' => $stats,
        'user_info' => $user_info
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