<?php
session_start();
include __DIR__ . '/../../database/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get user ID
$userId = $_SESSION['userId'] ?? null;
if (!$userId) {
    $userQuery = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("s", $_SESSION['userEmail']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userId = $user['user_id'];
    }
}

if (!$userId) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

$employeeId = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;

// Fetch messages for this user and employee
if ($employeeId) {
    $sql = "SELECT * FROM chat_messages 
            WHERE user_id = ? AND employee_id = ? 
            ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $employeeId);
} else {
    // Get all messages for this user
    $sql = "SELECT * FROM chat_messages 
            WHERE user_id = ? 
            ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();
$messages = [];

while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode(['success' => true, 'messages' => $messages]);
$conn->close();
?>
