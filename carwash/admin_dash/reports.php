<?php
session_start();

if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'admin') {
    header('Location: ../landing/login/login.php');
    exit;
}

$current_page = 'reports';

include __DIR__ . '/../database/database.php';

$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

$revenueQuery = "SELECT 
                SUM(CASE WHEN b.status = 'Completed' THEN b.final_amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN b.status = 'Completed' AND DATE(b.booking_date) = CURDATE() THEN b.final_amount ELSE 0 END) as today_revenue,
                SUM(CASE WHEN b.status = 'Completed' AND WEEK(b.booking_date) = WEEK(CURDATE()) THEN b.final_amount ELSE 0 END) as week_revenue,
                SUM(CASE WHEN b.status = 'Completed' AND MONTH(b.booking_date) = MONTH(CURDATE()) THEN b.final_amount ELSE 0 END) as month_revenue,
                COUNT(CASE WHEN b.status = 'Completed' THEN 1 END) as completed_bookings,
                COUNT(CASE WHEN b.status = 'Pending' OR b.status = 'Confirmed' THEN 1 END) as pending_bookings,
                COUNT(CASE WHEN b.status = 'Cancelled' THEN 1 END) as cancelled_bookings
                FROM bookings b
                WHERE b.booking_date BETWEEN '$startDate' AND '$endDate'";
$revenueResult = $conn->query($revenueQuery);
$revenueStats = $revenueResult ? $revenueResult->fetch_assoc() : [
    'total_revenue' => 0, 'today_revenue' => 0, 'week_revenue' => 0, 
    'month_revenue' => 0, 'completed_bookings' => 0, 'pending_bookings' => 0, 'cancelled_bookings' => 0
];

$topServicesQuery = "SELECT s.service_name as name,
                     COUNT(b.booking_id) as booking_count,
                     SUM(CASE WHEN b.status = 'Completed' THEN b.final_amount ELSE 0 END) as revenue,
                     AVG(CASE WHEN b.status = 'Completed' THEN b.final_amount ELSE NULL END) as avg_price
                     FROM services s
                     LEFT JOIN bookings b ON s.service_id = b.service_id 
                        AND b.booking_date BETWEEN '$startDate' AND '$endDate'
                     GROUP BY s.service_id
                     ORDER BY revenue DESC
                     LIMIT 5";
$topServicesResult = $conn->query($topServicesQuery);

$topCustomersQuery = "SELECT c.name, 
                      COALESCE(u.email, 'N/A') as email,
                      COUNT(b.booking_id) as booking_count,
                      SUM(CASE WHEN b.status = 'Completed' THEN b.final_amount ELSE 0 END) as total_spent
                      FROM customers c
                      LEFT JOIN users u ON c.user_id = u.user_id
                      LEFT JOIN bookings b ON c.customer_id = b.customer_id 
                        AND b.booking_date BETWEEN '$startDate' AND '$endDate'
                      GROUP BY c.customer_id
                      HAVING booking_count > 0
                      ORDER BY total_spent DESC
                      LIMIT 10";
$topCustomersResult = $conn->query($topCustomersQuery);

$dailyRevenueQuery = "SELECT DATE(b.booking_date) as date,
                      SUM(CASE WHEN b.status = 'Completed' THEN b.final_amount ELSE 0 END) as revenue,
                      COUNT(CASE WHEN b.status = 'Completed' THEN 1 END) as bookings
                      FROM bookings b
                      WHERE b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      GROUP BY DATE(b.booking_date)
                      ORDER BY date ASC";
$dailyRevenueResult = $conn->query($dailyRevenueQuery);

$monthlyQuery = "SELECT 
                DATE_FORMAT(b.booking_date, '%Y-%m') as month,
                SUM(CASE WHEN b.status = 'Completed' THEN b.final_amount ELSE 0 END) as revenue,
                COUNT(CASE WHEN b.status = 'Completed' THEN 1 END) as bookings
                FROM bookings b
                WHERE b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY month
                ORDER BY month ASC";
$monthlyResult = $conn->query($monthlyQuery);

$statusQuery = "SELECT status, COUNT(*) as count
                FROM bookings
                WHERE booking_date BETWEEN '$startDate' AND '$endDate'
                GROUP BY status";
$statusResult = $conn->query($statusQuery);

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; display: flex; min-height: 100vh; }

        /* SIDEBAR STYLES */
        .admin-sidebar {
            width: 260px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 999;
            left: 0;
            top: 0;
        }

        .admin-sidebar .logo {
            font-size: 1.8rem;
            font-weight: bold;
            padding: 0 1.5rem;
            margin-bottom: 2rem;
            letter-spacing: 0.5px;
        }

        .admin-sidebar nav { display: flex; flex-direction: column; }

        .admin-sidebar .menu-item {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            text-decoration: none;
            color: white;
            font-size: 1rem;
            position: relative;
        }

        .admin-sidebar .menu-item:hover {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: rgba(255, 255, 255, 0.5);
        }

        .admin-sidebar .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left-color: white;
            font-weight: 600;
        }

        .admin-sidebar .menu-item.active::after {
            content: '';
            position: absolute;
            right: 1rem;
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            font-size: 1.5rem;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active { display: block; opacity: 1; }

        .admin-sidebar::-webkit-scrollbar { width: 6px; }
        .admin-sidebar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.1); }
        .admin-sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.3); border-radius: 3px; }

        .main-content { margin-left: 260px; flex: 1; padding: 2rem; width: calc(100% - 260px); }
        .header { background: white; padding: 1.5rem 2rem; border-radius: 15px; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .filter-section { background: white; padding: 1.5rem 2rem; border-radius: 15px; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group input, .filter-group select { padding: 0.8rem; border: 2px solid #e0e0e0; border-radius: 10px; }
        .btn-primary { padding: 0.8rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 25px; cursor: pointer; font-weight: bold; }
        .export-buttons { display: flex; gap: 10px; }
        .btn-export { padding: 0.6rem 1.2rem; color: white; border: none; border-radius: 20px; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 5px; }
        .btn-pdf { background: #e74c3c; }
        .btn-excel { background: #27ae60; }
        .btn-word { background: #2980b9; }
        .btn-export:hover { opacity: 0.9; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #667eea; }
        .card { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .card-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; }
        .chart-container { position: relative; height: 350px; margin: 1rem 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; font-weight: 600; color: #666; }
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem; }

        @media (max-width: 1024px) { .content-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.active { transform: translateX(0); }
            .mobile-menu-toggle { display: flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0 !important; width: 100% !important; }
            .header { flex-direction: column; gap: 1rem; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <a href="index.php" class="menu-item <?php echo ($current_page === 'index') ? 'active' : ''; ?>">Dashboard</a>
            <a href="bookings.php" class="menu-item <?php echo ($current_page === 'bookings') ? 'active' : ''; ?>">Bookings</a>
            <a href="customers.php" class="menu-item <?php echo ($current_page === 'customers') ? 'active' : ''; ?>">Customers</a>
            <a href="services.php" class="menu-item <?php echo ($current_page === 'services') ? 'active' : ''; ?>">Services</a>
            <a href="staff.php" class="menu-item <?php echo ($current_page === 'staff') ? 'active' : ''; ?>">Staff</a>
            <a href="reports.php" class="menu-item <?php echo ($current_page === 'reports') ? 'active' : ''; ?>">Reports</a>
            <a href="settings.php" class="menu-item <?php echo ($current_page === 'settings') ? 'active' : ''; ?>">Settings</a>
        </nav>
    </aside>

    <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleSidebar()">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>Reports & Analytics</h1>
                <p style="color: #666; margin-top: 0.3rem;">Comprehensive business insights</p>
            </div>
            <div class="export-buttons">
                <button onclick="exportToPDF()" class="btn-export btn-pdf">📄 PDF</button>
                <button onclick="exportToExcel()" class="btn-export btn-excel">📊 Excel</button>
                <button onclick="exportToWord()" class="btn-export btn-word">📝 Word</button>
            </div>
        </div>

        <div class="filter-section">
            <form method="GET">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Report Type</label>
                        <select name="report_type">
                            <option value="overview">Overview</option>
                            <option value="revenue">Revenue</option>
                            <option value="services">Services</option>
                            <option value="customers">Customers</option>
                        </select>
                    </div>
                    <div class="filter-group" style="align-self: end;">
                        <button type="submit" class="btn-primary">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>

        <div id="printableArea">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">₱<?php echo number_format($revenueStats['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">₱<?php echo number_format($revenueStats['today_revenue'], 2); ?></div>
                    <div class="stat-label">Today's Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">₱<?php echo number_format($revenueStats['week_revenue'], 2); ?></div>
                    <div class="stat-label">This Week</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">₱<?php echo number_format($revenueStats['month_revenue'], 2); ?></div>
                    <div class="stat-label">This Month</div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $revenueStats['completed_bookings']; ?></div>
                    <div class="stat-label">Completed Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $revenueStats['pending_bookings']; ?></div>
                    <div class="stat-label">Pending Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $revenueStats['cancelled_bookings']; ?></div>
                    <div class="stat-label">Cancelled Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">₱<?php 
                        $avg = $revenueStats['completed_bookings'] > 0 
                            ? $revenueStats['total_revenue'] / $revenueStats['completed_bookings'] 
                            : 0;
                        echo number_format($avg, 2); 
                    ?></div>
                    <div class="stat-label">Average Transaction</div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <h2>Daily Revenue Trend (Last 30 Days)</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Booking Status</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Top Services</h2>
                </div>
                <table id="servicesTable">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Bookings</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($topServicesResult && $topServicesResult->num_rows > 0): ?>
                            <?php while ($service = $topServicesResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['name']); ?></td>
                                <td><?php echo $service['booking_count']; ?></td>
                                <td><strong>₱<?php echo number_format($service['revenue'], 2); ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align: center;">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Top Customers</h2>
                </div>
                <table id="customersTable">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Bookings</th>
                            <th>Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($topCustomersResult && $topCustomersResult->num_rows > 0): ?>
                            <?php while ($customer = $topCustomersResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td><?php echo $customer['booking_count']; ?></td>
                                <td><strong>₱<?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align: center;">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('mobileMenuToggle');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            toggle.innerHTML = sidebar.classList.contains('active') ? '✕' : '☰';
        }

        function closeSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('mobileMenuToggle');
            
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            toggle.innerHTML = '☰';
        }

        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        function exportToPDF() {
            const element = document.getElementById('printableArea');
            const opt = {
                margin: 0.5,
                filename: 'SmartWash_Report_<?php echo date("Y-m-d"); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }

        function exportToExcel() {
            const wb = XLSX.utils.book_new();
            const servicesTable = document.getElementById('servicesTable');
            if(servicesTable) {
                const ws1 = XLSX.utils.table_to_sheet(servicesTable);
                XLSX.utils.book_append_sheet(wb, ws1, "Top Services");
            }
            const customersTable = document.getElementById('customersTable');
            if(customersTable) {
                const ws2 = XLSX.utils.table_to_sheet(customersTable);
                XLSX.utils.book_append_sheet(wb, ws2, "Top Customers");
            }
            XLSX.writeFile(wb, 'SmartWash_Report_<?php echo date("Y-m-d"); ?>.xlsx');
        }

        function exportToWord() {
            const element = document.getElementById('printableArea').cloneNode(true);
            const charts = element.querySelectorAll('canvas');
            charts.forEach(c => c.parentNode.innerHTML = '<i>[Charts not available in .doc export. Please use PDF]</i>');
            const header = "<html xmlns:o='urn:schemas-microsoft-com:office:office' "+
                    "xmlns:w='urn:schemas-microsoft-com:office:word' "+
                    "xmlns='http://www.w3.org/TR/REC-html40'>"+
                    "<head><meta charset='utf-8'><title>SmartWash Report</title></head><body>";
            const footer = "</body></html>";
            const sourceHTML = header + element.innerHTML + footer;
            const source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
            const fileDownload = document.createElement("a");
            document.body.appendChild(fileDownload);
            fileDownload.href = source;
            fileDownload.download = 'SmartWash_Report_<?php echo date("Y-m-d"); ?>.doc';
            fileDownload.click();
            document.body.removeChild(fileDownload);
        }

        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = {
            labels: [
                <?php 
                if ($dailyRevenueResult) {
                    mysqli_data_seek($dailyRevenueResult, 0);
                    while ($day = $dailyRevenueResult->fetch_assoc()) {
                        echo "'" . date('M d', strtotime($day['date'])) . "',";
                    }
                }
                ?>
            ],
            datasets: [{
                label: 'Revenue (₱)',
                data: [
                    <?php 
                    if ($dailyRevenueResult) {
                        mysqli_data_seek($dailyRevenueResult, 0);
                        while ($day = $dailyRevenueResult->fetch_assoc()) {
                            echo $day['revenue'] . ',';
                        }
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
                plugins: { legend: { display: true, position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(value) { return '₱' + value.toLocaleString(); } }
                    }
                }
            }
        });

        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = {
            labels: [
                <?php 
                if ($statusResult) {
                    mysqli_data_seek($statusResult, 0);
                    while ($status = $statusResult->fetch_assoc()) {
                        echo "'" . ucfirst($status['status']) . "',";
                    }
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    if ($statusResult) {
                        mysqli_data_seek($statusResult, 0);
                        while ($status = $statusResult->fetch_assoc()) {
                            echo $status['count'] . ',';
                        }
                    }
                    ?>
                ],
                backgroundColor: ['#27ae60', '#f39c12', '#3498db', '#e74c3c']
            }]
        };

        new Chart(statusCtx, {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'bottom' } }
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>