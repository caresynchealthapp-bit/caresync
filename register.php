To implement Email OTP verification during user registration, we need to modify both the HTML form to capture the OTP and the PHP backend to generate, send, and verify it.

Here is the exact implementation to replace your current `register.php`:

```php
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

// Use the existing Google Apps Script endpoint to send emails
$google_script_url = 'https://script.google.com/macros/s/AKfycbzqVsoKCLbtcREInR9yRx5M48bW6N1sztBi4Mvrqt_brj1Cshfuh6hq4raqJEkSO8y9/exec';

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

function sendOtpEmail($to_email, $otp, $google_script_url) {
    $subject = "CareSync: Your Registration OTP";
    $htmlContent = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 20px; color: #1e293b;'>
        <div style='max-width: 500px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;'>
            <h2 style='color: #2563eb; margin-bottom: 20px;'>CareSync Registration</h2>
            <p style='font-size: 16px;'>Your One-Time Password (OTP) to complete registration is:</p>
            <h1 style='font-size: 36px; color: #0d9488; letter-spacing: 5px; margin: 20px 0;'>{$otp}</h1>
            <p style='font-size: 14px; color: #64748b;'>This code will expire in 15 minutes. Do not share it with anyone.</p>
        </div>
    </body>
    </html>";

    $payload = json_encode([
        'to'          => $to_email,
        'subject'     => $subject,
        'htmlContent' => $htmlContent
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $google_script_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code == 200 || $http_code == 302);
}

// STEP 1: Process initial registration and send OTP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_step1'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } elseif (!is_valid_email_domain($email)) {
        $error_message = "Email is incorrect or inactive. Please enter a real email address.";
    } else {
        $safe_email = $conn->real_escape_string($email);
        $check_sql = "SELECT id FROM users WHERE email = '$safe_email'";
        $check_result = $conn->query($check_sql);

        if ($check_result && $check_result->num_rows > 0) {
            $error_message = "An account with this email already exists.";
        } else {
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            
            // Store pending registration details in session securely
            $_SESSION['pending_reg'] = [
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'otp' => $otp,
                'otp_expires' => time() + (15 * 60) // 15 minutes validity
            ];

            // Send OTP Email
            if (sendOtpEmail($email, $otp, $google_script_url)) {
                $success_message = "OTP sent successfully! Please check your inbox.";
                $_SESSION['reg_step'] = 2; // Move to OTP verification UI
            } else {
                $error_message = "Failed to send OTP email. Please try again.";
                unset($_SESSION['pending_reg']);
            }
        }
    }
}

// STEP 2: Verify OTP and insert into Database
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp'] ?? '');
    
    if (!isset($_SESSION['pending_reg']) || time() > $_SESSION['pending_reg']['otp_expires']) {
        $error_message = "OTP has expired. Please restart the registration process.";
        unset($_SESSION['pending_reg']);
        $_SESSION['reg_step'] = 1;
    } elseif ($entered_otp !== $_SESSION['pending_reg']['otp']) {
        $error_message = "Incorrect OTP. Please try again.";
    } else {
        // OTP verified, create user
        $safe_name = $conn->real_escape_string($_SESSION['pending_reg']['name']);
        $safe_email = $conn->real_escape_string($_SESSION['pending_reg']['email']);
        $hashed_password = $_SESSION['pending_reg']['password_hash'];

        $sql = "INSERT INTO users (name, email, password) VALUES ('$safe_name', '$safe_email', '$hashed_password')";
        
        if ($conn->query($sql)) {
            unset($_SESSION['pending_reg']);
            unset($_SESSION['reg_step']);
            header("Location: login.php?registered=true");
            exit();
        } else {
            $error_message = "Database error. Please try again.";
        }
    }
}

// Handle Cancel/Back button from OTP screen
if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['pending_reg']);
    $_SESSION['reg_step'] = 1;
    header("Location: register.php");
    exit();
}

// Determine which step to show (1 = Initial Form, 2 = OTP Form)
$current_step = $_SESSION['reg_step'] ?? 1;
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
        .subtitle { text-align: center; color: var(--cs-text-muted); margin-bottom: 32px; font-size: 14.5px; font-weight: 500; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; font-size: 13.5px; color: #334155; }
        .form-group input { width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; outline: none; font-size: 14px; font-family: inherit; background: #f8fafc; transition: all 0.2s ease; text-align: left; }
        .form-group input:focus { border-color: var(--cs-primary); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); background: #fff; }
        
        .otp-input { text-align: center !important; font-size: 24px !important; letter-spacing: 8px; font-weight: 800; }

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
        
        .btn-secondary { background: #f1f5f9; color: #334155; box-shadow: none; margin-top: 10px; }
        .btn-secondary:hover { background: #e2e8f0; color: #0f172a; box-shadow: none; }

        .error-message { color: #dc2626; font-size: 13.5px; font-weight: 600; text-align: center; margin-bottom: 20px; background-color: #fef2f2; padding: 12px; border-radius: 10px; border: 1px solid #fecaca; }
        .success-message { color: #059669; font-size: 13.5px; font-weight: 600; text-align: center; margin-bottom: 20px; background-color: #ecfdf5; padding: 12px; border-radius: 10px; border: 1px solid #a7f3d0; }
        
        .footer-link { text-align: center; margin-top: 24px; font-size: 14px; color: var(--cs-text-muted); font-weight: 500; }
        .footer-link a { color: var(--cs-primary); text-decoration: none; font-weight: 700; transition: color 0.2s; }
        .footer-link a:hover { color: #1e3a8a; }
        .cs-logo { height: 32px; width: 32px; border-radius: 8px; object-fit: cover; image-rendering: crisp-edges; }
    </style>
    <link rel="icon" type="image/jpg" href="Gemini_Generated_Image_h1uw8kh1uw8kh1uw.jpg">
</head>
<body>

    <div class="login-container">
        <div class="brand">
            <img src="Firefly.png" alt="CareSync Logo" class="cs-logo"> CareSync
        </div>

        <?php if ($current_step == 1): ?>
            <!-- STEP 1: REGISTRATION FORM -->
            <div class="subtitle">Create a new account to manage your family's health</div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="e.g. John Doe" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="email@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create a strong password" required minlength="6">
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Type your password again" required minlength="6">
                </div>

                <button type="submit" name="register_step1" class="login-btn">Send OTP & Continue</button>
            </form>

            <div class="footer-link">
                Already have an account? <a href="login.php">Sign In here</a>
            </div>

        <?php else: ?>
            <!-- STEP 2: OTP VERIFICATION FORM -->
            <div class="subtitle">Enter the 6-digit code sent to <br><strong><?php echo htmlspecialchars($_SESSION['pending_reg']['email']); ?></strong></div>

            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label style="text-align: center;">One-Time Password (OTP)</label>
                    <input type="text" name="otp" class="otp-input" placeholder="000000" required maxlength="6" pattern="\d{6}" autofocus>
                </div>

                <button type="submit" name="verify_otp" class="login-btn">Verify Account</button>
                <a href="register.php?cancel_otp=true" style="text-decoration: none;">
                    <button type="button" class="login-btn btn-secondary">Change Email Address</button>
                </a>
            </form>
        <?php endif; ?>

    </div>

</body>
</html>

```