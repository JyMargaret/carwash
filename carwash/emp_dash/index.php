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
$dbPath = __DIR__ . '/../database/database.php';
if (file_exists($dbPath)) {
    include $dbPath;
} else {
    die("Database configuration file not found.");
}

$employeeEmail = $_SESSION['userEmail'];
$employeeName = $_SESSION['userName'] ?? 'Employee';

// 1. Get Employee ID from Users -> Employees table
$employeeId = null;
$userQuery = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $employeeEmail);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult && $userResult->num_rows > 0) {
    $user = $userResult->fetch_assoc();
    $userId = $user['user_id'];
    
    // Fetch employee record
    // Note: Adjust column name 'name' or 'full_name' based on your schema
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

// 2. Fetch Tasks (Assigned to this employee OR Unassigned)
$tasks = [];
if ($employeeId) {
    // Check if 'assigned_staff_id' or 'employee_id' is the correct column name based on your schema.
    // Based on previous files, it seems to be 'assigned_staff_id' in bookings.
    // However, your snippet used 'employee_id'. I will use 'assigned_staff_id' as per admin files, 
    // but fallback to logic provided.
    
    $colCheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'assigned_staff_id'");
    $empCol = ($colCheck && $colCheck->num_rows > 0) ? 'assigned_staff_id' : 'employee_id';

    $sql = "SELECT b.*, 
            s.service_name, s.base_price as service_price,
            c.name as customer_name,
            v.make, v.model, v.plate_number
            FROM bookings b
            LEFT JOIN services s ON b.service_id = s.service_id
            LEFT JOIN customers c ON b.customer_id = c.customer_id
            LEFT JOIN vehicles v ON b.vehicle_id = v.vehicle_id
            WHERE 
            (
                (b.$empCol = ?) 
                OR 
                (b.$empCol IS NULL AND b.status IN ('Pending', 'Confirmed'))
            )
            AND b.status NOT IN ('Cancelled', 'Completed', 'No Show')
            ORDER BY 
                CASE WHEN b.status = 'In Progress' THEN 1 ELSE 2 END,
                b.booking_date ASC, 
                b.booking_time ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
}

// Calculate Stats for Today
$today = date('Y-m-d');
$stats = [
    'completed' => 0,
    'in_progress' => 0,
    'pending' => 0
];

if ($employeeId) {
    // Determine column name again for stats
    $colCheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'assigned_staff_id'");
    $empCol = ($colCheck && $colCheck->num_rows > 0) ? 'assigned_staff_id' : 'employee_id';

    // Completed Today
    $completedSql = "SELECT COUNT(*) as count FROM bookings WHERE $empCol = ? AND status = 'Completed' AND booking_date = ?";
    $stmt = $conn->prepare($completedSql);
    $stmt->bind_param("is", $employeeId, $today);
    $stmt->execute();
    $stats['completed'] = $stmt->get_result()->fetch_assoc()['count'];

    // In Progress
    $progSql = "SELECT COUNT(*) as count FROM bookings WHERE $empCol = ? AND status = 'In Progress'";
    $stmt = $conn->prepare($progSql);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $stats['in_progress'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Pending (Assigned)
    $pendSql = "SELECT COUNT(*) as count FROM bookings WHERE $empCol = ? AND status IN ('Pending', 'Confirmed')";
    $stmt = $conn->prepare($pendSql);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $stats['pending'] = $stmt->get_result()->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Employee Dashboard</title>
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

        .employee-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn {
            padding: 0.5rem 1.5rem;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.9rem;
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

        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.2rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }

        .task-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .task-item:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transform: translateX(5px);
        }

        .task-details h4 {
            font-size: 1.1rem;
            margin-bottom: 0.4rem;
            color: #333;
        }

        .task-meta {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .task-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .badge-date { background: #e3f2fd; color: #0d47a1; }
        
        .badge-status-in-progress { background: #e7f3ff; color: #0056b3; }
        .badge-status-pending { background: #fff3cd; color: #856404; }
        .badge-status-confirmed { background: #d1fae5; color: #065f46; }

        .badge-assigned { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge-unassigned { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .btn {
            padding: 0.6rem 1.5rem;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            color: white;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-start {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-complete {
            background: #27ae60;
        }

        .btn-complete:hover {
            background: #219150;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }

        .btn-messages {
            background: #3498db;
            padding: 1rem 2rem;
            font-size: 1rem;
            width: 100%;
            text-align: center;
            margin-top: 1rem;
        }

        .btn-messages:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .navbar { padding: 1rem; }
            .task-item { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .task-item form { width: 100%; }
            .btn { width: 100%; text-align: center; }
            .task-meta { flex-direction: column; gap: 0.3rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">SmartWash</div>
        <div class="employee-info">
            <span style="margin-right: 15px; font-weight: 500;">👤 <?php echo htmlspecialchars($employeeName); ?></span>
            <a href="../landing/logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message" style="background: #d4edda; color: #155724;">
                ✅ <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message" style="background: #f8d7da; color: #721c24;">
                ❌ <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="welcome-section">
            <h1>Employee Dashboard</h1>
            <p>Welcome back! You have <strong><?php echo count($tasks); ?></strong> active tasks available.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Assigned Pending</div>
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <a href="messages.php" class="btn btn-messages">📧 View Customer Messages</a>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Active Tasks List</h2>
            </div>
            
            <?php if (empty($tasks)): ?>
                <div style="text-align: center; padding: 3rem; color: #999;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                    <h3>No active tasks found</h3>
                    <p>Great job! There are no pending tasks at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <?php 
                        // Determine badges
                        $statusClass = 'badge-status-' . strtolower(str_replace(' ', '-', $task['status']));
                        $isAssignedToMe = (isset($task[$empCol]) && $task[$empCol] == $employeeId);
                    ?>
                    <div class="task-item">
                        <div class="task-details">
                            <div style="margin-bottom: 8px;">
                                <span class="badge badge-date">
                                    📅 <?php 
                                        $bDate = strtotime($task['booking_date']);
                                        echo ($bDate == strtotime($today)) ? 'TODAY' : date('M d', $bDate);
                                    ?> 
                                    @ <?php echo date('g:i A', strtotime($task['booking_time'])); ?>
                                </span>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($task['status']); ?>
                                </span>
                                <?php if($isAssignedToMe): ?>
                                    <span class="badge badge-assigned">👤 Assigned to You</span>
                                <?php else: ?>
                                    <span class="badge badge-unassigned">⚡ Unassigned</span>
                                <?php endif; ?>
                            </div>
                            
                            <h4><?php echo htmlspecialchars($task['service_name']); ?> - <?php echo htmlspecialchars($task['make'] . ' ' . $task['model']); ?></h4>
                            
                            <div class="task-meta">
                                <span>🚗 Plate: <?php echo htmlspecialchars($task['plate_number']); ?></span>
                                <span>👤 Customer: <?php echo htmlspecialchars($task['customer_name']); ?></span>
                                <span>📍 Bay: <?php echo htmlspecialchars($task['bay_number'] ?? 'Not set'); ?></span>
                            </div>
                        </div>
                        
                        <div class="task-actions">
                            <form action="update_task.php" method="POST">
                                <input type="hidden" name="booking_id" value="<?php echo $task['booking_id']; ?>">
                                
                                <?php if ($task['status'] == 'In Progress' && $isAssignedToMe): ?>
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-complete">✓ Complete Job</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="start">
                                    <button type="submit" class="btn btn-start">
                                        <?php echo $isAssignedToMe ? '▶ Continue Job' : '✋ Claim & Start'; ?>
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>