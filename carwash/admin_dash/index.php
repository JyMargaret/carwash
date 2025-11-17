<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Admin Dashboard</title>
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
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left-color: white;
        }

        .menu-icon {
            font-size: 1.5rem;
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

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-avatar {
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
            cursor: pointer;
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

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 2rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .stat-change {
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: #27ae60;
        }

        .stat-change.negative {
            color: #e74c3c;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

        .btn-primary {
            padding: 0.6rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
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

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            padding: 0.5rem 1rem;
            background: white;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .btn-danger:hover {
            background: #e74c3c;
            color: white;
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

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-upcoming {
            background: #cce5ff;
            color: #004085;
        }

        .status-in {
            background: #e7f3ff;
            color: #0056b3;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-active {
            background: #cce5ff;
            color: #004085;
        }

        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }

        .activity-item:hover {
            background: #f8f9fa;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .activity-details h4 {
            font-size: 0.95rem;
            margin-bottom: 0.3rem;
            color: #333;
        }

        .activity-details p {
            font-size: 0.8rem;
            color: #666;
        }

        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .search-input {
            flex: 1;
            padding: 0.8rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-select {
            padding: 0.8rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .quick-stat-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: center;
        }

        .quick-stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .quick-stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.3rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .mobile-menu-btn {
            display: none;
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            z-index: 1000;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            background: none;
            border: none;
            padding: 0.5rem;
        }

        .close-btn:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .notification {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            z-index: 2000;
            display: none;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
        }

        .notification.active {
            display: flex;
        }

        .notification.success {
            border-left: 4px solid #27ae60;
        }

        .notification.error {
            border-left: 4px solid #e74c3c;
        }

        .notification.info {
            border-left: 4px solid #667eea;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
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

            .header {
                flex-direction: column;
                gap: 1rem;
            }

            .mobile-menu-btn {
                display: block;
            }

            .search-bar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <div class="menu-item active" onclick="window.location.href='index.php'">
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
            <div class="menu-item" onclick="window.location.href='reports.php'">
                <span>Reports</span>
            </div>
            <div class="menu-item" onclick="window.location.href='settings.php'">
                <span>Settings</span>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>Dashboard Overview</h1>
                <p style="color: #666; margin-top: 0.3rem;" id="currentDate"></p>
            </div>
            <div class="admin-info">
                <div>
                    <p style="font-weight: 600;">Admin User</p>
                    <p style="font-size: 0.85rem; color: #666;">admin@smartwash.com</p>
                </div>
                <div class="admin-avatar">👤</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">💰</div>
                </div>
                <div class="stat-value" id="todayRevenue">₱0</div>
                <div class="stat-label">Today's Revenue</div>
                <div class="stat-change positive" id="revenueChange">↑ 0% from yesterday</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">📅</div>
                </div>
                <div class="stat-value" id="todayBookings">0</div>
                <div class="stat-label">Today's Bookings</div>
                <div class="stat-change positive" id="bookingChange">↑ 0% from yesterday</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">👥</div>
                </div>
                <div class="stat-value" id="totalCustomers">0</div>
                <div class="stat-label">Total Customers</div>
                <div class="stat-change positive">Active users</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">⭐</div>
                </div>
                <div class="stat-value">4.8</div>
                <div class="stat-label">Average Rating</div>
                <div class="stat-change positive">↑ 0.2 from last month</div>
            </div>
        </div>

        <div class="search-bar">
            <input type="text" class="search-input" id="searchInput" placeholder="Search bookings, customers, or services...">
            <select class="filter-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="completed">Completed</option>
                <option value="upcoming">Upcoming</option>
                <option value="in progress">In Progress</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <button class="btn-primary" onclick="performSearch()">Search</button>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Bookings</h2>
                    <button class="btn-primary" onclick="openNewBookingModal()">+ New Booking</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Vehicle</th>
                                <th>Date & Time</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bookingsTable">
                            <!-- Bookings will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Today's Summary</h2>
                    </div>
                    <div class="quick-stats">
                        <div class="quick-stat-item">
                            <div class="quick-stat-value" id="completedCount">0</div>
                            <div class="quick-stat-label">Completed</div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="quick-stat-value" id="upcomingCount">0</div>
                            <div class="quick-stat-label">Upcoming</div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="quick-stat-value" id="inProgressCount">0</div>
                            <div class="quick-stat-label">In Progress</div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="quick-stat-value" id="cancelledCount">0</div>
                            <div class="quick-stat-label">Cancelled</div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">Recent Activity</h2>
                    </div>
                    <div id="activityList">
                        <!-- Activity items will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Service Performance</h2>
                <select class="filter-select" id="performanceFilter" onchange="filterPerformance()">
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </select>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Total Bookings</th>
                            <th>Revenue</th>
                            <th>Avg. Rating</th>
                            <th>Growth</th>
                        </tr>
                    </thead>
                    <tbody id="performanceTable">
                        <tr>
                            <td><strong>Premium Wash</strong></td>
                            <td id="premiumCount">0</td>
                            <td id="premiumRevenue">₱0</td>
                            <td>⭐ 4.9</td>
                            <td><span class="stat-change positive">↑ 18%</span></td>
                        </tr>
                        <tr>
                            <td><strong>Ultimate Wash</strong></td>
                            <td id="ultimateCount">0</td>
                            <td id="ultimateRevenue">₱0</td>
                            <td>⭐ 4.8</td>
                            <td><span class="stat-change positive">↑ 25%</span></td>
                        </tr>
                        <tr>
                            <td><strong>Basic Wash</strong></td>
                            <td id="basicCount">0</td>
                            <td id="basicRevenue">₱0</td>
                            <td>⭐ 4.7</td>
                            <td><span class="stat-change positive">↑ 5%</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>

    <!-- Modals -->
    <div class="modal" id="bookingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">New Booking</h2>
                <button class="close-btn" onclick="closeModal('bookingModal')">×</button>
            </div>
            <form id="bookingForm" onsubmit="handleBookingSubmit(event)">
                <div class="form-group">
                    <label class="form-label">Customer Name</label>
                    <input type="text" class="form-input" id="customerName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Service</label>
                    <select class="form-input" id="serviceType" required>
                        <option value="">Select Service</option>
                        <option value="Basic Wash">Basic Wash - ₱250</option>
                        <option value="Premium Wash">Premium Wash - ₱450</option>
                        <option value="Ultimate Wash">Ultimate Wash - ₱750</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Vehicle</label>
                    <input type="text" class="form-input" id="vehicleInfo" placeholder="e.g., Honda Civic (ABC 1234)" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-input" id="bookingDate" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Time</label>
                    <input type="time" class="form-input" id="bookingTime" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-input" id="bookingStatus" required>
                        <option value="Upcoming">Upcoming</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('bookingModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Booking</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Booking Details</h2>
                <button class="close-btn" onclick="closeModal('viewModal')">×</button>
            </div>
            <div id="viewContent"></div>
        </div>
    </div>

    <div class="notification" id="notification">
        <span id="notificationIcon"></span>
        <span id="notificationText"></span>
    </div>

    <script>
        let bookings = [];
        let activities = [];
        let editingId = null;

        // Load bookings from localStorage
        function loadBookingsFromStorage() {
            const storedBookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [];
            
            // Convert stored bookings to admin format
            bookings = storedBookings.map(booking => ({
                id: booking.id,
                customer: 'Customer User',
                service: booking.service,
                vehicle: booking.vehicle,
                date: booking.date,
                time: booking.time || '10:00 AM',
                dateTime: `${booking.date}${booking.time ? ', ' + booking.time : ''}`,
                amount: getServiceAmount(booking.service),
                status: booking.status,
                rawDate: booking.rawDate || new Date().toISOString().split('T')[0]
            }));
            
            updateUI();
        }

        function getServiceAmount(service) {
            const amounts = {
                'Basic Wash': 250,
                'Premium Wash': 450,
                'Ultimate Wash': 750
            };
            return amounts[service] || 0;
        }

        function updateUI() {
            renderBookings();
            updateStats();
            updateServicePerformance();
            generateActivities();
        }

        function generateActivities() {
            activities = [];
            
            // Get recent bookings (last 5)
            const recentBookings = [...bookings].slice(0, 5);
            
            recentBookings.forEach((booking, index) => {
                let icon = '📅';
                let title = 'New Booking';
                let timeAgo = index === 0 ? 'Just now' : `${index * 15} mins ago`;
                
                if (booking.status === 'Completed') {
                    icon = '✅';
                    title = 'Booking Completed';
                } else if (booking.status === 'In Progress') {
                    icon = '🔄';
                    title = 'Booking In Progress';
                } else if (booking.status === 'Cancelled') {
                    icon = '❌';
                    title = 'Booking Cancelled';
                }
                
                activities.push({
                    icon,
                    title,
                    desc: `${booking.customer} - ${booking.service} • ${timeAgo}`
                });
            });
            
            renderActivities();
        }

        function init() {
            updateDate();
            loadBookingsFromStorage();
            
            // Check for updates every 3 seconds
            setInterval(checkForUpdates, 3000);
        }

        function checkForUpdates() {
            const currentBookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [];
            
            // Check if there are new bookings or status changes
            if (currentBookings.length !== bookings.length) {
                const newCount = currentBookings.length - bookings.length;
                if (newCount > 0) {
                    showNotification(`${newCount} new booking${newCount > 1 ? 's' : ''} received!`, 'success');
                }
                loadBookingsFromStorage();
            } else {
                // Check for status changes
                currentBookings.forEach(currentBooking => {
                    const existingBooking = bookings.find(b => b.id === currentBooking.id);
                    if (existingBooking && existingBooking.status !== currentBooking.status) {
                        showNotification(`Booking status updated: ${currentBooking.status}`, 'info');
                        loadBookingsFromStorage();
                    }
                });
            }
        }

        function updateDate() {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const date = new Date().toLocaleDateString('en-US', options);
            document.getElementById('currentDate').textContent = date;
        }

        function renderBookings(filter = '') {
            const tbody = document.getElementById('bookingsTable');
            let filtered = bookings;

            if (filter) {
                filtered = bookings.filter(b => 
                    b.customer.toLowerCase().includes(filter.toLowerCase()) ||
                    b.service.toLowerCase().includes(filter.toLowerCase()) ||
                    b.vehicle.toLowerCase().includes(filter.toLowerCase())
                );
            }

            const statusFilter = document.getElementById('statusFilter').value;
            if (statusFilter) {
                filtered = filtered.filter(b => b.status.toLowerCase() === statusFilter);
            }

            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: #666;">No bookings found</td></tr>';
                return;
            }

            tbody.innerHTML = filtered.map(booking => {
                const statusClass = booking.status.toLowerCase().replace(' ', '-');
                return `
                <tr>
                    <td>${booking.customer}</td>
                    <td>${booking.service}</td>
                    <td>${booking.vehicle}</td>
                    <td>${booking.dateTime}</td>
                    <td>₱${booking.amount}</td>
                    <td><span class="status-badge status-${statusClass}">${booking.status}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-secondary" onclick="viewBooking(${booking.id})">View</button>
                            <button class="btn-secondary" onclick="editBooking(${booking.id})">Edit</button>
                            <button class="btn-danger" onclick="deleteBooking(${booking.id})">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
            }).join('');
        }

        function renderActivities() {
            const list = document.getElementById('activityList');
            
            if (activities.length === 0) {
                list.innerHTML = '<p style="text-align: center; padding: 2rem; color: #666;">No recent activity</p>';
                return;
            }
            
            list.innerHTML = activities.map(activity => `
                <div class="activity-item">
                    <div class="activity-icon">${activity.icon}</div>
                    <div class="activity-details">
                        <h4>${activity.title}</h4>
                        <p>${activity.desc}</p>
                    </div>
                </div>
            `).join('');
        }

        function updateStats() {
            const completed = bookings.filter(b => b.status === 'Completed').length;
            const upcoming = bookings.filter(b => b.status === 'Upcoming').length;
            const inProgress = bookings.filter(b => b.status === 'In Progress').length;
            const cancelled = bookings.filter(b => b.status === 'Cancelled').length;
            const totalRevenue = bookings.filter(b => b.status === 'Completed').reduce((sum, b) => sum + b.amount, 0);

            document.getElementById('completedCount').textContent = completed;
            document.getElementById('upcomingCount').textContent = upcoming;
            document.getElementById('inProgressCount').textContent = inProgress;
            document.getElementById('cancelledCount').textContent = cancelled;
            document.getElementById('todayRevenue').textContent = '₱' + totalRevenue.toLocaleString();
            document.getElementById('todayBookings').textContent = bookings.length;
            
            // Get unique customers from bookings
            const uniqueCustomers = new Set(bookings.map(b => b.customer));
            document.getElementById('totalCustomers').textContent = uniqueCustomers.size;
        }

        function updateServicePerformance() {
            const basicBookings = bookings.filter(b => b.service === 'Basic Wash');
            const premiumBookings = bookings.filter(b => b.service === 'Premium Wash');
            const ultimateBookings = bookings.filter(b => b.service === 'Ultimate Wash');
            
            const basicRevenue = basicBookings.filter(b => b.status === 'Completed').reduce((sum, b) => sum + b.amount, 0);
            const premiumRevenue = premiumBookings.filter(b => b.status === 'Completed').reduce((sum, b) => sum + b.amount, 0);
            const ultimateRevenue = ultimateBookings.filter(b => b.status === 'Completed').reduce((sum, b) => sum + b.amount, 0);
            
            document.getElementById('basicCount').textContent = basicBookings.length;
            document.getElementById('basicRevenue').textContent = '₱' + basicRevenue.toLocaleString();
            
            document.getElementById('premiumCount').textContent = premiumBookings.length;
            document.getElementById('premiumRevenue').textContent = '₱' + premiumRevenue.toLocaleString();
            
            document.getElementById('ultimateCount').textContent = ultimateBookings.length;
            document.getElementById('ultimateRevenue').textContent = '₱' + ultimateRevenue.toLocaleString();
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function performSearch() {
            const searchTerm = document.getElementById('searchInput').value;
            renderBookings(searchTerm);
            if (searchTerm) {
                showNotification(`Searching for: ${searchTerm}`, 'info');
            }
        }

        document.getElementById('statusFilter').addEventListener('change', function() {
            renderBookings(document.getElementById('searchInput').value);
        });

        function filterPerformance() {
            const filter = document.getElementById('performanceFilter').value;
            showNotification(`Showing performance for: ${filter}`, 'info');
        }

        function openNewBookingModal() {
            editingId = null;
            document.getElementById('modalTitle').textContent = 'New Booking';
            document.getElementById('bookingForm').reset();
            
            // Set default date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('bookingDate').value = today;
            document.getElementById('bookingDate').setAttribute('min', today);
            
            document.getElementById('bookingModal').classList.add('active');
        }

        function viewBooking(id) {
            const booking = bookings.find(b => b.id === id);
            if (booking) {
                const statusClass = booking.status.toLowerCase().replace(' ', '-');
                const content = `
                    <div style="padding: 1rem;">
                        <div style="margin-bottom: 1rem;">
                            <strong>Customer:</strong> ${booking.customer}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Service:</strong> ${booking.service}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Vehicle:</strong> ${booking.vehicle}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Date & Time:</strong> ${booking.dateTime}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Amount:</strong> ₱${booking.amount}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Status:</strong> <span class="status-badge status-${statusClass}">${booking.status}</span>
                        </div>
                        <div style="margin-top: 2rem;">
                            <button class="btn-primary" onclick="closeModal('viewModal')">Close</button>
                        </div>
                    </div>
                `;
                document.getElementById('viewContent').innerHTML = content;
                document.getElementById('viewModal').classList.add('active');
            }
        }

        function editBooking(id) {
            const booking = bookings.find(b => b.id === id);
            if (booking) {
                editingId = id;
                document.getElementById('modalTitle').textContent = 'Edit Booking';
                document.getElementById('customerName').value = booking.customer;
                document.getElementById('serviceType').value = booking.service;
                document.getElementById('vehicleInfo').value = booking.vehicle;
                document.getElementById('bookingDate').value = booking.rawDate;
                document.getElementById('bookingTime').value = booking.time;
                document.getElementById('bookingStatus').value = booking.status;
                document.getElementById('bookingModal').classList.add('active');
            }
        }

        function deleteBooking(id) {
            if (confirm('Are you sure you want to delete this booking?')) {
                // Remove from bookings array
                bookings = bookings.filter(b => b.id !== id);
                
                // Update localStorage
                const storedBookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [];
                const updatedBookings = storedBookings.filter(b => b.id !== id);
                localStorage.setItem('smartwash_bookings', JSON.stringify(updatedBookings));
                
                updateUI();
                showNotification('Booking deleted successfully!', 'success');
            }
        }

        function handleBookingSubmit(event) {
            event.preventDefault();
            
            const customer = document.getElementById('customerName').value;
            const service = document.getElementById('serviceType').value;
            const vehicle = document.getElementById('vehicleInfo').value;
            const date = document.getElementById('bookingDate').value;
            const time = document.getElementById('bookingTime').value;
            const status = document.getElementById('bookingStatus').value;
            
            const amount = getServiceAmount(service);
            
            const bookingDate = new Date(date);
            const formattedDate = bookingDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            if (editingId) {
                // Update existing booking
                const storedBookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [];
                const index = storedBookings.findIndex(b => b.id === editingId);
                
                if (index !== -1) {
                    storedBookings[index] = {
                        ...storedBookings[index],
                        service: service,
                        vehicle: vehicle,
                        date: formattedDate,
                        time: time,
                        status: status,
                        rawDate: date
                    };
                    
                    localStorage.setItem('smartwash_bookings', JSON.stringify(storedBookings));
                    showNotification('Booking updated successfully!', 'success');
                }
            } else {
                // Create new booking
                const newBooking = {
                    id: Date.now(),
                    service: service,
                    date: formattedDate,
                    vehicle: vehicle,
                    status: status,
                    price: amount,
                    time: time,
                    rawDate: date
                };
                
                const storedBookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [];
                storedBookings.unshift(newBooking);
                localStorage.setItem('smartwash_bookings', JSON.stringify(storedBookings));
                
                showNotification('Booking created successfully!', 'success');
            }
            
            loadBookingsFromStorage();
            closeModal('bookingModal');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            const notificationText = document.getElementById('notificationText');
            const notificationIcon = document.getElementById('notificationIcon');
            
            const icons = {
                success: '✓',
                error: '✕',
                info: 'ℹ'
            };
            
            notification.className = 'notification active ' + type;
            notificationIcon.textContent = icons[type] || 'ℹ';
            notificationText.textContent = message;
            
            setTimeout(() => {
                notification.classList.remove('active');
            }, 4000);
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // Initialize on page load
        init();
    </script>
</body>
</html>