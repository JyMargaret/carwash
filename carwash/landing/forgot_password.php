<?php
session_start();

$dbPath = __DIR__ . '/../database/database.php';
if (file_exists($dbPath)) {
    include $dbPath;
} else {
    // Fallback if moved to login folder
    include __DIR__ . '/../../database/database.php'; 
}

// PHPMailer Path (check landing/PHPMailer first, then login/PHPMailer as fallback)
if (file_exists(__DIR__ . '/PHPMailer/src/Exception.php')) {
    require __DIR__ . '/PHPMailer/src/Exception.php';
    require __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer/src/SMTP.php';
} elseif (file_exists(__DIR__ . '/login/PHPMailer/Exception.php')) {
    // Some installs have PHPMailer under landing/login/PHPMailer
    require __DIR__ . '/login/PHPMailer/Exception.php';
    require __DIR__ . '/login/PHPMailer/PHPMailer.php';
    require __DIR__ . '/login/PHPMailer/SMTP.php';
} else {
    die("Error: PHPMailer not found. Please copy the 'PHPMailer' folder into the 'landing' directory or install PHPMailer.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string($_POST['email']);

    // Check if email exists
    $sql = "SELECT user_id FROM users WHERE email = '$email' LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $update = "UPDATE users SET reset_token = '$token', reset_expires = '$expires' WHERE email = '$email'";
        
        if ($conn->query($update)) {
            // Determine Protocol
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            
            // Link points to 'login/reset_password.php' assuming that file is in the login folder
            // If reset_password.php is also in 'landing/', remove '/login' from the string below
            $resetLink = $domain . "/login/reset_password.php?token=" . $token;

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'eggvelasco@gmail.com'; 
                $mail->Password   = 'kkvl aiue obbo yuau';    
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Recipients
                $mail->setFrom('no-reply@smartwash.com', 'SmartWash');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Reset Your Password - SmartWash';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                        <h2 style='color: #667eea;'>Password Reset Request</h2>
                        <p>We received a request to reset your password. Click the button below to proceed:</p>
                        <p>
                            <a href='$resetLink' style='background-color: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                        </p>
                        <p style='font-size: 12px; color: #777;'>If you did not request this, please ignore this email. Link expires in 1 hour.</p>
                    </div>
                ";
                $mail->AltBody = "Click here to reset: $resetLink";

                $mail->send();
                $message = "Reset link sent! Please check your email.";
                $msgType = "success";

            } catch (Exception $e) {
                $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                $msgType = "error";
            }
        } else {
            $message = "Database error. Please try again.";
            $msgType = "error";
        }
    } else {
        $message = "No account found with that email address.";
        $msgType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Forgot Password</title>
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
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Left Side */
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
        .login-left::before { content: ''; position: absolute; width: 300px; height: 300px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; top: -100px; right: -100px; }
        .login-left::after { content: ''; position: absolute; width: 200px; height: 200px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; bottom: -50px; left: -50px; }
        .logo-section { position: relative; z-index: 1; text-align: center; }
        .logo { font-size: 3rem; font-weight: bold; margin-bottom: 1rem; text-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); }
        .logo-icon { font-size: 5rem; margin-bottom: 1rem; animation: bounce 2s infinite; }
        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
        .tagline { font-size: 1.2rem; opacity: 0.95; line-height: 1.6; }

        /* Right Side */
        .login-right { padding: 3rem; display: flex; flex-direction: column; justify-content: center; }
        .login-header { margin-bottom: 2rem; }
        .login-header h2 { font-size: 2rem; color: #333; margin-bottom: 0.5rem; }
        .login-header p { color: #666; font-size: 0.95rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; font-size: 0.95rem; }
        .input-wrapper { position: relative; }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #999; font-size: 1.2rem; }
        .form-group input { width: 100%; padding: 1rem 1rem 1rem 3rem; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease; }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        
        .login-btn { width: 100%; padding: 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); }
        .login-btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        
        .signup-link { text-align: center; color: #666; font-size: 0.95rem; margin-top: 1.5rem; }
        .signup-link a { color: #667eea; text-decoration: none; font-weight: 600; transition: color 0.3s ease; }
        .signup-link a:hover { color: #764ba2; }
        
        .back-home { position: absolute; top: 2rem; left: 2rem; color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; transition: all 0.3s ease; z-index: 10; }
        .back-home:hover { transform: translateX(-5px); }

        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @media (max-width: 768px) {
            .login-container { grid-template-columns: 1fr; }
            .login-left { padding: 2rem; min-height: 250px; }
            .logo { font-size: 2rem; }
            .logo-icon { font-size: 3rem; }
            .tagline { font-size: 1rem; }
            .login-right { padding: 2rem; }
            .back-home { position: static; margin-bottom: 1rem; }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-home">← Back to Home</a>

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
                <h2>Forgot Password?</h2>
                <p>Enter your email address to reset your password</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $msgType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon"></span>
                        <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
                    </div>
                </div>

                <button type="submit" class="login-btn">Send Reset Link</button>
            </form>

            <div class="signup-link">
                Remember your password? <a href="login/login.php">Login here</a>
            </div>
        </div>
    </div>
</body>
</html>