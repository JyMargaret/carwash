<?php
session_start();
include __DIR__ . '/../database/database.php';

$message = '';
$msgType = '';
$token = $_GET['token'] ?? '';
$validToken = false;

// Verify Token
if (!empty($token)) {
    $now = date('Y-m-d H:i:s');
    $sql = "SELECT user_id FROM users WHERE reset_token = ? AND reset_expires > ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $token, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) $validToken = true;
    else { $message = "Invalid or expired reset link."; $msgType = "error"; }
} else {
    header('Location: login/login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $p1 = $_POST['password'];
    $p2 = $_POST['confirm_password'];

    if ($p1 === $p2) {
        if (strlen($p1) >= 6) {
            $hashed = password_hash($p1, PASSWORD_DEFAULT);
            $upd = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?";
            $stmt = $conn->prepare($upd);
            $stmt->bind_param("ss", $hashed, $token);
            if ($stmt->execute()) {
                $message = "Password updated! Redirecting to login...";
                $msgType = "success";
                $validToken = false;
                header("refresh:3;url=login/login.php");
            } else { $message = "Error updating password."; $msgType = "error"; }
        } else { $message = "Password must be at least 6 characters."; $msgType = "error"; }
    } else { $message = "Passwords do not match."; $msgType = "error"; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - SmartWash</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.5s ease;
            text-align: center;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        h2 { font-size: 2rem; color: #333; margin-bottom: 0.5rem; }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        .form-group input {
            width: 100%; padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;
        }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn-primary {
            width: 100%; padding: 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: bold;
            cursor: pointer; transition: all 0.3s ease; box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; text-align: left; font-size: 0.9rem; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $msgType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($validToken): ?>
        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="6">
            </div>
            <button type="submit" class="btn-primary">Update Password</button>
        </form>
        <?php else: ?>
            <p style="margin-top: 1rem;"><a href="forgot_password.php" style="color: #667eea;">Request a new link</a></p>
        <?php endif; ?>
    </div>
</body>
</html>