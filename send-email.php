<?php
require 'db.php';
date_default_timezone_set('Asia/Colombo');

$current_time = date('Y-m-d H:i:s');

$sql = "SELECT t.*, m.name AS member_name, u.email AS user_email 
        FROM tasks t 
        JOIN members m ON t.member_id = m.id 
        JOIN users u ON m.user_id = u.id 
        WHERE t.schedule_time <= '$current_time' 
          AND t.status = 'Pending' 
          AND t.email_sent = 0";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($task = $result->fetch_assoc()) {
        $to = $task['user_email'];
        $subject = "CareSync Alert: {$task['member_name']}'s Task is Due!";
        
        // Build a beautiful HTML message
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
                <p>It's time for <strong>{$task['member_name']}</strong>'s scheduled health task.</p>
                
                <div class='task-card'>
                    <h3 class='task-title'>{$task['title']}</h3>
                    <p class='task-detail'><strong>Instructions:</strong> " . (!empty($task['instructions']) ? $task['instructions'] : 'None') . "</p>
                    <p class='task-detail'><strong>Time:</strong> " . date('h:i A', strtotime($task['schedule_time'])) . "</p>
                </div>
                
                <div class='btn-container'>
                    <a href='http://localhost/PHP/caresync/index.php' class='btn'>Open Dashboard</a>
                </div>
                
                <div class='footer'>
                    This is an automated alert from your CareSync family health tracker.
                </div>
            </div>
        </body>
        </html>
        ";
        
        // IMPORTANT: The headers must declare MIME-Version and text/html for the design to render
        // New Code
        $headers = "From: CareSync Alerts <caresync.healthapp@gmail.com>\r\n";
        $headers .= "Reply-To: CareSync Alerts <caresync.healthapp@gmail.com>\r\n";     
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        // Call the native mail function
        if (mail($to, $subject, $message, $headers)) {
            $task_id = (int)$task['id'];
            $conn->query("UPDATE tasks SET email_sent = 1 WHERE id = $task_id");
            echo "HTML Email successfully delivered!";
        } else {
            echo "Failed to send.";
        }
    }
} else {
    // ADD THIS:
    echo "No pending tasks are due right now.";
}
?>