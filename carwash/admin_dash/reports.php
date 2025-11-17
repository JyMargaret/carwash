<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'admin') {
    header('Location: ../landing/login/login.php');
    exit;
}

// Include database connection
include __DIR__ . '/../database/database.php';

// Get date range from query params or use defaults
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// Revenue Statistics - Using price from bookings table
$revenueQuery = "SELECT 
                SUM(CASE WHEN b.status = 'completed' THEN b.price ELSE 0 END) as total_revenue,
                SUM(CASE WHEN b.status = 'completed' AND DATE(b.booking_date) = CURDATE() THEN b.price ELSE 0 END) as today_revenue,
                SUM(CASE WHEN b.status = 'completed' AND WEEK(b.booking_date) = WEEK(CURDATE()) THEN b.price ELSE 0 END) as week_revenue,
                SUM(CASE WHEN b.status = 'completed' AND MONTH(b.booking_date) = MONTH(CURDATE()) THEN b.price ELSE 0 END) as month_revenue,
                COUNT(CASE WHEN b.status = 'completed' THEN 1 END) as completed_bookings,
                COUNT(CASE WHEN b.status = 'pending' THEN 1 END) as pending_bookings,
                COUNT(CASE WHEN b.status = 'cancelled' THEN 1 END) as cancelled_bookings
                FROM bookings b
                WHERE b.booking_date BETWEEN '$startDate' AND '$endDate'";
$revenueResult = $conn->query($revenueQuery);
$revenueStats = $revenueResult->fetch_assoc();

// Top Services - Using price from bookings table
$topServicesQuery = "SELECT s.name,
                     COUNT(b.id) as booking_count,
                     SUM(CASE WHEN b.status = 'completed' THEN b.price ELSE 0 END) as revenue,
                     AVG(CASE WHEN b.status = 'completed' THEN b.price ELSE NULL END) as avg_price
                     FROM services s
                     LEFT JOIN bookings b ON s.id = b.service_id AND b.booking_date BETWEEN '$startDate' AND '$endDate'
                     GROUP BY s.id
                     ORDER BY booking_count DESC
                     LIMIT 5";
$topServicesResult = $conn->query($topServicesQuery);

// Top Customers - Using price from bookings table
$topCustomersQuery = "SELECT c.name, c.email,
                      COUNT(b.id) as booking_count,
                      SUM(CASE WHEN b.status = 'completed' THEN b.price ELSE 0 END) as total_spent
                      FROM customers c
                      LEFT JOIN bookings b ON c.id = b.customer_id AND b.booking_date BETWEEN '$startDate' AND '$endDate'
                      GROUP BY c.id
                      HAVING booking_count > 0
                      ORDER BY total_spent DESC
                      LIMIT 10";
$topCustomersResult = $conn->query($topCustomersQuery);

// Daily Revenue (Last 30 days) - Using price from bookings table
$dailyRevenueQuery = "SELECT DATE(b.booking_date) as date,
                      SUM(CASE WHEN b.status = 'completed' THEN b.price ELSE 0 END) as revenue,
                      COUNT(CASE WHEN b.status = 'completed' THEN 1 END) as bookings
                      FROM bookings b
                      WHERE b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      GROUP BY DATE(b.booking_date)
                      ORDER BY date ASC";
$dailyRevenueResult = $conn->query($dailyRevenueQuery);

// Monthly comparison - Using price from bookings table
$monthlyQuery = "SELECT 
                DATE_FORMAT(b.booking_date, '%Y-%m') as month,
                SUM(CASE WHEN b.status = 'completed' THEN b.price ELSE 0 END) as revenue,
                COUNT(CASE WHEN b.status = 'completed' THEN 1 END) as bookings
                FROM bookings b
                WHERE b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY month
                ORDER BY month ASC";
$monthlyResult = $conn->query($monthlyQuery);

// Booking status distribution
$statusQuery = "SELECT status, COUNT(*) as count
                FROM bookings
                WHERE booking_date BETWEEN '$startDate' AND '$endDate'
                GROUP BY status";
$statusResult = $conn->query($statusQuery);

// Peak hours analysis
$peakHoursQuery = "SELECT HOUR(booking_time) as hour, COUNT(*) as bookings
                   FROM bookings
                   WHERE booking_date BETWEEN '$startDate' AND '$endDate'
                   GROUP BY hour
                   ORDER BY hour ASC";
$peakHoursResult = $conn->query($peakHoursQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Reports & Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 999;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            padding: 0 1.5rem;
            margin-bottom: 2rem;
        }

        .menu-item {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            text-decoration: none;
            color: white;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left-color: white;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 2rem;
            width: calc(100% - 260px);
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 1.8rem;
            color: #333;
        }

        .filter-section {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 500;
            color: #666;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-primary {
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
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

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 2rem;
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

        .chart-container {
            position: relative;
            height: 350px;
            margin: 1rem 0;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-export {
            padding: 0.5rem 1rem;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .btn-export:hover {
            background: #667eea;
            color: white;
        }

        .metric-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .metric-name {
            font-weight: 500;
            color: #333;
        }

        .metric-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                display: block;
            }

            .sidebar,
            .filter-section,
            .export-buttons,
            .btn-export,
            .btn-primary {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0;
            }

            .header {
                box-shadow: none;
                border-bottom: 2px solid #333;
                page-break-after: avoid;
            }

            .stat-card,
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }

            .chart-container {
                page-break-inside: avoid;
            }

            .content-grid {
                display: block;
            }

            .card {
                margin-bottom: 1rem;
            }

            table {
                font-size: 0.9rem;
            }

            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 2rem;
                padding-bottom: 1rem;
                border-bottom: 3px solid #667eea;
            }

            .print-date {
                display: block !important;
                text-align: right;
                font-size: 0.9rem;
                color: #666;
                margin-bottom: 1rem;
            }
        }

        .print-header {
            display: none;
        }

        .print-date {
            display: none;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <div class="menu-item" onclick="window.location.href='index.php'">
                <span>Dashboard</span>
            </div>
            <div class="menu-item" onclick="window.location.href='bookings.php'">
                <span>Bookings</span>
            </div>
            <div class="menu-item" onclick="window.location.href='customers.php'">
                <span>Customers</span>
            </div>
            <div class="menu-item" onclick="window.location.href='services.php'">
                <span>Services</span>
            </div>
            <div class="menu-item" onclick="window.location.href='staff.php'">
                <span>Staff</span>
            </div>
            <div class="menu-item active" onclick="window.location.href='reports.php'">
                <span>Reports</span>
            </div>
            <div class="menu-item" onclick="window.location.href='settings.php'">
                <span>Settings</span>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="print-header">
            <h1>SmartWash Business Reports</h1>
            <p>Comprehensive Analytics & Performance Metrics</p>
        </div>

        <div class="print-date">
            Report Generated: <?php echo date('F d, Y g:i A'); ?><br>
            Period: <?php echo date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)); ?>
        </div>

        <div class="header">
            <div>
                <h1>Reports & Analytics</h1>
                <p style="color: #666; margin-top: 0.3rem;">Comprehensive business insights and performance metrics</p>
            </div>
            <div class="export-buttons">
                <button class="btn-export" onclick="printReport()">🖨️ Print Report</button>
                <button class="btn-export" onclick="exportAllData()">📄 Export All Data</button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="report_type">Report Type</label>
                        <select name="report_type" id="report_type">
                            <option value="overview" <?php echo $reportType === 'overview' ? 'selected' : ''; ?>>Overview</option>
                            <option value="revenue" <?php echo $reportType === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                            <option value="services" <?php echo $reportType === 'services' ? 'selected' : ''; ?>>Services</option>
                            <option value="customers" <?php echo $reportType === 'customers' ? 'selected' : ''; ?>>Customers</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn-primary">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Revenue Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₱<?php echo number_format($revenueStats['total_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Revenue (Period)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value">₱<?php echo number_format($revenueStats['today_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value">₱<?php echo number_format($revenueStats['week_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-value">₱<?php echo number_format($revenueStats['month_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">This Month</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $revenueStats['completed_bookings'] ?? 0; ?></div>
                <div class="stat-label">Completed Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?php echo $revenueStats['pending_bookings'] ?? 0; ?></div>
                <div class="stat-label">Pending Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-value"><?php echo $revenueStats['cancelled_bookings'] ?? 0; ?></div>
                <div class="stat-label">Cancelled Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💵</div>
                <div class="stat-value">₱<?php 
                    $avgRevenue = $revenueStats['completed_bookings'] > 0 
                        ? $revenueStats['total_revenue'] / $revenueStats['completed_bookings'] 
                        : 0;
                    echo number_format($avgRevenue, 2); 
                ?></div>
                <div class="stat-label">Average Transaction</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Daily Revenue Trend (Last 30 Days)</h2>
                    <div class="export-buttons">
                        <button class="btn-export" onclick="exportChart('dailyChart')">📊 Export</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Booking Status Distribution</h2>
                </div>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Monthly Comparison -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Monthly Performance (Last 6 Months)</h2>
            </div>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <!-- Tables Section -->
        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Top Services</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Bookings</th>
                                <th>Revenue</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $maxBookings = 1;
                            $services = [];
                            if ($topServicesResult && $topServicesResult->num_rows > 0) {
                                while ($service = $topServicesResult->fetch_assoc()) {
                                    $services[] = $service;
                                    if ($service['booking_count'] > $maxBookings) {
                                        $maxBookings = $service['booking_count'];
                                    }
                                }
                                
                                foreach ($services as $service):
                                    $percentage = ($service['booking_count'] / $maxBookings) * 100;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($service['name']); ?></strong></td>
                                <td><?php echo $service['booking_count']; ?></td>
                                <td><strong style="color: #667eea;">₱<?php echo number_format($service['revenue'], 2); ?></strong></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endforeach;
                            } else {
                                echo '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #999;">No data available</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Peak Hours</h2>
                </div>
                <?php 
                if ($peakHoursResult && $peakHoursResult->num_rows > 0):
                    while ($hour = $peakHoursResult->fetch_assoc()):
                        $timeLabel = date('g:i A', strtotime($hour['hour'] . ':00'));
                ?>
                <div class="metric-item">
                    <div class="metric-header">
                        <span class="metric-name"><?php echo $timeLabel; ?></span>
                        <span class="metric-value"><?php echo $hour['bookings']; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(($hour['bookings'] / 10) * 100, 100); ?>%"></div>
                    </div>
                </div>
                <?php 
                    endwhile;
                else:
                    echo '<p style="text-align: center; padding: 2rem; color: #999;">No data available</p>';
                endif;
                ?>
            </div>
        </div>

        <!-- Top Customers -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Top Customers</h2>
                <div class="export-buttons">
                    <button class="btn-export" onclick="exportCustomersToCSV()">📄 Export CSV</button>
                </div>
            </div>
            <div class="table-container">
                <table id="customersTable">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Total Bookings</th>
                            <th>Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($topCustomersResult && $topCustomersResult->num_rows > 0):
                            $rank = 1;
                            while ($customer = $topCustomersResult->fetch_assoc()):
                        ?>
                        <tr>
                            <td><strong><?php echo $rank++; ?></strong></td>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo $customer['booking_count']; ?></td>
                            <td><strong style="color: #667eea;">₱<?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                            echo '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #999;">No data available</td></tr>';
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Daily Revenue Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = {
            labels: [
                <?php 
                mysqli_data_seek($dailyRevenueResult, 0);
                while ($day = $dailyRevenueResult->fetch_assoc()) {
                    echo "'" . date('M d', strtotime($day['date'])) . "',";
                }
                ?>
            ],
            datasets: [{
                label: 'Revenue (₱)',
                data: [
                    <?php 
                    mysqli_data_seek($dailyRevenueResult, 0);
                    while ($day = $dailyRevenueResult->fetch_assoc()) {
                        echo $day['revenue'] . ',';
                    }
                    ?>
                ],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        };

        new Chart(dailyCtx, {
            type: 'line',
            data: dailyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = {
            labels: [
                <?php 
                mysqli_data_seek($statusResult, 0);
                while ($status = $statusResult->fetch_assoc()) {
                    echo "'" . ucfirst($status['status']) . "',";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    mysqli_data_seek($statusResult, 0);
                    while ($status = $statusResult->fetch_assoc()) {
                        echo $status['count'] . ',';
                    }
                    ?>
                ],
                backgroundColor: [
                    '#27ae60',
                    '#f39c12',
                    '#3498db',
                    '#e74c3c'
                ]
            }]
        };

        new Chart(statusCtx, {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = {
            labels: [
                <?php 
                mysqli_data_seek($monthlyResult, 0);
                while ($month = $monthlyResult->fetch_assoc()) {
                    echo "'" . date('M Y', strtotime($month['month'] . '-01')) . "',";
                }
                ?>
            ],
            datasets: [
                {
                    label: 'Revenue (₱)',
                    data: [
                        <?php 
                        mysqli_data_seek($monthlyResult, 0);
                        while ($month = $monthlyResult->fetch_assoc()) {
                            echo $month['revenue'] . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 2
                },
                {
                    label: 'Bookings',
                    data: [
                        <?php 
                        mysqli_data_seek($monthlyResult, 0);
                        while ($month = $monthlyResult->fetch_assoc()) {
                            echo $month['bookings'] . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(118, 75, 162, 0.8)',
                    borderColor: '#764ba2',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }
            ]
        };

        new Chart(monthlyCtx, {
            type: 'bar',
            data: monthlyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Print Report Function
        function printReport() {
            window.print();
        }

        // Export Chart Function
        function exportChart(chartId) {
            const canvas = document.getElementById(chartId);
            const url = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = chartId + '_' + new Date().getTime() + '.png';
            link.href = url;
            link.click();
        }

        // Export Customers to CSV
        function exportCustomersToCSV() {
            let csv = 'Rank,Customer Name,Email,Total Bookings,Total Spent\n';
            
            const table = document.getElementById('customersTable');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td');
                if (cols.length > 0) {
                    const rowData = [];
                    cols.forEach(col => {
                        let text = col.textContent.trim().replace(/"/g, '""');
                        // Remove currency symbol and format
                        text = text.replace('₱', '').replace(/,/g, '');
                        rowData.push('"' + text + '"');
                    });
                    csv += rowData.join(',') + '\n';
                }
            });
            
            downloadCSV(csv, 'top_customers_' + new Date().getTime() + '.csv');
        }

        // Export All Data Function
        function exportAllData() {
            let csv = 'SmartWash Business Report\n';
            csv += 'Generated: ' + new Date().toLocaleString() + '\n';
            csv += 'Period: <?php echo date("M d, Y", strtotime($startDate)) . " - " . date("M d, Y", strtotime($endDate)); ?>\n\n';
            
            // Revenue Statistics
            csv += 'REVENUE STATISTICS\n';
            csv += 'Metric,Value\n';
            csv += 'Total Revenue,₱<?php echo number_format($revenueStats["total_revenue"] ?? 0, 2); ?>\n';
            csv += 'Today Revenue,₱<?php echo number_format($revenueStats["today_revenue"] ?? 0, 2); ?>\n';
            csv += 'Week Revenue,₱<?php echo number_format($revenueStats["week_revenue"] ?? 0, 2); ?>\n';
            csv += 'Month Revenue,₱<?php echo number_format($revenueStats["month_revenue"] ?? 0, 2); ?>\n';
            csv += 'Completed Bookings,<?php echo $revenueStats["completed_bookings"] ?? 0; ?>\n';
            csv += 'Pending Bookings,<?php echo $revenueStats["pending_bookings"] ?? 0; ?>\n';
            csv += 'Cancelled Bookings,<?php echo $revenueStats["cancelled_bookings"] ?? 0; ?>\n';
            csv += 'Average Transaction,₱<?php echo number_format($avgRevenue, 2); ?>\n\n';
            
            // Top Services
            csv += 'TOP SERVICES\n';
            csv += 'Service,Bookings,Revenue\n';
            const servicesTable = document.querySelectorAll('.card')[2].querySelectorAll('tbody tr');
            servicesTable.forEach(row => {
                const cols = row.querySelectorAll('td');
                if (cols.length >= 3) {
                    csv += cols[0].textContent.trim() + ',';
                    csv += cols[1].textContent.trim() + ',';
                    csv += cols[2].textContent.trim().replace(/₱/g, '') + '\n';
                }
            });
            
            csv += '\nTOP CUSTOMERS\n';
            csv += 'Rank,Customer Name,Email,Total Bookings,Total Spent\n';
            const customersTable = document.getElementById('customersTable').querySelectorAll('tbody tr');
            customersTable.forEach(row => {
                const cols = row.querySelectorAll('td');
                if (cols.length > 0) {
                    const rowData = [];
                    cols.forEach(col => {
                        let text = col.textContent.trim().replace(/"/g, '""');
                        text = text.replace('₱', '').replace(/,/g, '');
                        rowData.push('"' + text + '"');
                    });
                    csv += rowData.join(',') + '\n';
                }
            });
            
            downloadCSV(csv, 'smartwash_full_report_' + new Date().getTime() + '.csv');
        }

        // Helper function to download CSV
        function downloadCSV(csv, filename) {
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        // Quick date filters
        function setDateRange(range) {
            const endDate = new Date();
            let startDate = new Date();
            
            switch(range) {
                case 'today':
                    startDate = new Date();
                    break;
                case 'week':
                    startDate.setDate(endDate.getDate() - 7);
                    break;
                case 'month':
                    startDate.setMonth(endDate.getMonth() - 1);
                    break;
                case 'year':
                    startDate.setFullYear(endDate.getFullYear() - 1);
                    break;
            }
            
            document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
        }

        // Add quick filter buttons dynamically
        window.addEventListener('DOMContentLoaded', function() {
            const filterSection = document.querySelector('.filter-section form');
            const quickFilters = document.createElement('div');
            quickFilters.style.cssText = 'display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;';
            quickFilters.innerHTML = `
                <button type="button" class="btn-export" onclick="setDateRange('today')">Today</button>
                <button type="button" class="btn-export" onclick="setDateRange('week')">Last 7 Days</button>
                <button type="button" class="btn-export" onclick="setDateRange('month')">Last 30 Days</button>
                <button type="button" class="btn-export" onclick="setDateRange('year')">Last Year</button>
            `;
            filterSection.appendChild(quickFilters);
        });

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Add mobile menu button for smaller screens
        if (window.innerWidth <= 768) {
            const header = document.querySelector('.header');
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '☰';
            menuBtn.style.cssText = 'font-size: 1.5rem; background: none; border: none; cursor: pointer; color: #667eea;';
            menuBtn.onclick = toggleSidebar;
            header.insertBefore(menuBtn, header.firstChild);
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>