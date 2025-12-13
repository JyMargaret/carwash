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

// Check if user is logged in and is employee
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'employee') {
    header('Location: ../landing/login/login.php');
    exit;
}

// Include database connection
include __DIR__ . '/../database/database.php';

$employeeEmail = $_SESSION['userEmail'];
$employeeName = $_SESSION['userName'] ?? 'Employee';
$employeeId = null;

// Get Employee ID
$userQuery = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $employeeEmail);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult && $userResult->num_rows > 0) {
    $user = $userResult->fetch_assoc();
    $userId = $user['user_id'];
    
    $empQuery = "SELECT employee_id, name FROM employees WHERE user_id = ? LIMIT 1";
    $empStmt = $conn->prepare($empQuery);
    $empStmt->bind_param("i", $userId);
    $empStmt->execute();
    $empResult = $empStmt->get_result();
    
    if ($empResult && $empResult->num_rows > 0) {
        $emp = $empResult->fetch_assoc();
        $employeeId = $emp['employee_id'];
        if (!empty($emp['name'])) {
            $employeeName = $emp['name'];
        }
    }
}

// Fetch all conversations for this employee
$conversations = [];
if ($employeeId) {
    $sql = "SELECT DISTINCT cm.user_id, u.email, c.name as customer_name,
            MAX(cm.created_at) as last_message_time,
            SUM(CASE WHEN cm.is_read = 0 AND cm.sender_type = 'user' THEN 1 ELSE 0 END) as unread_count
            FROM chat_messages cm
            JOIN users u ON cm.user_id = u.user_id
            JOIN customers c ON cm.user_id = c.user_id
            WHERE cm.employee_id = ?
            GROUP BY cm.user_id
            ORDER BY MAX(cm.created_at) DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
}

// Handle marking messages as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $userId = intval($_POST['user_id']);
    if ($userId && $employeeId) {
        $updateSql = "UPDATE chat_messages SET is_read = 1 
                     WHERE employee_id = ? AND user_id = ? AND sender_type = 'user'";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ii", $employeeId, $userId);
        $stmt->execute();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Employee Messages</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .navbar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .navbar-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .back-btn, .logout-btn {
            padding: 0.5rem 1.5rem;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-btn {
            background: #667eea;
        }

        .back-btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }

        .logout-btn {
            background: #ff4757;
        }

        .logout-btn:hover {
            background: #ff3838;
            transform: translateY(-2px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .messages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .conversation-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .conversation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .conversation-header {
            padding: 1.5rem;
            border-bottom: 2px solid #f0f0f0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .conversation-info h3 {
            font-size: 1.1rem;
            margin-bottom: 0.2rem;
        }

        .conversation-info p {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .unread-badge {
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .conversation-body {
            padding: 1.5rem;
        }

        .last-message-time {
            color: #999;
            font-size: 0.8rem;
            margin-bottom: 0.8rem;
        }

        .conversation-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            flex: 1;
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: inline-block;
            font-size: 0.85rem;
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .messages-grid {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">SmartWash</div>
        <div class="navbar-actions">
            <span style="font-weight: 500;">👤 <?php echo htmlspecialchars($employeeName); ?></span>
            <a href="index.php" class="back-btn">← Back to Dashboard</a>
            <a href="../landing/logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>📧 Customer Messages</h1>
            <p>View and respond to customer inquiries</p>
        </div>

        <?php if (empty($conversations)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">💬</div>
                <h3>No messages yet</h3>
                <p>You don't have any customer messages at the moment.</p>
            </div>
        <?php else: ?>
            <div class="messages-grid">
                <?php foreach ($conversations as $conv): ?>
                    <div class="conversation-card">
                        <div class="conversation-header">
                            <div class="conversation-info">
                                <h3><?php echo htmlspecialchars($conv['customer_name']); ?></h3>
                                <p><?php echo htmlspecialchars($conv['email']); ?></p>
                            </div>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <div class="unread-badge"><?php echo $conv['unread_count']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="conversation-body">
                            <div class="last-message-time">
                                Last message: <?php echo date('M d, Y h:i A', strtotime($conv['last_message_time'])); ?>
                            </div>
                            <div class="conversation-actions">
                                <a href="view_messages.php?user_id=<?php echo $conv['user_id']; ?>" class="btn btn-view">
                                    💬 View Chat
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
