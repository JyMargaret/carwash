<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'customer') {
    header('Location: ../landing/login/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use correct path - go up one level to carwash, then into database
    include __DIR__ . '/../database/database.php';
    
    $userId = $_SESSION['userId'];
    
    // Find customer_id
    $customerQuery = "SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1";
    $customerStmt = $conn->prepare($customerQuery);
    $customerStmt->bind_param("i", $userId);
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    $customer = $customerResult->fetch_assoc();
    
    if (!$customer) {
        // Create customer record if doesn't exist
        $insertCustomer = "INSERT INTO customers (user_id, loyalty_points, membership_tier, name) 
                          SELECT user_id, 0, 'Bronze', CONCAT(first_name, ' ', last_name) 
                          FROM users WHERE user_id = ?";
        $insertStmt = $conn->prepare($insertCustomer);
        $insertStmt->bind_param("i", $userId);
        $insertStmt->execute();
        $customerId = $conn->insert_id;
    } else {
        $customerId = $customer['customer_id'];
    }
    
    $vehicleId = intval($_POST['vehicle_id']);
    $serviceType = $conn->real_escape_string($_POST['service_type']);
    $bookingDate = $conn->real_escape_string($_POST['booking_date']);
    $bookingTime = $conn->real_escape_string($_POST['booking_time']);
    
    // Validate inputs
    if (!$vehicleId || !$serviceType || !$bookingDate || !$bookingTime) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: index.php');
        exit;
    }
    
    // Map service types to service IDs and get price
    $serviceMapping = [
        'basic' => 1,
        'basic_wash' => 1,
        'premium' => 2,
        'premium_wash' => 2,
        'ultimate' => 3,
        'ultimate_wash' => 3
    ];
    
    $serviceId = $serviceMapping[$serviceType] ?? 1;
    
    // Get service details
    $serviceQuery = "SELECT service_id, base_price FROM services WHERE service_id = ? LIMIT 1";
    $serviceStmt = $conn->prepare($serviceQuery);
    $serviceStmt->bind_param("i", $serviceId);
    $serviceStmt->execute();
    $serviceResult = $serviceStmt->get_result();
    $service = $serviceResult->fetch_assoc();
    
    if (!$service) {
        $_SESSION['error'] = 'Invalid service selected.';
        header('Location: index.php');
        exit;
    }
    
    $price = $service['base_price'];
    $status = 'Pending';
    
    // Check for available bay
    $bayQuery = "SELECT bay_number FROM bookings 
                 WHERE booking_date = ? AND booking_time = ? 
                 AND status NOT IN ('Cancelled', 'Completed')";
    $bayStmt = $conn->prepare($bayQuery);
    $bayStmt->bind_param("ss", $bookingDate, $bookingTime);
    $bayStmt->execute();
    $bayResult = $bayStmt->get_result();
    
    $usedBays = [];
    while ($row = $bayResult->fetch_assoc()) {
        $usedBays[] = $row['bay_number'];
    }
    
    // Find available bay
    $availableBay = null;
    for ($i = 1; $i <= 3; $i++) {
        $bayName = "Bay $i";
        if (!in_array($bayName, $usedBays)) {
            $availableBay = $bayName;
            break;
        }
    }
    
    if (!$availableBay) {
        $_SESSION['error'] = 'No available bays for this time slot. Please choose another time.';
        header('Location: index.php');
        exit;
    }
    
    // Insert booking
    $insertQuery = "INSERT INTO bookings (customer_id, vehicle_id, service_id, booking_date, booking_time, 
                    status, bay_number, total_amount, final_amount, payment_status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
    
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("iiissssdd", $customerId, $vehicleId, $serviceId, $bookingDate, $bookingTime, 
                      $status, $availableBay, $price, $price);
    
    if ($stmt->execute()) {
        // Award loyalty points (10 points per booking)
        $pointsQuery = "UPDATE customers SET loyalty_points = loyalty_points + 10 WHERE customer_id = ?";
        $pointsStmt = $conn->prepare($pointsQuery);
        $pointsStmt->bind_param("i", $customerId);
        $pointsStmt->execute();
        
        $_SESSION['success'] = 'Booking created successfully! Assigned to ' . $availableBay;
    } else {
        $_SESSION['error'] = 'Failed to create booking: ' . $stmt->error;
    }
    
    $stmt->close();
    $conn->close();
}

header('Location: index.php');
exit;
?>