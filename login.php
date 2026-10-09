<?php
session_start();
require 'db.php';

$error_message = "";
$success_message = "";

// Check if they just came from a successful registration
if (isset($_GET['registered']) && $_GET['registered'] == 'true') {
    $success_message = "Account created successfully! You can now log in.";
}

if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            
            header("Location: index.php");
            exit();
        } else {
            $error_message = "Incorrect password.";
        }
    } else {
        $error_message = "No account found with this email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CareSync</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2563eb; --primary-dark: #1d4ed8; --text-dark: #1e293b; --text-gray: #64748b; --bg-gray: #f8fafc; --white: #ffffff; --border: #e2e8f0; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background-color: var(--bg-gray); display: flex; justify-content: center; align-items: center; height: 100vh; color: var(--text-dark); }
        .login-container { background-color: var(--white); padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); width: 100%; max-width: 400px; border: 1px solid var(--border); }
        .brand { text-align: center; font-size: 28px; font-weight: bold; color: var(--primary); margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .subtitle { text-align: center; color: var(--text-gray); margin-bottom: 32px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; outline: none; font-size: 14px; }
        .form-group input:focus { border-color: var(--primary); }
        .login-btn { width: 100%; padding: 12px; background-color: var(--primary); color: var(--white); border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .login-btn:hover { background-color: var(--primary-dark); }
        .error-message { color: #ef4444; font-size: 13px; text-align: center; margin-bottom: 16px; background-color: #fee2e2; padding: 10px; border-radius: 6px;}
        .success-message { color: #059669; font-size: 13px; text-align: center; margin-bottom: 16px; background-color: #d1fae5; padding: 10px; border-radius: 6px;}
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
        <div class="subtitle">Log in to manage your family's health</div>

        <form action="login.php" method="POST">
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Show success message if they just registered -->
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="admin@family.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" name="login" class="login-btn">Sign In</button>
        </form>

        <div class="footer-link">
            Don't have an account? <a href="register.php">Sign Up here</a>
        </div>
    </div>

</body>
</html>