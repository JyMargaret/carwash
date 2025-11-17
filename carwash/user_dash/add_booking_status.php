<?php
// update_booking_status.php - Accept, update, or delete bookings

require_once '../database/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if staff is logged in
if (!isStaffLoggedIn()) {
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
if (!isset($data['booking_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Get database connection
$conn = getDBConnection();

try {
    $booking_id = intval($data['booking_id']);
    $action = sanitizeInput($data['action']);
    $staff_id = $_SESSION['staff_id'];
    $user_type = $_SESSION['user_type'];
    
    // Start transaction
    $conn->begin_transaction();
    
    // Verify booking exists
    $verify_sql = "SELECT booking_id, status, user_id FROM bookings WHERE booking_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $booking_id);
    $verify_stmt->execute();
    $booking = $verify_stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    $response = ['success' => false];
    
    switch ($action) {
        case 'accept':
        case 'confirm':
            // Confirm booking and assign to staff (employee) or just confirm (admin)
            if ($user_type === 'employee') {
                $update_sql = "UPDATE bookings SET status = 'Confirmed', assigned_staff_id = ? 
                              WHERE booking_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $staff_id, $booking_id);
            } else {
                // Admin can assign to specific staff or leave unassigned
                $assigned_to = isset($data['assign_to']) ? intval($data['assign_to']) : null;
                if ($assigned_to) {
                    $update_sql = "UPDATE bookings SET status = 'Confirmed', assigned_staff_id = ? 
                                  WHERE booking_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ii", $assigned_to, $booking_id);
                } else {
                    $update_sql = "UPDATE bookings SET status = 'Confirmed' WHERE booking_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $booking_id);
                }
            }
            
            if ($update_stmt->execute()) {
                $response = ['success' => true, 'message' => 'Booking confirmed successfully'];
            }
            break;
            
        case 'start':
            // Start working on booking
            $update_sql = "UPDATE bookings SET status = 'In Progress', assigned_staff_id = ? 
                          WHERE booking_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $staff_id, $booking_id);
            
            if ($update_stmt->execute()) {
                $response = ['success' => true, 'message' => 'Booking started'];
            }
            break;
            
        case 'complete':
            // Complete booking
            if ($booking['status'] !== 'In Progress') {
                throw new Exception("Only in-progress bookings can be completed");
            }
            
            $update_sql = "UPDATE bookings SET status = 'Completed', payment_status = 'Paid' 
                          WHERE booking_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $booking_id);
            
            if ($update_stmt->execute()) {
                // Award loyalty points (10% of amount)
                $points_sql = "UPDATE users SET loyalty_points = loyalty_points + ? 
                              WHERE user_id = ?";
                $points_stmt = $conn->prepare($points_sql);
                $points = intval($booking['total_amount'] * 0.1);
                $points_stmt->bind_param("ii", $points, $booking['user_id']);
                $points_stmt->execute();
                
                $response = ['success' => true, 'message' => 'Booking completed successfully'];
            }
            break;
            
        case 'cancel':
        case 'delete':
            // Cancel/delete booking (only if not completed)
            if ($booking['status'] === 'Completed') {
                throw new Exception("Cannot cancel completed bookings");
            }
            
            if ($user_type === 'admin' && $action === 'delete') {
                // Admin can permanently delete
                $delete_sql = "DELETE FROM bookings WHERE booking_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $booking_id);
                
                if ($delete_stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Booking deleted successfully'];
                }
            } else {
                // Otherwise just cancel
                $update_sql = "UPDATE bookings SET status = 'Cancelled' WHERE booking_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $booking_id);
                
                if ($update_stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Booking cancelled successfully'];
                }
            }
            break;
            
        case 'reassign':
            // Admin can reassign booking to different staff
            if ($user_type !== 'admin') {
                throw new Exception("Only admins can reassign bookings");
            }
            
            $new_staff_id = isset($data['staff_id']) ? intval($data['staff_id']) : null;
            if (!$new_staff_id) {
                throw new Exception("Staff ID required for reassignment");
            }
            
            $update_sql = "UPDATE bookings SET assigned_staff_id = ? WHERE booking_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $new_staff_id, $booking_id);
            
            if ($update_stmt->execute()) {
                $response = ['success' => true, 'message' => 'Booking reassigned successfully'];
            }
            break;
            
        default:
            throw new Exception("Invalid action");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>