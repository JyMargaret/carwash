<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'customer') {
    header('Location: ../landing/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include __DIR__ . '/../../database/database.php';
    
    $userId = $_SESSION['userId'];
    $vehicleId = $_POST['vehicle_id'] ?? null;
    $serviceType = $_POST['service_type'] ?? null;
    $bookingDate = $_POST['booking_date'] ?? null;
    $bookingTime = $_POST['booking_time'] ?? null;
    
    // Validate inputs
    if (!$vehicleId || !$serviceType || !$bookingDate || !$bookingTime) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: index.php');
        exit;
    }
    
    // Map service types to prices
    $servicePrices = [
        'basic' => 250,
        'premium' => 450,
        'ultimate' => 750
    ];
    
    $price = $servicePrices[$serviceType] ?? 0;
    $status = 'Upcoming';
    
    // Try different column names for user_id
    $userIdColumn = 'user_id';
    $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'customer_id'");
    if ($checkColumn && $checkColumn->num_rows > 0) {
        $userIdColumn = 'customer_id';
    }
    
    // Insert booking
    $query = "INSERT INTO bookings ({$userIdColumn}, vehicle_id, service_type, booking_date, booking_time, status, price, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("iissssd", $userId, $vehicleId, $serviceType, $bookingDate, $bookingTime, $status, $price);
        
        if ($stmt->execute()) {
            // Add loyalty points (10 points per booking)
            $pointsQuery = "INSERT INTO loyalty ({$userIdColumn}, points, last_updated) 
                           VALUES (?, 10, NOW())
                           ON DUPLICATE KEY UPDATE 
                           points = points + 10, 
                           last_updated = NOW()";
            $pointsStmt = $conn->prepare($pointsQuery);
            if ($pointsStmt) {
                $pointsStmt->bind_param("i", $userId);
                $pointsStmt->execute();
                $pointsStmt->close();
            }
            
            $_SESSION['success'] = 'Booking created successfully!';
        } else {
            $_SESSION['error'] = 'Failed to create booking: ' . $stmt->error;
        }
        
        $stmt->close();
    } else {
        $_SESSION['error'] = 'Database error: ' . $conn->error;
    }
    
    $conn->close();
}

header('Location: index.php');
exit;
?>