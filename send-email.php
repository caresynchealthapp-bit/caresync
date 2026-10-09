<?php
require 'db.php';
date_default_timezone_set('Asia/Colombo');

// --- 1. DIRECT GMAIL SMTP SENDER (NO EXTERNAL LIBRARIES NEEDED) ---
function send_smtp_email($to, $subject, $message_html, $from_email, $from_name, $app_password) {
    $timeout = 15;
    $socket = @fsockopen("ssl://smtp.gmail.com", 465, $errno, $errstr, $timeout);
    if (!$socket) {
        return "Failed to connect to Gmail SMTP: $errstr ($errno)";
    }

    $read = function() use ($socket) {
        $response = "";
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    };

    $write = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    $read(); // Server greeting

    $write("EHLO localhost");
    $read();

    $write("AUTH LOGIN");
    $read();

    $write(base64_encode($from_email));
    $read();

    $write(base64_encode($app_password));
    $auth_res = $read();
    if (strpos($auth_res, '235') === false) {
        $write("QUIT");
        fclose($socket);
        return "Authentication failed. Check your 16-character App Password. Server said: " . trim($auth_res);
    }

    $write("MAIL FROM: <$from_email>");
    $read();

    $write("RCPT TO: <$to>");
    $read();

    $write("DATA");
    $read();

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from_email>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $write($headers . "\r\n" . $message_html . "\r\n.");
    $data_res = $read();

    $write("QUIT");
    fclose($socket);

    if (strpos($data_res, '250') !== false) {
        return true;
    }
    return "Failed to send body: " . trim($data_res);
}

// --- 2. QUERY DUE TASKS ---
$current_time = date('Y-m-d H:i:s');

// Notice: Uses FALSE for PostgreSQL boolean compatibility
$sql = "SELECT t.*, m.name AS member_name, u.email AS user_email 
        FROM tasks t 
        JOIN members m ON t.member_id = m.id 
        JOIN users u ON m.user_id = u.id 
        WHERE t.schedule_time <= '$current_time' 
          AND t.status = 'Pending' 
          AND t.email_sent = FALSE";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    // Dynamic URL: Points to your live domain on Render, or localhost when offline
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dashboard_url = "$protocol://$host/index.php";

    // SMTP Credentials
    $sender_email = 'caresync.healthapp@gmail.com';
    // REPLACE THIS with your 16-character Google App Password (NOT your normal Gmail password)
    $app_password = 'oiteedxjxlraxfni'; 

    while ($task = $result->fetch_assoc()) {
        $to = $task['user_email'];
        $subject = "CareSync Alert: {$task['member_name']}'s Task is Due!";
        
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
                
                <div class='footer'>
                    This is an automated alert from your CareSync family health tracker.
                </div>
            </div>
        </body>
        </html>
        ";

        $status = send_smtp_email($to, $subject, $message, $sender_email, "CareSync Alerts", $app_password);

        if ($status === true) {
            $task_id = (int)$task['id'];
            $conn->query("UPDATE tasks SET email_sent = TRUE WHERE id = $task_id");
            echo "HTML Email successfully delivered to $to<br>";
        } else {
            echo "Failed to send to $to: $status<br>";
        }
    }
} else {
    echo "No pending tasks are due right now.";
}
?>