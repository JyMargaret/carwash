<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Dashboard</title>
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
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

        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
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

        .booking-history {
            margin-top: 1rem;
        }

        .booking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }

        .booking-item:hover {
            background: #f8f9fa;
        }

        .booking-info h4 {
            font-size: 1rem;
            margin-bottom: 0.3rem;
            color: #333;
        }

        .booking-info p {
            font-size: 0.85rem;
            color: #666;
        }

        .booking-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background: #cce5ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .membership-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1rem;
        }

        .membership-tier {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .points-display {
            font-size: 2rem;
            font-weight: bold;
            margin: 1rem 0;
        }

        .progress-bar {
            background: rgba(255, 255, 255, 0.3);
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .progress-fill {
            background: white;
            height: 100%;
            width: 65%;
            border-radius: 5px;
            transition: width 0.5s ease;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
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
        }

        .quick-action-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
        }

        .vehicle-list {
            margin-top: 1rem;
        }

        .vehicle-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .vehicle-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .vehicle-icon {
            font-size: 2rem;
        }

        .vehicle-details h4 {
            font-size: 1rem;
            margin-bottom: 0.2rem;
        }

        .vehicle-details p {
            font-size: 0.85rem;
            color: #666;
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
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
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

        .notification.success {
            border-left: 4px solid #28a745;
        }

        .notification.info {
            border-left: 4px solid #17a2b8;
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

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .navbar {
                padding: 1rem;
            }

            .user-info span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">SmartWash</div>
        <div class="user-info">
            <span>User</span>
            <div class="user-avatar">👤</div>
            <button class="logout-btn" onclick="window.location.href='../landing/logout.php'">Logout</button>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-section">
            <h1>Welcome back, User! 👋</h1>
            <p id="nextBooking">Your next wash is scheduled for October 28, 2025 at 10:00 AM</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🚗</div>
                <div class="stat-value" id="totalWashes">12</div>
                <div class="stat-label">Total Washes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value">850</div>
                <div class="stat-label">Loyalty Points</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₱350</div>
                <div class="stat-label">Total Savings</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value" id="upcomingCount">1</div>
                <div class="stat-label">Upcoming Bookings</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Booking History</h2>
                    <button class="btn-primary" onclick="openBookingModal()">New Booking</button>
                </div>
                <div class="booking-history" id="bookingHistory">
                    <!-- Bookings will be populated here -->
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="membership-card">
                        <div class="membership-tier">🏆 Gold Member</div>
                        <p>You're doing great!</p>
                        <div class="points-display">850 pts</div>
                        <p style="font-size: 0.9rem;">150 points to Platinum</p>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                    </div>
                    <div class="quick-actions">
                        <button class="quick-action-btn" onclick="openBookingModal()">📅 Book Now</button>
                        <button class="quick-action-btn" onclick="alert('Viewing rewards...')">🎁 Rewards</button>
                        <button class="quick-action-btn" onclick="alert('Viewing history...')">📊 History</button>
                        <button class="quick-action-btn" onclick="window.location.href='./support/chat.php'">💬 Support</button>
                    </div>
                </div>

                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">My Vehicles</h2>
                        <button class="btn-primary" onclick="openVehicleModal()">+ Add</button>
                    </div>
                    <div class="vehicle-list" id="vehicleList">
                        <!-- Vehicles will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="card-title">New Booking</h2>
                <span class="close-btn" onclick="closeBookingModal()">✕</span>
            </div>
            <form onsubmit="handleBooking(event)">
                <div class="form-group">
                    <label for="vehicle">Select Vehicle</label>
                    <select id="vehicle" required>
                        <option value="">Choose a vehicle</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="service">Select Service</label>
                    <select id="service" required>
                        <option value="">Choose a service</option>
                        <option value="basic">Basic Wash - ₱250</option>
                        <option value="premium">Premium Wash - ₱450</option>
                        <option value="ultimate">Ultimate Wash - ₱750</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" required>
                </div>
                <div class="form-group">
                    <label for="time">Time</label>
                    <input type="time" id="time" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem;">Confirm Booking</button>
            </form>
        </div>
    </div>

    <!-- Vehicle Modal -->
    <div id="vehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="card-title">Add Vehicle</h2>
                <span class="close-btn" onclick="closeVehicleModal()">✕</span>
            </div>
            <form onsubmit="handleAddVehicle(event)">
                <div class="form-group">
                    <label for="make">Make</label>
                    <input type="text" id="make" placeholder="e.g., Honda" required>
                </div>
                <div class="form-group">
                    <label for="model">Model</label>
                    <input type="text" id="model" placeholder="e.g., Civic" required>
                </div>
                <div class="form-group">
                    <label for="plate">Plate Number</label>
                    <input type="text" id="plate" placeholder="e.g., ABC 1234" required>
                </div>
                <div class="form-group">
                    <label for="type">Vehicle Type</label>
                    <select id="type" required>
                        <option value="">Select type</option>
                        <option value="sedan">Sedan</option>
                        <option value="suv">SUV</option>
                        <option value="truck">Truck</option>
                        <option value="van">Van</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="color">Color</label>
                    <input type="text" id="color" placeholder="e.g., White" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem;">Add Vehicle</button>
            </form>
        </div>
    </div>

    <script>
        // Simulated storage (in production, this would be a database)
        let vehicles = JSON.parse(localStorage.getItem('smartwash_vehicles')) || [
            { id: 'civic', make: 'Honda', model: 'Civic', plate: 'ABC 1234', type: 'Sedan', color: 'White' }
        ];

        let bookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [
            { id: 1, service: 'Premium Wash', date: 'October 20, 2025', vehicle: 'Honda Civic (ABC 1234)', status: 'Completed', time: '10:00 AM' },
            { id: 2, service: 'Ultimate Wash', date: 'October 28, 2025', vehicle: 'Honda Civic (ABC 1234)', status: 'Upcoming', time: '10:00 AM' },
            { id: 3, service: 'Basic Wash', date: 'October 15, 2025', vehicle: 'Honda Civic (ABC 1234)', status: 'Completed', time: '02:00 PM' },
            { id: 4, service: 'Premium Wash', date: 'October 10, 2025', vehicle: 'Honda Civic (ABC 1234)', status: 'Completed', time: '11:00 AM' }
        ];

        // Save to storage
        function saveData() {
            localStorage.setItem('smartwash_vehicles', JSON.stringify(vehicles));
            localStorage.setItem('smartwash_bookings', JSON.stringify(bookings));
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <span style="font-size: 1.5rem;">${type === 'success' ? '✓' : 'ℹ'}</span>
                <span>${message}</span>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 4000);
        }

        // Update booking history display
        function updateBookingHistory() {
            const bookingHistory = document.getElementById('bookingHistory');
            bookingHistory.innerHTML = '';
            
            bookings.forEach(booking => {
                const bookingItem = document.createElement('div');
                bookingItem.className = 'booking-item';
                
                let statusClass = 'status-pending';
                if (booking.status === 'Completed') statusClass = 'status-completed';
                if (booking.status === 'In Progress') statusClass = 'status-in-progress';
                if (booking.status === 'Cancelled') statusClass = 'status-cancelled';
                
                bookingItem.innerHTML = `
                    <div class="booking-info">
                        <h4>${booking.service}</h4>
                        <p>${booking.date} - ${booking.vehicle}</p>
                    </div>
                    <span class="booking-status ${statusClass}">${booking.status}</span>
                `;
                bookingHistory.appendChild(bookingItem);
            });
        }

        // Update vehicle list display
        function updateVehicleList() {
            const vehicleList = document.getElementById('vehicleList');
            vehicleList.innerHTML = '';
            
            vehicles.forEach(vehicle => {
                const vehicleItem = document.createElement('div');
                vehicleItem.className = 'vehicle-item';
                vehicleItem.innerHTML = `
                    <div class="vehicle-icon">🚗</div>
                    <div class="vehicle-details">
                        <h4>${vehicle.make} ${vehicle.model}</h4>
                        <p>${vehicle.plate} • ${vehicle.type} • ${vehicle.color}</p>
                    </div>
                `;
                vehicleList.appendChild(vehicleItem);
            });
        }

        // Update vehicle dropdown in booking modal
        function updateVehicleDropdown() {
            const vehicleSelect = document.getElementById('vehicle');
            vehicleSelect.innerHTML = '<option value="">Choose a vehicle</option>';
            
            vehicles.forEach(vehicle => {
                const option = document.createElement('option');
                option.value = vehicle.id;
                option.textContent = `${vehicle.make} ${vehicle.model} (${vehicle.plate})`;
                vehicleSelect.appendChild(option);
            });
        }

        function openBookingModal() {
            updateVehicleDropdown();
            document.getElementById('bookingModal').classList.add('active');
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date').setAttribute('min', today);
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.remove('active');
        }

        function openVehicleModal() {
            document.getElementById('vehicleModal').classList.add('active');
        }

        function closeVehicleModal() {
            document.getElementById('vehicleModal').classList.remove('active');
        }

        function handleBooking(event) {
            event.preventDefault();
            
            const vehicleId = document.getElementById('vehicle').value;
            const serviceId = document.getElementById('service').value;
            const date = document.getElementById('date').value;
            const time = document.getElementById('time').value;
            
            const selectedVehicle = vehicles.find(v => v.id === vehicleId);
            
            const serviceMap = {
                'basic': { name: 'Basic Wash', price: 250 },
                'premium': { name: 'Premium Wash', price: 450 },
                'ultimate': { name: 'Ultimate Wash', price: 750 }
            };
            const service = serviceMap[serviceId];
            
            const bookingDate = new Date(date);
            const formattedDate = bookingDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const newBooking = {
                id: Date.now(),
                service: service.name,
                date: formattedDate,
                vehicle: `${selectedVehicle.make} ${selectedVehicle.model} (${selectedVehicle.plate})`,
                status: 'Upcoming',
                price: service.price,
                time: time,
                rawDate: date
            };
            
            bookings.unshift(newBooking);
            saveData();
            updateBookingHistory();
            
            const upcomingCount = bookings.filter(b => b.status === 'Upcoming').length;
            document.getElementById('upcomingCount').textContent = upcomingCount;
            
            showNotification(`Booking confirmed! ${service.name} on ${formattedDate} at ${time}`);
            
            event.target.reset();
            closeBookingModal();
        }

        function handleAddVehicle(event) {
            event.preventDefault();
            
            const make = document.getElementById('make').value;
            const model = document.getElementById('model').value;
            const plate = document.getElementById('plate').value;
            const type = document.getElementById('type').value;
            const color = document.getElementById('color').value;
            
            const newVehicle = {
                id: plate.replace(/\s+/g, '_').toLowerCase(),
                make: make,
                model: model,
                plate: plate,
                type: type,
                color: color
            };
            
            vehicles.push(newVehicle);
            saveData();
            updateVehicleList();
            updateVehicleDropdown();
            
            showNotification(`Vehicle added: ${make} ${model} (${plate})`);
            
            event.target.reset();
            closeVehicleModal();
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'index.html';
            }
        }

        // Check for booking status updates
        function checkForUpdates() {
            const updatedBookings = JSON.parse(localStorage.getItem('smartwash_bookings')) || [];
            
            updatedBookings.forEach((updatedBooking, index) => {
                const existingBooking = bookings.find(b => b.id === updatedBooking.id);
                
                if (existingBooking && existingBooking.status !== updatedBooking.status) {
                    if (updatedBooking.status === 'In Progress') {
                        showNotification(`Your ${updatedBooking.service} is now in progress! 🚗`, 'info');
                    } else if (updatedBooking.status === 'Completed') {
                        showNotification(`Your ${updatedBooking.service} has been completed! ✓`, 'success');
                        document.getElementById('totalWashes').textContent = 
                            parseInt(document.getElementById('totalWashes').textContent) + 1;
                    }
                    
                    existingBooking.status = updatedBooking.status;
                }
            });
            
            bookings = updatedBookings;
            updateBookingHistory();
            
            const upcomingCount = bookings.filter(b => b.status === 'Upcoming').length;
            document.getElementById('upcomingCount').textContent = upcomingCount;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const bookingModal = document.getElementById('bookingModal');
            const vehicleModal = document.getElementById('vehicleModal');
            if (event.target === bookingModal) {
                closeBookingModal();
            }
            if (event.target === vehicleModal) {
                closeVehicleModal();
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateBookingHistory();
            updateVehicleList();
            
            // Check for updates every 3 seconds
            setInterval(checkForUpdates, 3000);
        });
    </script>
</body>
</html>