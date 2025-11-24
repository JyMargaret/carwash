<?php
session_start();

// Check if user is logged in and is employee
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'employee') {
    header('Location: ../landing/login/login.php');
    exit;
}

// Include database connection
$dbPath = __DIR__ . '/../database/database.php';
if (file_exists($dbPath)) {
    include $dbPath;
    if (!isset($conn) || !$conn || $conn->connect_error) {
        die("Database connection failed.");
    }
} else {
    die("Database configuration file not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($bookingId > 0 && in_array($action, ['start', 'complete'])) {
        // Get database name
        $dbNameQuery = "SELECT DATABASE() AS dbname";
        $dbNameResult = $conn->query($dbNameQuery);
        $dbName = $dbNameResult ? $dbNameResult->fetch_assoc()['dbname'] : 'smartwash_db';
        
        // Detect bookings table columns
        $bookingIdCol = 'booking_id';
        $bookingEmployeeCol = 'employee_id';
        
        $bookingCols = $conn->query("SELECT COLUMN_NAME, COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'bookings'");
        if ($bookingCols && $bookingCols->num_rows > 0) {
            while ($col = $bookingCols->fetch_assoc()) {
                $colName = $col['COLUMN_NAME'];
                $lower = strtolower($colName);
                
                if ($col['COLUMN_KEY'] === 'PRI') {
                    $bookingIdCol = $colName;
                }
                if (strpos($lower, 'employee') !== false || strpos($lower, 'staff') !== false || strpos($lower, 'assigned') !== false) {
                    $bookingEmployeeCol = $colName;
                }
            }
        }
        
        // Get employee ID
        $employeeEmail = $_SESSION['userEmail'];
        $employeeId = null;
        
        $userQuery = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($userQuery);
        $stmt->bind_param("s", $employeeEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userId = $user['user_id'] ?? $user['id'] ?? null;
            
            if ($userId) {
                $empQuery = "SELECT * FROM employees WHERE user_id = ? LIMIT 1";
                $empStmt = $conn->prepare($empQuery);
                $empStmt->bind_param("i", $userId);
                $empStmt->execute();
                $empResult = $empStmt->get_result();
                
                if ($empResult && $empResult->num_rows > 0) {
                    $emp = $empResult->fetch_assoc();
                    $employeeId = $emp['employee_id'] ?? $emp['id'] ?? null;
                }
            }
        }
        
        if ($action === 'start') {
            // Update booking status to in_progress and assign employee
            if ($employeeId) {
                $updateQuery = "UPDATE bookings SET status = 'In Progress', `$bookingEmployeeCol` = ? WHERE `$bookingIdCol` = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ii", $employeeId, $bookingId);
            } else {
                $updateQuery = "UPDATE bookings SET status = 'In Progress' WHERE `$bookingIdCol` = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("i", $bookingId);
            }
            
            if ($updateStmt->execute()) {
                $_SESSION['success'] = 'Task started successfully!';
            } else {
                $_SESSION['error'] = 'Failed to start task: ' . $updateStmt->error;
            }
        } elseif ($action === 'complete') {
            // Update booking status to completed
            if ($employeeId) {
                $updateQuery = "UPDATE bookings SET status = 'Completed' WHERE `$bookingIdCol` = ? AND `$bookingEmployeeCol` = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ii", $bookingId, $employeeId);
            } else {
                $updateQuery = "UPDATE bookings SET status = 'Completed' WHERE `$bookingIdCol` = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("i", $bookingId);
            }
            
            if ($updateStmt->execute()) {
                $_SESSION['success'] = 'Task completed successfully!';
            } else {
                $_SESSION['error'] = 'Failed to complete task: ' . $updateStmt->error;
            }
        }
    } else {
        $_SESSION['error'] = 'Invalid request.';
    }
}

$conn->close();
header('Location: index.php');
exit;
?>