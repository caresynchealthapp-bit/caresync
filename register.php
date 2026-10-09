<?php
session_start();
require 'db.php';

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Check if passwords match
    if ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } else {
        // 2. Check if the email already exists in the database
        $check_sql = "SELECT id FROM users WHERE email = '$email'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            $error_message = "An account with this email already exists.";
        } else {
            // 3. Encrypt the password securely
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // [FUTURE UPGRADE ROOM]: Here is where you would generate a random token
            // and send a verification email using PHPMailer before inserting them as "active".
            // For now, we will just register them directly.

            // 4. Insert the new user into the database
            $sql = "INSERT INTO users (name, email, password) VALUES ('$name', '$email', '$hashed_password')";
            
            if ($conn->query($sql) === TRUE) {
                // Registration successful, send them to login page with a success flag
                header("Location: login.php?registered=true");
                exit();
            } else {
                $error_message = "Something went wrong. Please try again.";
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
    <title>Register | CareSync</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2563eb; --primary-dark: #1d4ed8; --text-dark: #1e293b; --text-gray: #64748b; --bg-gray: #f8fafc; --white: #ffffff; --border: #e2e8f0; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background-color: var(--bg-gray); display: flex; justify-content: center; align-items: center; min-height: 100vh; color: var(--text-dark); padding: 20px;}
        .login-container { background-color: var(--white); padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); width: 100%; max-width: 450px; border: 1px solid var(--border); }
        .brand { text-align: center; font-size: 28px; font-weight: bold; color: var(--primary); margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .subtitle { text-align: center; color: var(--text-gray); margin-bottom: 24px; font-size: 14px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; outline: none; font-size: 14px; }
        .form-group input:focus { border-color: var(--primary); }
        .login-btn { width: 100%; padding: 12px; background-color: var(--primary); color: var(--white); border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px;}
        .login-btn:hover { background-color: var(--primary-dark); }
        .error-message { color: #ef4444; font-size: 13px; text-align: center; margin-bottom: 16px; background-color: #fee2e2; padding: 10px; border-radius: 6px;}
        .footer-link { text-align: center; margin-top: 20px; font-size: 14px; color: var(--text-gray); }
        .footer-link a { color: var(--primary); text-decoration: none; font-weight: bold; }
        .footer-link a:hover { text-decoration: underline; }
    </style>
    <link rel="icon" type="image/jpg" href="Gemini_Generated_Image_h1uw8kh1uw8kh1uw.jpg">
</head>
<body>

    <div class="login-container">
        <div class="brand">
            <i class="fas fa-heartbeat"></i> CareSync
        </div>
        <div class="subtitle">Create a new account to manage your family's health</div>

        <form action="register.php" method="POST">
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="e.g. John Doe" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="email@example.com" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a strong password" required minlength="6">
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Type your password again" required minlength="6">
            </div>

            <button type="submit" name="register" class="login-btn">Create Account</button>
        </form>

        <div class="footer-link">
            Already have an account? <a href="login.php">Sign In here</a>
        </div>
    </div>

</body>
</html>