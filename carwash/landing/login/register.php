<?php
session_start();
require_once '../../database/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = isset($_POST['name']) ? $conn->real_escape_string(trim($_POST['name'])) : '';
    $email = isset($_POST['email']) ? $conn->real_escape_string(trim($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? $conn->real_escape_string(trim($_POST['phone'])) : '';
    $address = isset($_POST['address']) ? $conn->real_escape_string(trim($_POST['address'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // Validate input
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Detect actual columns present in the `users` table
        $dbRes = $conn->query("SELECT DATABASE() AS dbname");
        $dbName = $dbRes ? $dbRes->fetch_assoc()['dbname'] : null;
        $colQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'users'";
        $colRes = $conn->query($colQuery);
        $columns = [];
        if ($colRes && $colRes->num_rows > 0) {
            while ($r = $colRes->fetch_assoc()) {
                $columns[] = $r['COLUMN_NAME'];
            }
        }

        // Map desired fields to actual column names
        $fieldMap = [
            'name' => null,
            'email' => null,
            'phone' => null,
            'address' => null,
            'password' => null,
            'role' => null,
            'status' => null,
        ];

        foreach ($columns as $col) {
            $lower = strtolower($col);
            if ($fieldMap['name'] === null && (strpos($lower, 'name') !== false || strpos($lower, 'full_name') !== false || strpos($lower, 'username') !== false)) {
                $fieldMap['name'] = $col;
            }
            if ($fieldMap['email'] === null && strpos($lower, 'email') !== false) {
                $fieldMap['email'] = $col;
            }
            if ($fieldMap['phone'] === null && (strpos($lower, 'phone') !== false || strpos($lower, 'mobile') !== false)) {
                $fieldMap['phone'] = $col;
            }
            if ($fieldMap['address'] === null && strpos($lower, 'address') !== false) {
                $fieldMap['address'] = $col;
            }
            if ($fieldMap['password'] === null && strpos($lower, 'pass') !== false) {
                $fieldMap['password'] = $col;
            }
            if ($fieldMap['role'] === null && $lower === 'role') {
                $fieldMap['role'] = $col;
            }
            if ($fieldMap['status'] === null && $lower === 'status') {
                $fieldMap['status'] = $col;
            }
        }

        // Check duplicate email if email column exists
        if ($fieldMap['email']) {
            $checkSql = "SELECT 1 FROM users WHERE `" . $fieldMap['email'] . "` = ? LIMIT 1";
            $chkStmt = $conn->prepare($checkSql);
            if ($chkStmt) {
                $chkStmt->bind_param('s', $email);
                $chkStmt->execute();
                $chkRes = $chkStmt->get_result();
                if ($chkRes && $chkRes->num_rows > 0) {
                    $error = 'Email already registered. Please use a different email.';
                }
                $chkStmt->close();
            }
        }

        if (empty($error)) {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Build dynamic insert using columns that exist
            $insertCols = [];
            $placeholders = [];
            $types = '';
            $values = [];

            if ($fieldMap['name']) { $insertCols[] = "`".$fieldMap['name']."`"; $placeholders[] = '?'; $types .= 's'; $values[] = $name; }
            if ($fieldMap['email']) { $insertCols[] = "`".$fieldMap['email']."`"; $placeholders[] = '?'; $types .= 's'; $values[] = $email; }
            if ($fieldMap['phone']) { $insertCols[] = "`".$fieldMap['phone']."`"; $placeholders[] = '?'; $types .= 's'; $values[] = $phone; }
            if ($fieldMap['address']) { $insertCols[] = "`".$fieldMap['address']."`"; $placeholders[] = '?'; $types .= 's'; $values[] = $address; }
            if ($fieldMap['password']) { $insertCols[] = "`".$fieldMap['password']."`"; $placeholders[] = '?'; $types .= 's'; $values[] = $hashedPassword; }
            if ($fieldMap['role']) { $insertCols[] = "`".$fieldMap['role']."`"; $placeholders[] = '?'; $types .= 's'; $values[] = 'customer'; }
            if ($fieldMap['status']) { $insertCols[] = "`".$fieldMap['status']."`"; $placeholders[] = '?'; $types .= 's'; $values[] = 'active'; }

            if (empty($insertCols)) {
                $error = 'Users table does not have expected columns. Please run the database setup script.';
            } else {
                $sql = "INSERT INTO users (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $placeholders) . ")";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $error = 'Failed to prepare registration query: ' . $conn->error;
                } else {
                    // Bind params dynamically
                    $bindNames = [];
                    $bindNames[] = $types;
                    for ($i=0;$i<count($values);$i++) { $bindNames[] = & $values[$i]; }
                    call_user_func_array([$stmt, 'bind_param'], $bindNames);

                    if ($stmt->execute()) {
                        // Redirect to login page immediately
                        header("Location: login.php");
                        exit();
                    } else {
                        $error = 'Registration failed. ' . $stmt->error;
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Register</title>
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
            justify-content: center;
            align-items: center;
            padding: 2rem;
            position: relative;
        }

        .register-container {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header h2 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .register-header p {
            color: #666;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
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

        .register-btn {
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
            margin-bottom: 1rem;
        }

        .register-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .login-link {
            text-align: center;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .error-message {
            background: #ff4757;
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .success-message {
            background: #2ed573;
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: none;
        }

        .success-message.show {
            display: block;
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
            font-size: 1rem;
        }

        .back-home:hover {
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .register-container {
                padding: 1.5rem;
            }

            .register-header h2 {
                font-size: 1.5rem;
            }

            .back-home {
                top: 1rem;
                left: 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <a href="../index.php" class="back-home">← Back to Home</a>

    <div class="register-container">
        <div class="register-header">
            <h2>Create Account</h2>
            <p>Join SmartWash today</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message show">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="success-message show">
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" required>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="register-btn">Create Account</button>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </form>
    </div>
</body>
</html>
