<?php
session_start();
// Server-side login handling: authenticate against database customers/users tables
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include __DIR__ . '/../../database/database.php';

    $email = isset($_POST['email']) ? $conn->real_escape_string(trim($_POST['email'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $error = '';
    if ($email === '' || $password === '') {
        $error = 'Please provide email and password.';
    } else {
        // Dynamic lookup: search any table in the current DB that has an email-like column
        $foundUser = false;

        // Get current database name
        $dbRes = $conn->query("SELECT DATABASE() AS dbname");
        $currentDb = $dbRes ? $dbRes->fetch_assoc()['dbname'] : null;

        if ($currentDb) {
            // Find columns that look like email columns
            $colQuery = "SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($currentDb) . "' AND LOWER(COLUMN_NAME) LIKE '%email%';";
            $colRes = $conn->query($colQuery);

            if ($colRes && $colRes->num_rows > 0) {
                while ($row = $colRes->fetch_assoc()) {
                    $table = $row['TABLE_NAME'];
                    $emailCol = $row['COLUMN_NAME'];

                    // For each table with an email-like column, try to find the user
                    $sql = "SELECT * FROM `" . $conn->real_escape_string($table) . "` WHERE `" . $conn->real_escape_string($emailCol) . "` = '" . $email . "' LIMIT 1";
                    $res = $conn->query($sql);
                    if ($res && $res->num_rows > 0) {
                        $user = $res->fetch_assoc();
                        $foundUser = true;

                        // Determine password column by searching for column names containing 'pass'
                        $passCol = null;
                        foreach (array_keys($user) as $colName) {
                            if (stripos($colName, 'pass') !== false) {
                                $passCol = $colName;
                                break;
                            }
                        }

                        if (!$passCol) {
                            // No password column found in this table; cannot authenticate here
                            $error = 'User record found but password column is missing.';
                            break;
                        }

                        $dbPassword = $user[$passCol] ?? '';
                        if ($dbPassword !== '' && ($dbPassword === $password || password_verify($password, $dbPassword))) {
                            // Successful login
                            $_SESSION['userEmail'] = $user[$emailCol];
                            $_SESSION['userId'] = $user['id'] ?? $user['customer_id'] ?? null;
                            // determine role
                            $role = 'customer';
                            if (isset($user['role']) && $user['role'] !== '') $role = $user['role'];
                            elseif (stripos($table, 'admin') !== false) $role = 'admin';
                            elseif (stripos($table, 'emp') !== false || stripos($table, 'staff') !== false) $role = 'employee';
                            if (isset($user['is_admin']) && $user['is_admin']) $role = 'admin';
                            if (isset($user[$emailCol])) {
                                $lowerEmail = strtolower($user[$emailCol]);
                                if ($lowerEmail === 'admin@smartwash.com') {
                                    $role = 'admin';
                                } elseif ($lowerEmail === 'employee@smartwash.com') {
                                    $role = 'employee';
                                }
                            }
                            $_SESSION['userRole'] = $role;
                            $_SESSION['userName'] = $user['name'] ?? $user['full_name'] ?? $user['username'] ?? 'User';

                            if ($role === 'admin') {
                                header('Location: /carwash/admin_dash/index.php');
                                exit;
                            } elseif ($role === 'employee') {
                                header('Location: /carwash/emp_dash/index.php');
                                exit;
                            } else {
                                header('Location: /carwash/user_dash/index.php');
                                exit;
                            }
                        } else {
                            $error = 'Invalid email or password.';
                        }

                        break; 
                    }
                }
            }
        }

        if (!$foundUser && $error === '') {
            $error = 'No account found with that email.';
        }
    }
    
    // Close connection
    if (isset($conn)) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            max-width: 1000px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }

        .login-left::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
        }

        .logo-section {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .logo {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .logo-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .tagline {
            font-size: 1.2rem;
            opacity: 0.95;
            line-height: 1.6;
        }

        .login-right {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 2rem;
        }

        .login-header h2 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #666;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.2rem;
        }

        .form-group input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: #764ba2;
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .login-btn:active {
            transform: translateY(-1px);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: #999;
            font-size: 0.9rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e0e0e0;
        }

        .divider span {
            padding: 0 1rem;
        }

        .signup-link {
            text-align: center;
            color: #666;
            font-size: 0.95rem;
        }

        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .signup-link a:hover {
            color: #764ba2;
        }

        .error-message {
            display: none;
            background: #ff4757;
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            animation: shake 0.5s ease;
        }

        .error-message.show {
            display: block;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .back-home {
            position: absolute;
            top: 2rem;
            left: 2rem;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .back-home:hover {
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
            }

            .login-left {
                padding: 2rem;
                min-height: 250px;
            }

            .logo {
                font-size: 2rem;
            }

            .logo-icon {
                font-size: 3rem;
            }

            .tagline {
                font-size: 1rem;
            }

            .login-right {
                padding: 2rem;
            }

            .back-home {
                position: static;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <a href="../index.php" class="back-home">← Back to Home</a>

    <div class="login-container">
        <div class="login-left">
            <div class="logo-section">
                <div class="logo-icon"></div>
                <div class="logo">SmartWash</div>
                <p class="tagline">The Future of Professional<br>Car Washing Technology</p>
            </div>
        </div>

        <div class="login-right">
            <div class="login-header">
                <h2>Welcome Back!</h2>
                <p>Sign in to your account to continue</p>
            </div>

            <div id="errorMessage" class="error-message <?php if (!empty($error)) echo 'show'; ?>">
                <?php if (!empty($error)) echo htmlspecialchars($error); ?>
            </div>

            <form id="loginForm" method="post" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon"></span>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon"></span>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-password" onclick="alert('Password reset link would be sent to your email'); return false;">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn">Sign In</button>
            </form>

            <div class="divider">
                <span>OR</span>
            </div>

            <div class="signup-link">
                Don't have an account? <a href="register.php">Sign Up</a>
            </div>
        </div>
    </div>

    <script>
        function handleLogin(event) {
            event.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorMessage = document.getElementById('errorMessage');
            
            // Hide previous error
            errorMessage.classList.remove('show');
            
            const validUsers = {
                'admin@smartwash.com': { password: 'admin123', role: 'admin', redirect: '/carwash/admin_dash/index.php' },
                'user@smartwash.com': { password: 'user123', role: 'customer', redirect: '/carwash/user_dash/index.php' },
                'employee@smartwash.com': { password: 'employee123', role: 'employee', redirect: '/carwash/emp_dash/index.php' }
            };
            
            // Check credentials
            if (validUsers[email] && validUsers[email].password === password) {
                // Success - Store user session (in production, use proper session management)
                sessionStorage.setItem('userEmail', email);
                sessionStorage.setItem('userRole', validUsers[email].role);
                // Show success message
                alert('Login successful! Redirecting to your dashboard...');
                // Redirect to appropriate dashboard
                var redirectUrl = validUsers[email].redirect;
                // Fallback: check if file exists, else go to landing
                setTimeout(function() {
                    window.location.href = redirectUrl;
                }, 100);
            } else {
                // Show error message
                errorMessage.textContent = '❌ Invalid email or password. Please try again.';
                errorMessage.classList.add('show');
                // Clear password field
                document.getElementById('password').value = '';
            }
            
             
            // For PHP integration, use this code:
            fetch('login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorMessage.textContent = '❌ ' + data.message;
                    errorMessage.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorMessage.textContent = '❌ An error occurred. Please try again.';
                errorMessage.classList.add('show');
            });
            
        }

        // Check if user is already logged in
        window.addEventListener('load', function() {
            const userEmail = sessionStorage.getItem('userEmail');
            if (userEmail) {
                // User already logged in, could redirect to dashboard
                // Uncomment if you want auto-redirect for logged-in users
                // window.location.href = 'dashboard.html';
            }
        });
    </script>
</body>
</html>