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
$userId = null;

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

// Get customer ID from request
$customerId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$customerName = '';
$customerEmail = '';

if ($customerId && $employeeId) {
    // Get customer info
    $custQuery = "SELECT u.email, c.name FROM users u 
                  JOIN customers c ON c.user_id = u.user_id 
                  WHERE u.user_id = ? LIMIT 1";
    $stmt = $conn->prepare($custQuery);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $custResult = $stmt->get_result();
    
    if ($custResult && $custResult->num_rows > 0) {
        $cust = $custResult->fetch_assoc();
        $customerName = $cust['name'];
        $customerEmail = $cust['email'];
    }
    
    // Mark messages as read
    $markReadSql = "UPDATE chat_messages SET is_read = 1 
                   WHERE user_id = ? AND employee_id = ? AND sender_type = 'user'";
    $stmt = $conn->prepare($markReadSql);
    $stmt->bind_param("ii", $customerId, $employeeId);
    $stmt->execute();
}

// Handle sending reply
$replyMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reply = trim($_POST['reply']);
    if (!empty($reply) && $customerId && $employeeId) {
        $insertSql = "INSERT INTO chat_messages (user_id, employee_id, sender_type, message, is_read, created_at) 
                     VALUES (?, ?, 'employee', ?, 1, NOW())";
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param("iis", $customerId, $employeeId, $reply);
        if ($stmt->execute()) {
            $replyMessage = 'Reply sent successfully!';
        }
    }
}

// Fetch all messages
$messages = [];
if ($customerId && $employeeId) {
    $sql = "SELECT * FROM chat_messages 
            WHERE user_id = ? AND employee_id = ? 
            ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $customerId, $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Chat with Customer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

        .back-btn {
            padding: 0.5rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .back-btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 1000px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1rem;
            overflow: hidden;
        }

        .chat-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .agent-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .agent-info h3 {
            font-size: 1.2rem;
            margin-bottom: 0.2rem;
        }

        .agent-info p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .chat-messages {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: white;
            border-left: 1px solid #e0e0e0;
            border-right: 1px solid #e0e0e0;
        }

        .message {
            display: flex;
            gap: 1rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.employee {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            font-size: 0.8rem;
        }

        .message.employee .message-avatar {
            background: linear-gradient(135deg, #27ae60 0%, #219150 100%);
        }

        .message-content {
            max-width: 70%;
        }

        .message-bubble {
            padding: 1rem 1.5rem;
            border-radius: 20px;
            background: #f8f9fa;
            color: #333;
            word-wrap: break-word;
        }

        .message.employee .message-bubble {
            background: linear-gradient(135deg, #27ae60 0%, #219150 100%);
            color: white;
        }

        .message-time {
            font-size: 0.75rem;
            color: #999;
            margin-top: 0.3rem;
            padding: 0 0.5rem;
        }

        .chat-input-area {
            padding: 1.5rem;
            background: white;
            border-left: 1px solid #e0e0e0;
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            border-radius: 0 0 15px 15px;
        }

        .input-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .chat-input {
            flex: 1;
            padding: 0.8rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1rem;
            resize: none;
            font-family: inherit;
            transition: border-color 0.3s ease;
            max-height: 100px;
        }

        .chat-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .send-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .send-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .empty-state {
            text-align: center;
            color: #999;
            padding: 2rem;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
            }

            .message-content {
                max-width: 85%;
            }

            .chat-messages {
                padding: 1rem;
            }

            .chat-input-area {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">SmartWash</div>
        <a href="messages.php" class="back-btn">← Back to Messages</a>
    </nav>

    <div class="chat-container">
        <div class="chat-header">
            <div class="agent-avatar">👤</div>
            <div class="agent-info">
                <h3><?php echo htmlspecialchars($customerName); ?></h3>
                <p><?php echo htmlspecialchars($customerEmail); ?></p>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">💬</div>
                    <p>No messages yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['sender_type']; ?>">
                        <div class="message-avatar">
                            <?php echo ($msg['sender_type'] === 'user') ? '👤' : '👨‍💼'; ?>
                        </div>
                        <div class="message-content">
                            <div class="message-bubble">
                                <?php echo htmlspecialchars($msg['message']); ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('M d, h:i A', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-input-area">
            <?php if ($replyMessage): ?>
                <div style="padding: 0.8rem; background: #d4edda; color: #155724; border-radius: 10px; margin-bottom: 1rem;">
                    ✅ <?php echo $replyMessage; ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="input-form">
                <textarea 
                    class="chat-input" 
                    name="reply" 
                    placeholder="Type your reply here..." 
                    rows="1"
                    required
                ></textarea>
                <button type="submit" class="send-btn">➤</button>
            </form>
        </div>
    </div>

    <script>
        // Auto-resize textarea
        const textarea = document.querySelector('.chat-input');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // Scroll to bottom on load
        window.addEventListener('load', () => {
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        });
    </script>
</body>
</html>
