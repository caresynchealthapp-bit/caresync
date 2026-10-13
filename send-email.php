<?php
require 'db.php';
date_default_timezone_set('Asia/Colombo');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// --- FIXED DATABASE QUERY: TARGET EXACT TASK ID FROM JAVASCRIPT ---
if (isset($_POST['task_id']) && is_numeric($_POST['task_id'])) {
    $target_id = (int)$_POST['task_id'];
    $sql = "SELECT t.*, m.name AS member_name, u.email AS user_email 
            FROM tasks t 
            JOIN members m ON t.member_id = m.id 
            JOIN users u ON m.user_id = u.id 
            WHERE t.id = $target_id 
              AND t.status = 'Pending' 
              AND t.email_sent = FALSE";
} else {
    // Fallback if the script is run manually in the browser
    $current_time = date('Y-m-d H:i:s');
    $sql = "SELECT t.*, m.name AS member_name, u.email AS user_email 
            FROM tasks t 
            JOIN members m ON t.member_id = m.id 
            JOIN users u ON m.user_id = u.id 
            WHERE t.schedule_time <= '$current_time' 
              AND t.status = 'Pending' 
              AND t.email_sent = FALSE";
}

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dashboard_url = "$protocol://$host/index.php";

    while ($task = $result->fetch_assoc()) {
        $to = $task['user_email'];
        $mail = new PHPMailer(true);

        try {
            // SMTP Settings for Port 587 (Bypasses Render's Port 465 Block)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'caresync.healthapp@gmail.com';
            $mail->Password   = 'oiteedxjxlraxfni'; // Your verified App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = 587;

            // Email Headers
            $mail->setFrom('caresync.healthapp@gmail.com', 'CareSync Alerts');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = "CareSync Alert: {$task['member_name']}'s Task is Due!";
            
            // HTML Body
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; color: #1e293b; padding: 20px; }
                    .container { max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
                    .header { font-size: 24px; color: #2563eb; font-weight: bold; text-align: center; margin-bottom: 25px; }
                    .task-card { background-color: #f1f5f9; border-left: 4px solid #2563eb; padding: 15px 20px; border-radius: 6px; margin-bottom: 25px; }
                    .task-title { font-size: 18px; font-weight: bold; margin: 0 0 8px 0; color: #1d4ed8; }
                    .task-detail { margin: 5px 0; font-size: 14px; color: #475569; }
                    .btn-container { text-align: center; margin-top: 30px; }
                    .btn { background-color: #2563eb; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; display: inline-block; }
                    .footer { margin-top: 30px; font-size: 12px; color: #94a3b8; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>💙 CareSync</div>
                    <p>Hello,</p>
                    <p>It's time for <strong>" . htmlspecialchars($task['member_name']) . "</strong>'s scheduled health task.</p>
                    <div class='task-card'>
                        <h3 class='task-title'>" . htmlspecialchars($task['title']) . "</h3>
                        <p class='task-detail'><strong>Instructions:</strong> " . (!empty($task['instructions']) ? htmlspecialchars($task['instructions']) : 'None') . "</p>
                        <p class='task-detail'><strong>Time:</strong> " . date('h:i A', strtotime($task['schedule_time'])) . "</p>
                    </div>
                    <div class='btn-container'>
                        <a href='{$dashboard_url}' class='btn'>Open Dashboard</a>
                    </div>
                    <div class='footer'>This is an automated alert from your CareSync family health tracker.</div>
                </div>
            </body>
            </html>";

            $mail->Body = $message;
            
            // Send the email
            $mail->send();

            // Update Database
            $task_id = (int)$task['id'];
            $conn->query("UPDATE tasks SET email_sent = TRUE WHERE id = $task_id");
            echo "HTML Email successfully delivered to $to<br>";

        } catch (Exception $e) {
            echo "Failed to send to $to: Mailer Error: {$mail->ErrorInfo}<br>";
        }
    }
} else {
    echo "No pending tasks are due right now.";
}
?>