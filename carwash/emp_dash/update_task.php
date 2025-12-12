<?php
session_start();

if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'employee') {
    header('Location: ../landing/login/login.php');
    exit;
}

include __DIR__ . '/../database/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = intval($_POST['booking_id']);
    $action = $_POST['action'];
    $email = $_SESSION['userEmail'];

    // Get Employee ID from email
    $sql = "SELECT e.employee_id 
            FROM employees e 
            JOIN users u ON e.user_id = u.user_id 
            WHERE u.email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $emp = $res->fetch_assoc();

    if (!$emp) {
        $_SESSION['error'] = "Employee record not found.";
        header('Location: index.php');
        exit;
    }

    $employeeId = $emp['employee_id'];

    if ($action === 'start') {
        // Assign to self and set In Progress
        $update = "UPDATE bookings SET status = 'In Progress', employee_id = ?, started_at = NOW() WHERE booking_id = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("ii", $employeeId, $bookingId);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Task started! Get to work.";
        } else {
            $_SESSION['error'] = "Error starting task: " . $conn->error;
        }

    } elseif ($action === 'complete') {
        // Mark Completed
        $update = "UPDATE bookings SET status = 'Completed', completed_at = NOW(), payment_status = 'Paid' WHERE booking_id = ? AND employee_id = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("ii", $bookingId, $employeeId);
        
        if ($stmt->execute()) {
            // Optional: Update employee stats here if not handled by triggers
            $_SESSION['success'] = "Task completed successfully!";
        } else {
            $_SESSION['error'] = "Error completing task: " . $conn->error;
        }
    }
}

header('Location: index.php');
exit;
?>