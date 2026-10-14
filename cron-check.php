<?php
date_default_timezone_set('Asia/Colombo');

require 'db.php';

// Security key so unauthorized users cannot spam your cron endpoint
$secretKey = "CARE_SYNC_SECRET_CRON";
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die("Access Denied: Invalid Key");
}

$now = date('Y-m-d H:i:s');

// 1. Fetch pending tasks where schedule_time is due and email has not been sent
// Note: email_sent is checked as FALSE / IS NOT TRUE for PostgreSQL boolean compatibility
$query = "SELECT tasks.*, members.name AS member_name, users.email AS user_email 
          FROM tasks
          JOIN members ON tasks.member_id = members.id
          JOIN users ON members.user_id = users.id
          WHERE tasks.status = 'Pending' 
            AND tasks.schedule_time <= '$now' 
            AND (tasks.email_sent IS FALSE OR tasks.email_sent IS NULL)";

$result = $conn->query($query);

if (!$result) {
    die("Database Query Error: " . $conn->error);
}

$sentCount = 0;

while ($task = $result->fetch_assoc()) {
    $taskId = (int)$task['id'];
    $to = $task['user_email'];
    $subject = "Reminder: Time for " . $task['member_name'] . "'s " . $task['title'];
    
    $message = "Hello,\n\n"
             . "This is an automated reminder for " . $task['member_name'] . "'s scheduled task.\n\n"
             . "Task: " . $task['title'] . "\n"
             . "Type: " . $task['task_type'] . "\n"
             . "Scheduled Time: " . $task['schedule_time'] . "\n"
             . "Instructions: " . ($task['instructions'] ?: 'None') . "\n\n"
             . "Please mark it as done once completed:\n"
             . "https://caresync-38vp.onrender.com\n\n"
             . "- CareSync System";

    $headers = "From: no-reply@caresync-38vp.onrender.com\r\n" .
               "Reply-To: no-reply@caresync-38vp.onrender.com\r\n" .
               "X-Mailer: PHP/" . phpversion();

    // Send email using PHP mail() or your existing send-email.php logic/PHPMailer
    $mailSent = @mail($to, $subject, $message, $headers);

    if ($mailSent) {
        // 2. Set email_sent = TRUE in database
        $conn->query("UPDATE tasks SET email_sent = TRUE WHERE id = $taskId");
        $sentCount++;
    }
}

echo "Cron run completed. Tasks found: " . $result->num_rows . ", Emails dispatched: " . $sentCount;
?>