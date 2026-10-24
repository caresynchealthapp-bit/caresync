<?php
session_set_cookie_params([
    'lifetime' => 31536000,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require 'db.php';

$error_message = "";
$success_message = "";

function is_valid_email_domain($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $domain = strtolower(substr(strrchr($email, "@"), 1));

    $blacklisted_domains = [
        'fake.com', 'test.com', 'example.com', 'tempmail.com', 
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 
        'throwawaymail.com', 'yopmail.com', 'sharklasers.com'
    ];

    if (in_array($domain, $blacklisted_domains)) {
        return false;
    }

    $fqdn = $domain . '.';
    return checkdnsrr($fqdn, 'MX') || checkdnsrr($fqdn, 'A');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } 
    elseif (!is_valid_email_domain($email)) {
        $error_message = "Email is incorrect or inactive. Please enter a real email address.";
    } 
    else {
        $safe_email = $conn->real_escape_string($email);

        $check_sql = "SELECT id FROM users WHERE email = '$safe_email'";
        $check_result = $conn->query($check_sql);

        if ($check_result && $check_result->num_rows > 0) {
            $error_message = "An account with this email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $safe_name = $conn->real_escape_string($name);

            $sql = "INSERT INTO users (name, email, password) VALUES ('$safe_name', '$safe_email', '$hashed_password')";
            $res = $conn->query($sql);
            
            if ($res) {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --cs-primary: #2563eb; --cs-teal: #0d9488; --cs-text-main: #0f172a; --cs-text-muted: #64748b; --cs-border: #e2e8f0; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: radial-gradient(circle at top right, #eff6ff 0%, #f8fafc 45%, #f0fdf4 100%); display: flex; justify-content: center; align-items: center; min-height: 100vh; color: var(--cs-text-main); padding: 20px;}
        
        .login-container { 
            background-color: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(12px);
            padding: 44px; 
            border-radius: 24px; 
            box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.1); 
            width: 100%; 
            max-width: 450px; 
            border: 1px solid rgba(255,255,255,0.6); 
        }
        .brand { 
            text-align: center; 
            font-size: 26px; 
            font-weight: 800; 
            margin-bottom: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px; 
            background: linear-gradient(135deg, #2563eb 0%, #0d9488 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .brand i {
            background: linear-gradient(135deg, #2563eb 0%, #0d9488 100%);
            color: #fff;
            -webkit-text-fill-color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .subtitle { text-align: center; color: var(--cs-text-muted); margin-bottom: 32px; font-size: 14.5px; font-weight: 500; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; font-size: 13.5px; color: #334155; }
        .form-group input { width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; outline: none; font-size: 14px; font-family: inherit; background: #f8fafc; transition: all 0.2s ease; }
        .form-group input:focus { border-color: var(--cs-primary); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); background: #fff; }
        
        .login-btn { 
            width: 100%; 
            padding: 14px; 
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); 
            color: #fff; 
            border: none; 
            border-radius: 12px; 
            font-size: 15px; 
            font-weight: 700; 
            cursor: pointer; 
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            margin-top: 10px;
            transition: all 0.2s ease;
        }
        .login-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35); }
        
        .error-message { color: #dc2626; font-size: 13.5px; font-weight: 600; text-align: center; margin-bottom: 20px; background-color: #fef2f2; padding: 12px; border-radius: 10px; border: 1px solid #fecaca; }
        
        .footer-link { text-align: center; margin-top: 24px; font-size: 14px; color: var(--cs-text-muted); font-weight: 500; }
        .footer-link a { color: var(--cs-primary); text-decoration: none; font-weight: 700; transition: color 0.2s; }
        .footer-link a:hover { color: #1e3a8a; }
        .cs-logo {
    height: 32px;
    width: 32px;
    border-radius: 8px;
    object-fit: cover;
    /* Add these rendering rules */
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
    transform: translateZ(0);
}
    </style>
    <link rel="icon" type="image/jpg" href="Gemini_Generated_Image_h1uw8kh1uw8kh1uw.jpg">
</head>
<body>

    <div class="login-container">
        <div class="brand">
            <img src="Firefly.png" alt="CareSync Logo" class="cs-logo"> CareSync
        </div>
        <div class="subtitle">Create a new account to manage your family's health</div>

        <form action="register.php" method="POST">
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="e.g. John Doe" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="email" name="email" placeholder="email@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
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