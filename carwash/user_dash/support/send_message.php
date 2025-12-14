<?php
session_start();
include __DIR__ . '/../../database/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Handle both form data and JSON
$message = '';
$employeeId = null;

if (!empty($_POST)) {
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $employeeId = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $message = $data['message'] ?? '';
    $employeeId = $data['employee_id'] ?? null;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

// Get user ID
$userId = $_SESSION['userId'] ?? null;
if (!$userId) {
    // Try to get from session email
    $userQuery = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("s", $_SESSION['userEmail']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userId = $user['user_id'];
        $_SESSION['userId'] = $userId;
    }
}

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID not found']);
    exit;
}

// Check if chat_messages table exists, create if needed
$checkTableQuery = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages'";
$checkResult = $conn->query($checkTableQuery);

if (!$checkResult || $checkResult->num_rows === 0) {
    // Create chat_messages table
    $createTableSql = "CREATE TABLE IF NOT EXISTS chat_messages (
        message_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        employee_id INT NOT NULL,
        sender_type ENUM('user', 'employee') NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
        INDEX idx_user_employee (user_id, employee_id),
        INDEX idx_created_at (created_at)
    )";
    
    if (!$conn->query($createTableSql)) {
        echo json_encode(['success' => false, 'message' => 'Database error: Could not create messages table']);
        exit;
    }
}

// If no specific employee selected, get the first available employee (support agent)
if (!$employeeId) {
    $empQuery = "SELECT employee_id FROM employees LIMIT 1";
    $empStmt = $conn->prepare($empQuery);
    $empStmt->execute();
    $empResult = $empStmt->get_result();
    if ($empResult && $empResult->num_rows > 0) {
        $emp = $empResult->fetch_assoc();
        $employeeId = $emp['employee_id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'No employees available']);
        exit;
    }
}

// Insert message into chat_messages table
$insertSql = "INSERT INTO chat_messages (user_id, employee_id, sender_type, message, is_read, created_at) 
              VALUES (?, ?, 'user', ?, 0, NOW())";
$stmt = $conn->prepare($insertSql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iis", $userId, $employeeId, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
