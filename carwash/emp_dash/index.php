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
    if (!isset($conn) || !$conn || $conn->connect_error) {
        die("Database connection failed. Please check your database.php file.");
    }
} else {
    die("Database configuration file not found.");
}

// Get employee information
$employeeEmail = $_SESSION['userEmail'];
$employeeName = $_SESSION['userName'] ?? 'Employee';

// Get database name
$dbNameQuery = "SELECT DATABASE() AS dbname";
$dbNameResult = $conn->query($dbNameQuery);
$dbName = $dbNameResult ? $dbNameResult->fetch_assoc()['dbname'] : 'smartwash_db';

// Detect column names for services table
$serviceNameCol = 'service_name';
$servicePriceCol = 'price';
$serviceCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'services'");
if ($serviceCols && $serviceCols->num_rows > 0) {
    while ($col = $serviceCols->fetch_assoc()) {
        $colName = $col['COLUMN_NAME'];
        $lower = strtolower($colName);
        if (in_array($lower, ['name', 'service_name'])) {
            $serviceNameCol = $colName;
        }
        if (in_array($lower, ['price', 'base_price'])) {
            $servicePriceCol = $colName;
        }
    }
}

// Detect column names for customers table
$customerNameCol = 'name';
$customerCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'customers'");
if ($customerCols && $customerCols->num_rows > 0) {
    $cols = [];
    while ($col = $customerCols->fetch_assoc()) {
        $cols[] = $col['COLUMN_NAME'];
    }
    
    if (in_array('name', $cols)) {
        $customerNameCol = 'name';
    } elseif (in_array('full_name', $cols)) {
        $customerNameCol = 'full_name';
    } elseif (in_array('first_name', $cols) && in_array('last_name', $cols)) {
        $customerNameCol = "CONCAT(first_name, ' ', last_name)";
    }
}

// Detect bookings table primary key and employee foreign key
$bookingIdCol = 'booking_id';
$bookingEmployeeCol = 'employee_id';
$bookingServiceCol = 'service_id';
$bookingCustomerCol = 'customer_id';
$bookingVehicleCol = 'vehicle_id';

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
        if (strpos($lower, 'service') !== false && strpos($lower, 'id') !== false) {
            $bookingServiceCol = $colName;
        }
        if (strpos($lower, 'customer') !== false && strpos($lower, 'id') !== false) {
            $bookingCustomerCol = $colName;
        }
        if (strpos($lower, 'vehicle') !== false && strpos($lower, 'id') !== false) {
            $bookingVehicleCol = $colName;
        }
    }
}

// Get employee ID from users/employees table
$employeeId = null;
$userIdCol = 'user_id';
$employeeIdCol = 'employee_id';

$userQuery = "SELECT * FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $employeeEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $userIdValue = $user['user_id'] ?? $user['id'] ?? null;
    
    if ($userIdValue) {
        // Try to get employee_id from employees table
        $empQuery = "SELECT * FROM employees WHERE user_id = ? LIMIT 1";
        $empStmt = $conn->prepare($empQuery);
        $empStmt->bind_param("i", $userIdValue);
        $empStmt->execute();
        $empResult = $empStmt->get_result();
        
        if ($empResult && $empResult->num_rows > 0) {
            $emp = $empResult->fetch_assoc();
            $employeeId = $emp['employee_id'] ?? $emp['id'] ?? null;
        }
    }
}

// Fetch today's tasks/bookings assigned to this employee
$today = date('Y-m-d');

$tasksQuery = "SELECT b.*, 
               s.`$serviceNameCol` AS service_name,
               s.`$servicePriceCol` AS service_price,
               c.`$customerNameCol` AS customer_name
               FROM bookings b
               LEFT JOIN services s ON b.`$bookingServiceCol` = s.service_id
               LEFT JOIN customers c ON b.`$bookingCustomerCol` = c.customer_id
               WHERE b.booking_date = ?";

// Add employee filter if we have an employee ID
if ($employeeId) {
    $tasksQuery .= " AND (b.`$bookingEmployeeCol` = ? OR b.`$bookingEmployeeCol` IS NULL)";
}

$tasksQuery .= " ORDER BY b.booking_time ASC";

$stmt = $conn->prepare($tasksQuery);

if ($employeeId) {
    $stmt->bind_param("si", $today, $employeeId);
} else {
    $stmt->bind_param("s", $today);
}

$stmt->execute();
$tasksResult = $stmt->get_result();

$tasks = [];
$pendingCount = 0;
$inProgressCount = 0;
$completedToday = 0;

while ($row = $tasksResult->fetch_assoc()) {
    // Get vehicle info if vehicle_id exists
    if (isset($row[$bookingVehicleCol]) && $row[$bookingVehicleCol]) {
        $vehicleQuery = "SELECT * FROM vehicles WHERE vehicle_id = ? LIMIT 1";
        $vStmt = $conn->prepare($vehicleQuery);
        $vStmt->bind_param("i", $row[$bookingVehicleCol]);
        $vStmt->execute();
        $vResult = $vStmt->get_result();
        
        if ($vResult && $vResult->num_rows > 0) {
            $vehicle = $vResult->fetch_assoc();
            $row['vehicle_make'] = $vehicle['make'] ?? '';
            $row['vehicle_model'] = $vehicle['model'] ?? '';
            $row['plate_number'] = $vehicle['plate_number'] ?? '';
        }
    }
    
    $tasks[] = $row;
    
    $status = strtolower($row['status'] ?? '');
    if (in_array($status, ['pending', 'confirmed'])) {
        $pendingCount++;
    } elseif ($status == 'in progress' || $status == 'in_progress') {
        $inProgressCount++;
    } elseif ($status == 'completed') {
        $completedToday++;
    }
}

// Get employee statistics
$totalCompleted = 0;
$totalRevenue = 0;

if ($employeeId) {
    $statsQuery = "SELECT 
                   COUNT(*) as total_completed,
                   SUM(b.total_amount) as total_revenue
                   FROM bookings b
                   WHERE b.`$bookingEmployeeCol` = ? AND b.status = 'completed'";
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bind_param("i", $employeeId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    
    $totalCompleted = $stats['total_completed'] ?? 0;
    $totalRevenue = $stats['total_revenue'] ?? 0;
    
    // Get employee rating
    $ratingQuery = "SELECT AVG(rating) as avg_rating FROM reviews WHERE employee_id = ?";
    $ratingStmt = $conn->prepare($ratingQuery);
    $ratingStmt->bind_param("i", $employeeId);
    $ratingStmt->execute();
    $ratingResult = $ratingStmt->get_result();
    $rating = $ratingResult->fetch_assoc();
    $avgRating = round($rating['avg_rating'] ?? 4.5, 1);
} else {
    $avgRating = 4.5;
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
        }

        .logout-btn:hover {
            background: #ff3838;
            transform: translateY(-2px);
        }

        .employee-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #d4edda;
            color: #155724;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
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
            padding: 1.2rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .task-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .task-info h4 {
            font-size: 1rem;
            margin-bottom: 0.3rem;
            color: #333;
        }

        .task-info p {
            font-size: 0.85rem;
            color: #666;
        }

        .task-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-start, .btn-complete {
            padding: 0.6rem 1.5rem;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-start {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-complete {
            background: #28a745;
        }

        .btn-complete:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .no-tasks {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .no-tasks-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .welcome-content {
                flex-direction: column;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .task-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">SmartWash</div>
        <div class="employee-info">
            <div class="status-indicator">
                <span class="status-dot"></span>
                <span>On Duty</span>
            </div>
            <span>Employee: <?php echo htmlspecialchars($employeeName); ?></span>
            <div class="employee-avatar">👤</div>
            <a href="../landing/logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h1>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars($employeeName); ?>! 👋</h1>
                    <p>You have <?php echo $pendingCount + $inProgressCount; ?> tasks assigned today</p>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $completedToday; ?></div>
                <div class="stat-label">Completed Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔄</div>
                <div class="stat-value"><?php echo $inProgressCount; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏰</div>
                <div class="stat-value"><?php echo $pendingCount; ?></div>
                <div class="stat-label">Pending Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value"><?php echo $avgRating; ?></div>
                <div class="stat-label">Your Rating</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">My Tasks for Today</h2>
            </div>

            <?php if (empty($tasks)): ?>
                <div class="no-tasks">
                    <div class="no-tasks-icon">📋</div>
                    <h3>No Tasks Assigned Yet</h3>
                    <p>Check back later or contact your supervisor for task assignments.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-item">
                        <div class="task-info">
                            <h4><?php echo htmlspecialchars($task['service_name'] ?? 'Service'); ?> - <?php echo htmlspecialchars(($task['vehicle_make'] ?? '') . ' ' . ($task['vehicle_model'] ?? 'Vehicle')); ?></h4>
                            <p>
                                Customer: <?php echo htmlspecialchars($task['customer_name'] ?? 'N/A'); ?> • 
                                Time: <?php echo date('g:i A', strtotime($task['booking_time'])); ?> • 
                                Plate: <?php echo htmlspecialchars($task['plate_number'] ?? 'N/A'); ?>
                            </p>
                        </div>
                        <div class="task-actions">
                            <?php 
                            $status = strtolower($task['status'] ?? '');
                            if (in_array($status, ['pending', 'confirmed'])): 
                            ?>
                                <form method="POST" action="update_task.php" style="display: inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $task[$bookingIdCol]; ?>">
                                    <input type="hidden" name="action" value="start">
                                    <button type="submit" class="btn-start">Start</button>
                                </form>
                            <?php elseif ($status == 'in progress' || $status == 'in_progress'): ?>
                                <form method="POST" action="update_task.php" style="display: inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $task[$bookingIdCol]; ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn-complete">Complete</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #28a745; font-weight: 500;">✓ Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>