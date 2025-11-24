<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'customer') {
    header('Location: ../landing/login/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use correct path
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
    
    $make = $conn->real_escape_string($_POST['make']);
    $model = $conn->real_escape_string($_POST['model']);
    $plateNumber = strtoupper($conn->real_escape_string($_POST['plate_number']));
    $vehicleType = $conn->real_escape_string($_POST['vehicle_type']);
    $color = $conn->real_escape_string($_POST['color']);
    
    // Check if plate number already exists
    $checkPlate = "SELECT vehicle_id FROM vehicles WHERE plate_number = ?";
    $checkStmt = $conn->prepare($checkPlate);
    $checkStmt->bind_param("s", $plateNumber);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $_SESSION['error'] = 'Vehicle with this plate number already exists!';
    } else {
        $insertQuery = "INSERT INTO vehicles (customer_id, make, model, plate_number, vehicle_type, color, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("isssss", $customerId, $make, $model, $plateNumber, $vehicleType, $color);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Vehicle added successfully!';
        } else {
            $_SESSION['error'] = 'Failed to add vehicle: ' . $conn->error;
        }
    }
    
    $conn->close();
}

header('Location: index.php');
exit;
?>