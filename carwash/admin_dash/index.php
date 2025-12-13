<?php
session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 7, 'path' => '/', 'domain' => '', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'admin') {
    header('Location: ../landing/login/login.php');
    exit;
}

include __DIR__ . '/../database/database.php';

$adminName = $_SESSION['userName'] ?? 'Admin';
$today = date('Y-m-d');

// --- 1. Statistics Query ---
$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN DATE(booking_date) = '$today' THEN 1 ELSE 0 END) as today_bookings,
    SUM(CASE WHEN status = 'Completed' AND DATE(booking_date) = '$today' THEN 1 ELSE 0 END) as completed_today,
    SUM(CASE WHEN status IN ('Pending', 'Confirmed') THEN 1 ELSE 0 END) as upcoming,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// --- 2. Revenue Query ---
$revenueQuery = "SELECT 
    SUM(CASE WHEN status = 'Completed' AND DATE(booking_date) = '$today' THEN final_amount ELSE 0 END) as today_revenue,
    SUM(CASE WHEN status = 'Completed' THEN final_amount ELSE 0 END) as total_revenue
    FROM bookings";
$revenueResult = $conn->query($revenueQuery);
$revenue = $revenueResult->fetch_assoc();

$customerCount = $conn->query("SELECT COUNT(*) as total_customers FROM customers")->fetch_assoc();

// --- 3. Recent Bookings ---
$bookingsQuery = "SELECT b.*, s.service_name, c.name as customer_name, v.make, v.model
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.service_id
    LEFT JOIN customers c ON b.customer_id = c.customer_id
    LEFT JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    ORDER BY b.booking_date DESC, b.booking_time DESC
    LIMIT 10";
$bookingsResult = $conn->query($bookingsQuery);
$bookings = [];
while ($row = $bookingsResult->fetch_assoc()) { $bookings[] = $row; }

// --- 4. Top Services ---
$serviceStatsQuery = "SELECT s.service_name,
    COUNT(b.booking_id) as booking_count,
    SUM(CASE WHEN b.status = 'Completed' THEN b.final_amount ELSE 0 END) as revenue
    FROM services s
    LEFT JOIN bookings b ON s.service_id = b.service_id
    GROUP BY s.service_id
    ORDER BY revenue DESC
    LIMIT 3";
$serviceStatsResult = $conn->query($serviceStatsQuery);
$serviceStats = [];
while ($row = $serviceStatsResult->fetch_assoc()) { $serviceStats[] = $row; }

// --- 5. Activity Feed Logic (Booked -> Started -> Completed) ---
$feed = [];
// Fetch last 20 bookings to generate activity
$feedQuery = "SELECT b.booking_id, b.status, b.booking_date, b.booking_time, b.started_at, b.completed_at, 
              c.name as customer_name, s.service_name, e.name as employee_name
              FROM bookings b
              LEFT JOIN customers c ON b.customer_id = c.customer_id
              LEFT JOIN services s ON b.service_id = s.service_id
              LEFT JOIN employees e ON b.employee_id = e.employee_id
              ORDER BY b.booking_date DESC LIMIT 20";
$feedRes = $conn->query($feedQuery);

if ($feedRes) {
    while($row = $feedRes->fetch_assoc()) {
        // Event 1: Booking Created (Using booking time as proxy)
        $bookTime = $row['booking_date'] . ' ' . $row['booking_time'];
        $feed[] = [
            'time' => strtotime($bookTime),
            'type' => 'booked',
            'message' => "<strong>{$row['customer_name']}</strong> booked <strong>{$row['service_name']}</strong>",
            'icon' => '📅',
            'color' => '#3b82f6', // Blue
            'subtext' => date('M d, h:i A', strtotime($bookTime))
        ];
        
        // Event 2: Started Cleaning
        if (!empty($row['started_at'])) {
            $emp = $row['employee_name'] ?: 'Staff';
            $feed[] = [
                'time' => strtotime($row['started_at']),
                'type' => 'started',
                'message' => "<strong>{$emp}</strong> started cleaning <strong>{$row['customer_name']}</strong>'s vehicle",
                'icon' => '🚿',
                'color' => '#f59e0b', // Orange
                'subtext' => date('M d, h:i A', strtotime($row['started_at']))
            ];
        }

        // Event 3: Job Completed
        if (!empty($row['completed_at'])) {
            $feed[] = [
                'time' => strtotime($row['completed_at']),
                'type' => 'completed',
                'message' => "Service completed for <strong>{$row['customer_name']}</strong>",
                'icon' => '✅',
                'color' => '#10b981', // Green
                'subtext' => date('M d, h:i A', strtotime($row['completed_at']))
            ];
        }
    }
}

// Sort feed by time descending (newest first)
usort($feed, function($a, $b) {
    return $b['time'] - $a['time'];
});

// Limit to top 8 items
$feed = array_slice($feed, 0, 8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem 0; position: fixed; height: 100vh; overflow-y: auto; z-index: 999; }
        .logo { font-size: 1.8rem; font-weight: bold; padding: 0 1.5rem; margin-bottom: 2rem; }
        .menu-item { padding: 1rem 1.5rem; display: block; color: white; text-decoration: none; border-left: 4px solid transparent; transition: 0.3s; }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.2); border-left-color: white; }
        .main-content { margin-left: 260px; flex: 1; padding: 2rem; width: calc(100% - 260px); }
        .header { background: white; padding: 1.5rem 2rem; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .admin-info { display: flex; align-items: center; gap: 1rem; }
        .admin-avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .stat-value { font-size: 2.5rem; font-weight: bold; color: #667eea; margin-bottom: 0.3rem; }
        .stat-label { color: #666; font-size: 0.9rem; }
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .card { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; }
        .card-title { font-size: 1.3rem; font-weight: 600; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; font-weight: 600; color: #666; font-size: 0.9rem; }
        .status-badge { padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; display: inline-block; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cce5ff; color: #004085; }
        .status-in-progress { background: #e7f3ff; color: #0056b3; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .service-stat-item { padding: 1rem; background: #f8f9fa; border-radius: 10px; margin-bottom: 1rem; }
        .service-stat-header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .service-name { font-weight: 600; color: #333; }
        .service-revenue { font-weight: bold; color: #667eea; }
        .btn-primary { padding: 0.6rem 1.5rem; background: #667eea; color: white; border: none; border-radius: 25px; text-decoration: none; display: inline-block; }
        
        /* Today's Status Styles */
        .today-status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .status-box { padding: 1rem; border-radius: 10px; text-align: center; }
        .bg-blue { background: #e3f2fd; color: #0d47a1; }
        .bg-green { background: #d1fae5; color: #065f46; }
        .bg-yellow { background: #fffbeb; color: #92400e; }
        .bg-red { background: #fee2e2; color: #991b1b; }
        .status-num { font-size: 1.5rem; font-weight: bold; margin-bottom: 0.2rem; }
        .status-txt { font-size: 0.85rem; font-weight: 500; }

        /* Activity Feed Styles */
        .activity-list { list-style: none; }
        .activity-item { display: flex; align-items: flex-start; margin-bottom: 1.5rem; position: relative; }
        .activity-item:last-child { margin-bottom: 0; }
        .activity-icon { 
            width: 40px; height: 40px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.2rem; color: white; margin-right: 1rem; flex-shrink: 0;
            z-index: 2;
        }
        .activity-line {
            position: absolute; left: 20px; top: 40px; bottom: -20px;
            width: 2px; background: #eee; z-index: 1;
        }
        .activity-item:last-child .activity-line { display: none; }
        .activity-content { flex: 1; }
        .activity-msg { font-size: 0.95rem; margin-bottom: 0.2rem; }
        .activity-time { font-size: 0.8rem; color: #999; }

        @media (max-width: 1024px) { .content-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <a href="index.php" class="menu-item active">Dashboard</a>
            <a href="bookings.php" class="menu-item">Bookings</a>
            <a href="customers.php" class="menu-item">Customers</a>
            <a href="services.php" class="menu-item">Services</a>
            <a href="staff.php" class="menu-item">Staff</a>
            <a href="reports.php" class="menu-item">Reports</a>
            <a href="settings.php" class="menu-item">Settings</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>Dashboard Overview</h1>
                <p style="color: #666; margin-top: 0.3rem;"><?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="admin-info">
                <strong><?php echo htmlspecialchars($adminName); ?></strong>
                <div class="admin-avatar">👤</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">₱<?php echo number_format($revenue['today_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['today_bookings']; ?></div>
                <div class="stat-label">Today's Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $customerCount['total_customers']; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['upcoming']; ?></div>
                <div class="stat-label">Upcoming Bookings</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Bookings</h2>
                    <a href="bookings.php" class="btn-primary">View All</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr><td colspan="4">No bookings found</td></tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($booking['service_name']); ?><br>
                                        <small style="color:#666"><?php echo htmlspecialchars($booking['make'] . ' ' . $booking['model']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                    <td><strong>₱<?php echo number_format($booking['final_amount'] ?? 0, 2); ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $booking['status'])); ?>">
                                            <?php echo htmlspecialchars($booking['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">Today's Status</h2>
                    </div>
                    <div class="today-status-grid">
                        <div class="status-box bg-green">
                            <div class="status-num"><?php echo $stats['completed_today']; ?></div>
                            <div class="status-txt">Completed</div>
                        </div>
                        <div class="status-box bg-blue">
                            <div class="status-num"><?php echo $stats['upcoming']; ?></div>
                            <div class="status-txt">Upcoming</div>
                        </div>
                        <div class="status-box bg-yellow">
                            <div class="status-num"><?php echo $stats['in_progress']; ?></div>
                            <div class="status-txt">In Progress</div>
                        </div>
                        <div class="status-box bg-red">
                            <div class="status-num"><?php echo $stats['cancelled']; ?></div>
                            <div class="status-txt">Cancelled</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Top Performing Services</h2>
                    </div>
                    <?php if (empty($serviceStats)): ?>
                        <p style="color:#999; text-align:center;">No data available</p>
                    <?php else: ?>
                        <?php foreach ($serviceStats as $service): ?>
                            <div class="service-stat-item">
                                <div class="service-stat-header">
                                    <span class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                    <span class="service-revenue">₱<?php echo number_format($service['revenue'], 2); ?></span>
                                </div>
                                <div style="font-size:0.85rem; color:#666;">
                                    <?php echo $service['booking_count']; ?> bookings
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent System Activity</h2>
            </div>
            <ul class="activity-list">
                <?php if (empty($feed)): ?>
                    <li style="text-align:center; color:#999;">No recent activity found.</li>
                <?php else: ?>
                    <?php foreach ($feed as $item): ?>
                        <li class="activity-item">
                            <div class="activity-line"></div>
                            <div class="activity-icon" style="background-color: <?php echo $item['color']; ?>">
                                <?php echo $item['icon']; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-msg"><?php echo $item['message']; ?></div>
                                <div class="activity-time"><?php echo $item['subtext']; ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

    </main>
</body>
</html>
<?php $conn->close(); ?>