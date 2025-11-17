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

        .clock-in-btn {
            padding: 1rem 2.5rem;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        .clock-in-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .clock-in-btn.clocked-in {
            background: #ff4757;
            color: white;
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

        .dashboard-grid {
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

        .task-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .task-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .task-item.urgent {
            border-left: 4px solid #ff4757;
        }

        .task-item.normal {
            border-left: 4px solid #ffa502;
        }

        .task-item.low {
            border-left: 4px solid #667eea;
        }

        .task-item.completed {
            opacity: 0.6;
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

        .btn-start {
            padding: 0.6rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-complete {
            padding: 0.6rem 1.5rem;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-complete:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-complete:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .performance-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1rem;
        }

        .performance-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .performance-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .performance-label {
            font-size: 0.9rem;
        }

        .performance-value {
            font-size: 1.3rem;
            font-weight: bold;
        }

        .progress-bar {
            background: rgba(255, 255, 255, 0.3);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            background: white;
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .schedule-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .schedule-time {
            font-weight: bold;
            color: #667eea;
            min-width: 80px;
        }

        .schedule-details h4 {
            font-size: 0.95rem;
            margin-bottom: 0.3rem;
            color: #333;
        }

        .schedule-details p {
            font-size: 0.85rem;
            color: #666;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .quick-action-btn {
            padding: 1rem;
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-action-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
        }

        .quick-action-icon {
            font-size: 1.5rem;
        }

        .notification-badge {
            position: relative;
        }

        .badge-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .tips-section {
            background: #fff3cd;
            border-left: 4px solid #ffa502;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .tips-section h4 {
            color: #856404;
            margin-bottom: 0.5rem;
        }

        .tips-section p {
            color: #856404;
            font-size: 0.9rem;
        }

        .notification {
            position: fixed;
            top: 80px;
            right: 20px;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 1rem;
            z-index: 1001;
            animation: slideIn 0.3s ease;
            border-left: 4px solid #17a2b8;
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
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-content {
                flex-direction: column;
                gap: 1rem;
            }

            .navbar {
                padding: 1rem;
            }

            .employee-info span {
                display: none;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        .timer-display {
            font-size: 1.5rem;
            font-weight: bold;
            margin-top: 0.5rem;
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
            <span>Employee: Employee</span>
            <div class="employee-avatar">👤</div>
            <button class="logout-btn" onclick="logout()">Logout</button>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h1>Good Morning, Employee! 👋</h1>
                    <p id="taskCount">You have 5 tasks assigned today</p>
                    <div class="timer-display" id="workTimer">Shift Time: 00:00:00</div>
                </div>
                <button class="clock-in-btn" id="clockBtn" onclick="toggleClock()">Clock In</button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value" id="completedCount">8</div>
                <div class="stat-label">Completed Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔄</div>
                <div class="stat-value" id="inProgressCount">0</div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏰</div>
                <div class="stat-value" id="pendingCount">5</div>
                <div class="stat-label">Pending Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value">4.9</div>
                <div class="stat-label">Your Rating</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My Tasks</h2>
                    <select style="padding: 0.5rem; border-radius: 10px; border: 2px solid #e0e0e0;">
                        <option>All Tasks</option>
                        <option>Urgent</option>
                        <option>Normal</option>
                        <option>Low Priority</option>
                    </select>
                </div>

                <div id="taskList">
                    <!-- Tasks will be populated here -->
                </div>

                <div class="tips-section">
                    <h4>💡 Pro Tip</h4>
                    <p>Always check the vehicle for existing damages before starting the wash. Take photos if needed.</p>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="performance-card">
                        <div class="performance-title">Today's Performance</div>
                        <div class="performance-stat">
                            <span class="performance-label">Completed Tasks</span>
                            <span class="performance-value" id="performanceComplete">8/13</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressBar" style="width: 62%;"></div>
                        </div>
                        <div class="performance-stat" style="margin-top: 1rem;">
                            <span class="performance-label">Efficiency</span>
                            <span class="performance-value">95%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 95%;"></div>
                        </div>
                    </div>

                    <div class="quick-actions">
                        <button class="quick-action-btn" onclick="alert('Reporting issue...')">
                            <span class="quick-action-icon">⚠️</span>
                            <span>Report Issue</span>
                        </button>
                        <button class="quick-action-btn" onclick="alert('Requesting supplies...')">
                            <span class="quick-action-icon">🧴</span>
                            <span>Request Supplies</span>
                        </button>
                        <button class="quick-action-btn notification-badge" onclick="alert('Opening messages...')">
                            <span class="quick-action-icon">💬</span>
                            <span>Messages</span>
                            <span class="badge-count">3</span>
                        </button>
                        <button class="quick-action-btn" onclick="alert('Taking break...')">
                            <span class="quick-action-icon">☕</span>
                            <span>Break Time</span>
                        </button>
                    </div>
                </div>

                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">Today's Schedule</h2>
                    </div>
                    <div class="schedule-item">
                        <div class="schedule-time">08:00 AM</div>
                        <div class="schedule-details">
                            <h4>Shift Start</h4>
                            <p>Morning briefing with supervisor</p>
                        </div>
                    </div>
                    <div class="schedule-item">
                        <div class="schedule-time">10:00 AM</div>
                        <div class="schedule-details">
                            <h4>Peak Hours Begin</h4>
                            <p>5 vehicles scheduled</p>
                        </div>
                    </div>
                    <div class="schedule-item">
                        <div class="schedule-time">12:00 PM</div>
                        <div class="schedule-details">
                            <h4>Lunch Break</h4>
                            <p>30 minutes break time</p>
                        </div>
                    </div>
                    <div class="schedule-item">
                        <div class="schedule-time">05:00 PM</div>
                        <div class="schedule-details">
                            <h4>Shift End</h4>
                            <p>Clean up and report submission</p>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">This Week Stats</h2>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 10px;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #667eea;">42</div>
                            <div style="font-size: 0.85rem; color: #666;">Total Tasks</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 10px;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #667eea;">38</div>
                            <div style="font-size: 0.85rem; color: #666;">Completed</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 10px;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #667eea;">₱8,500</div>
                            <div style="font-size: 0.85rem; color: #666;">Tips Earned</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 10px;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #667eea;">32</div>
                            <div style="font-size: 0.85rem; color: #666;">Hours Worked</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isClockedIn = false;
        let startTime = null;
        let timerInterval = null;

        // Load bookings and convert to tasks
        let tasks = [];
        let lastBookingCount = 0;

        function loadTasksFromBookings() {
            const bookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [];
            const upcomingBookings = bookings.filter(b => b.status === 'Upcoming' || b.status === 'In Progress');
            
            // Check for new bookings
            if (upcomingBookings.length > lastBookingCount) {
                const newCount = upcomingBookings.length - lastBookingCount;
                showNotification(`${newCount} new booking${newCount > 1 ? 's' : ''} received! 🚗`);
            }
            lastBookingCount = upcomingBookings.length;
            
            // Convert bookings to tasks
            tasks = upcomingBookings.map((booking, index) => ({
                id: booking.id,
                service: booking.service,
                vehicle: booking.vehicle,
                customer: 'Customer',
                bay: `Bay ${(index % 3) + 1}`,
                time: booking.time || '10:00 AM',
                priority: booking.service.includes('Ultimate') ? 'Urgent' : 
                         booking.service.includes('Premium') ? 'Normal' : 'Low',
                status: booking.status
            }));
            
            updateTaskList();
            updateStats();
        }

        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = `
                <span style="font-size: 1.5rem;">🔔</span>
                <span>${message}</span>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        function updateTaskList() {
            const taskList = document.getElementById('taskList');
            taskList.innerHTML = '';
            
            if (tasks.length === 0) {
                taskList.innerHTML = '<p style="text-align: center; color: #666; padding: 2rem;">No tasks available at the moment</p>';
                return;
            }
            
            tasks.forEach(task => {
                const taskItem = document.createElement('div');
                taskItem.className = `task-item ${task.priority.toLowerCase()} ${task.status === 'Completed' ? 'completed' : ''}`;
                
                let buttonHTML = '';
                if (task.status === 'Upcoming') {
                    buttonHTML = `<button class="btn-start" onclick="startTask(${task.id})">Start</button>`;
                } else if (task.status === 'In Progress') {
                    buttonHTML = `<button class="btn-complete" onclick="completeTask(${task.id})">Complete</button>`;
                } else {
                    buttonHTML = `<button class="btn-complete" disabled>Completed ✓</button>`;
                }
                
                taskItem.innerHTML = `
                    <div class="task-info">
                        <h4>${task.service} - ${task.vehicle}</h4>
                        <p>Customer: ${task.customer} • ${task.bay} • ${task.time}</p>
                    </div>
                    <div class="task-actions">
                        ${buttonHTML}
                    </div>
                `;
                taskList.appendChild(taskItem);
            });
        }

        function updateStats() {
            const pending = tasks.filter(t => t.status === 'Upcoming').length;
            const inProgress = tasks.filter(t => t.status === 'In Progress').length;
            const completed = parseInt(document.getElementById('completedCount').textContent);
            
            document.getElementById('pendingCount').textContent = pending;
            document.getElementById('inProgressCount').textContent = inProgress;
            document.getElementById('taskCount').textContent = `You have ${pending + inProgress} tasks assigned today`;
            
            const total = completed + pending + inProgress;
            const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
            document.getElementById('performanceComplete').textContent = `${completed}/${total}`;
            document.getElementById('progressBar').style.width = `${percentage}%`;
        }

        function startTask(taskId) {
            const task = tasks.find(t => t.id === taskId);
            if (!task) return;
            
            if (confirm(`Start working on this task?\n\n${task.service} - ${task.vehicle}`)) {
                // Update task status
                task.status = 'In Progress';
                
                // Update in localStorage
                const bookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [];
                const booking = bookings.find(b => b.id === taskId);
                if (booking) {
                    booking.status = 'In Progress';
                    localStorage.setItem('smartwash_bookings', JSON.stringify(bookings));
                }
                
                updateTaskList();
                updateStats();
                showNotification(`Started: ${task.service}`);
            }
        }

        function completeTask(taskId) {
            const task = tasks.find(t => t.id === taskId);
            if (!task) return;
            
            if (confirm(`Mark this task as complete?\n\n${task.service} - ${task.vehicle}`)) {
                // Update task status
                task.status = 'Completed';
                
                // Update in localStorage
                const bookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [];
                const booking = bookings.find(b => b.id === taskId);
                if (booking) {
                    booking.status = 'Completed';
                    localStorage.setItem('smartwash_bookings', JSON.stringify(bookings));
                }
                
                // Update completed count
                const completedStat = document.getElementById('completedCount');
                completedStat.textContent = parseInt(completedStat.textContent) + 1;
                
                // Remove from tasks array
                tasks = tasks.filter(t => t.id !== taskId);
                
                updateTaskList();
                updateStats();
                showNotification(`Completed: ${task.service} ✓`);
            }
        }

        function toggleClock() {
            const btn = document.getElementById('clockBtn');
            const statusIndicator = document.querySelector('.status-indicator span:last-child');
            
            if (!isClockedIn) {
                isClockedIn = true;
                btn.textContent = 'Clock Out';
                btn.classList.add('clocked-in');
                statusIndicator.textContent = 'On Duty';
                startTime = Date.now();
                startTimer();
                showNotification('Clocked in successfully! Have a great shift!');
            } else {
                if (confirm('Are you sure you want to clock out?')) {
                    isClockedIn = false;
                    btn.textContent = 'Clock In';
                    btn.classList.remove('clocked-in');
                    statusIndicator.textContent = 'Off Duty';
                    stopTimer();
                    showNotification('Clocked out successfully! Great work today!');
                }
            }
        }

        function startTimer() {
            timerInterval = setInterval(updateTimer, 1000);
        }

        function stopTimer() {
            clearInterval(timerInterval);
            document.getElementById('workTimer').textContent = 'Shift Time: 00:00:00';
        }

        function updateTimer() {
            if (!startTime) return;
            
            const elapsed = Date.now() - startTime;
            const hours = Math.floor(elapsed / 3600000);
            const minutes = Math.floor((elapsed % 3600000) / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            
            const timeString = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            document.getElementById('workTimer').textContent = `Shift Time: ${timeString}`;
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'index.html';
            }
        }

        // Initialize and check for new bookings
        document.addEventListener('DOMContentLoaded', function() {
            loadTasksFromBookings();
            
            // Check for new bookings every 3 seconds
            setInterval(loadTasksFromBookings, 3000);
        });
    </script>
</body>
</html>