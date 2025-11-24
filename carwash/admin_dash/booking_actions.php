<?php
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 7,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'admin') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: ../landing/login/login.php');
    exit;
}

// Include database connection
$dbPath = __DIR__ . '/../database/database.php';
if (file_exists($dbPath)) {
    include $dbPath;
    if (!isset($conn) || !$conn || $conn->connect_error) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        die("Database connection failed.");
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'message' => 'Database configuration not found']);
        exit;
    }
    die("Database configuration file not found.");
}

// Handle POST requests for actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_booking') {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        
        if ($bookingId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
            exit;
        }
        
        // Delete the booking
        $deleteQuery = "DELETE FROM bookings WHERE booking_id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("i", $bookingId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete booking']);
        }
        
        $stmt->close();
        $conn->close();
        exit;
    }
    
    if ($action === 'update_status') {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        
        if ($bookingId <= 0 || empty($newStatus)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        // Update booking status
        $updateQuery = "UPDATE bookings SET status = ? WHERE booking_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("si", $newStatus, $bookingId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
        
        $stmt->close();
        $conn->close();
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// If GET request with edit parameter, redirect to bookings page
if (isset($_GET['edit'])) {
    header('Location: bookings.php?edit=' . intval($_GET['edit']));
    exit;
}

// Default redirect to dashboard
header('Location: index.php');
exit;
?>