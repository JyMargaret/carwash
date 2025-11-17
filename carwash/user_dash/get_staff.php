<?php
// get_staff.php - Fetch staff members (for admin)

require_once '../database/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get database connection
$conn = getDBConnection();

try {
    // Fetch all active staff members
    $sql = "SELECT staff_id, full_name, email, phone, role, rating
            FROM staff 
            WHERE is_active = 1 AND role = 'employee'
            ORDER BY full_name ASC";
    
    $result = $conn->query($sql);
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        // Get today's assigned tasks count
        $today = date('Y-m-d');
        $count_sql = "SELECT COUNT(*) as task_count 
                      FROM bookings 
                      WHERE assigned_staff_id = ? 
                      AND booking_date = ? 
                      AND status NOT IN ('Completed', 'Cancelled')";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param("is", $row['staff_id'], $today);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result()->fetch_assoc();
        
        $row['current_tasks'] = $count_result['task_count'];
        $staff[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'staff' => $staff
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