<?php
// get_bookings.php - Fetch bookings for admin/employee dashboard

require_once '../database/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if staff is logged in
if (!isStaffLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get database connection
$conn = getDBConnection();

try {
    $staff_id = $_SESSION['staff_id'];
    $user_type = $_SESSION['user_type'];
    
    // Get filter parameters
    $status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
    $date_filter = isset($_GET['date']) ? sanitizeInput($_GET['date']) : date('Y-m-d');
    
    // Build SQL query based on user type
    if ($user_type === 'employee') {
        // Employees see only their assigned tasks or unassigned tasks
        $sql = "SELECT b.booking_id, b.booking_date, b.booking_time, b.status, b.bay_number, 
                       b.total_amount, b.notes, b.assigned_staff_id,
                       s.service_name, s.duration_minutes,
                       v.make, v.model, v.plate_number, v.vehicle_type, v.color,
                       u.full_name as customer_name, u.phone as customer_phone,
                       st.full_name as assigned_staff_name
                FROM bookings b
                JOIN services s ON b.service_id = s.service_id
                JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                JOIN users u ON b.user_id = u.user_id
                LEFT JOIN staff st ON b.assigned_staff_id = st.staff_id
                WHERE b.booking_date = ? 
                AND (b.assigned_staff_id = ? OR b.assigned_staff_id IS NULL)";
        
        if ($status_filter !== 'all') {
            $sql .= " AND b.status = ?";
        }
        
        $sql .= " ORDER BY b.booking_time ASC";
        
        $stmt = $conn->prepare($sql);
        if ($status_filter !== 'all') {
            $stmt->bind_param("sis", $date_filter, $staff_id, $status_filter);
        } else {
            $stmt->bind_param("si", $date_filter, $staff_id);
        }
        
    } else {
        // Admins see all bookings
        $sql = "SELECT b.booking_id, b.booking_date, b.booking_time, b.status, b.bay_number, 
                       b.total_amount, b.notes, b.assigned_staff_id, b.payment_status,
                       s.service_name, s.duration_minutes,
                       v.make, v.model, v.plate_number, v.vehicle_type, v.color,
                       u.full_name as customer_name, u.phone as customer_phone, u.email as customer_email,
                       st.full_name as assigned_staff_name
                FROM bookings b
                JOIN services s ON b.service_id = s.service_id
                JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                JOIN users u ON b.user_id = u.user_id
                LEFT JOIN staff st ON b.assigned_staff_id = st.staff_id
                WHERE b.booking_date = ?";
        
        if ($status_filter !== 'all') {
            $sql .= " AND b.status = ?";
        }
        
        $sql .= " ORDER BY b.booking_time ASC";
        
        $stmt = $conn->prepare($sql);
        if ($status_filter !== 'all') {
            $stmt->bind_param("ss", $date_filter, $status_filter);
        } else {
            $stmt->bind_param("s", $date_filter);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    // Get summary statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0 END) as revenue
                  FROM bookings 
                  WHERE booking_date = ?";
    
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("s", $date_filter);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'bookings' => $bookings,
        'stats' => $stats,
        'date' => $date_filter
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