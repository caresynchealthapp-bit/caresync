<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
date_default_timezone_set('Asia/Colombo');

$current_timestamp = time();
$today_date_str    = date('Y-m-d', $current_timestamp);
$current_time_only = date('H:i', $current_timestamp);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dashboard_url = $protocol . "://" . $host . "/index.php";
$google_script_url = 'https://script.google.com/macros/s/AKfycbzqVsoKCLbtcREInR9yRx5M48bW6N1sztBi4Mvrqt_brj1Cshfuh6hq4raqJEkSO8y9/exec';

// Ensure stage tracking columns exist
@$conn->query("ALTER TABLE public.tasks ADD COLUMN IF NOT EXISTS reminder_stage_1 BOOLEAN DEFAULT FALSE");
@$conn->query("ALTER TABLE public.tasks ADD COLUMN IF NOT EXISTS reminder_stage_2 BOOLEAN DEFAULT FALSE");

// Fetch all pending tasks with unsent reminder stages
$sql = "SELECT t.*, m.name AS member_name, u.email AS user_email 
        FROM tasks t 
        JOIN members m ON t.member_id = m.id 
        JOIN users u ON m.user_id = u.id 
        WHERE t.status = 'Pending' 
          AND (t.reminder_stage_1 IS FALSE OR t.reminder_stage_2 IS FALSE)";

$result = $conn->query($sql);

$processed_any = false;
$sent_any = false;

if ($result) {
    while ($task = $result->fetch_assoc()) {
        $processed_any = true;
        $task_id       = (int)$task['id'];
        $task_type     = trim($task['task_type']);
        $sched_ts      = strtotime($task['schedule_time']);
        $sched_time_hm = date('h:i A', $sched_ts);

        $stage1_done = ($task['reminder_stage_1'] === true || $task['reminder_stage_1'] === 't' || $task['reminder_stage_1'] === 'true' || $task['reminder_stage_1'] === '1');
        $stage2_done = ($task['reminder_stage_2'] === true || $task['reminder_stage_2'] === 't' || $task['reminder_stage_2'] === 'true' || $task['reminder_stage_2'] === '1');

        $stage_to_send  = null;
        $subject_prefix = "";
        $notice_text    = "";

        $diff_seconds = $sched_ts - $current_timestamp;

        if ($task_type === 'Medicine') {
            // Stage 1: 5 minutes before medicine time
            if (!$stage1_done && $diff_seconds <= 300 && $diff_seconds > 0) {
                $stage_to_send  = 1;
                $subject_prefix = "Upcoming Medicine in 5 Minutes";
                $notice_text    = "This is a quick 5-minute reminder to prepare and take the prescribed medicine.";
            }
            // Stage 2: Exactly at or after medicine time
            elseif (!$stage2_done && $current_timestamp >= $sched_ts) {
                $stage_to_send  = 2;
                $subject_prefix = "Time to Take Medicine Now";
                $notice_text    = "It is now the scheduled time to take this medicine.";
            }
        } 
        elseif ($task_type === 'Clinic Date') {
            $yesterday_of_clinic = date('Y-m-d', strtotime('-1 day', $sched_ts));

            // Stage 1: 8:00 PM (20:00+) the day before clinic date
            if (!$stage1_done && $today_date_str === $yesterday_of_clinic && $current_time_only >= '20:00') {
                $stage_to_send  = 1;
                $subject_prefix = "Tomorrow's Clinic Appointment Reminder";
                $notice_text    = "Friendly reminder: You have a clinic appointment scheduled for tomorrow.";
            }
            // Stage 2: 1 hour before clinic appointment time
            elseif (!$stage2_done && $diff_seconds <= 3600 && $diff_seconds >= -1800) {
                $stage_to_send  = 2;
                $subject_prefix = "Clinic Appointment in 1 Hour";
                $notice_text    = "Your clinic appointment is in 1 hour. Please get ready.";
            }
        }

        if ($stage_to_send === null) {
            continue;
        }

        // Atomic stage lock
        $column_to_lock = "reminder_stage_" . $stage_to_send;
        $conn->query("UPDATE tasks SET $column_to_lock = TRUE, email_sent = TRUE WHERE id = $task_id");

        $to_email     = trim($task['user_email']);
        $member_name  = htmlspecialchars($task['member_name']);
        $title        = htmlspecialchars($task['title']);
        $instructions = !empty($task['instructions']) ? htmlspecialchars($task['instructions']) : 'None';
        $subject      = "CareSync Alert: {$subject_prefix} ({$member_name})";

        $htmlContent = "
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
                <div class='header'>💙 CareSync Reminder</div>
                <p>Hello,</p>
                <p>{$notice_text}</p>
                <div class='task-card'>
                    <h3 class='task-title'>{$title} ({$task_type})</h3>
                    <p class='task-detail'><strong>Member:</strong> {$member_name}</p>
                    <p class='task-detail'><strong>Scheduled For:</strong> " . date('M d, Y', $sched_ts) . " at {$sched_time_hm}</p>
                    <p class='task-detail'><strong>Instructions:</strong> {$instructions}</p>
                </div>
                <div class='btn-container'>
                    <a href='{$dashboard_url}' class='btn'>Open Dashboard</a>
                </div>
                <div class='footer'>Automated alert from your CareSync family health tracker.</div>
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

        if ($http_code == 200 || $http_code == 302) {
            $sent_any = true;
            echo "SUCCESS: Stage {$stage_to_send} ({$subject_prefix}) sent to {$to_email} for task #{$task_id}<br>";
        } else {
            $conn->query("UPDATE tasks SET $column_to_lock = FALSE WHERE id = $task_id");
            echo "FAILED: Task #{$task_id} HTTP Code {$http_code}. Response: {$response}<br>";
        }
    }
}

if (!$processed_any) {
    echo "No pending tasks with unsent reminders found.";
} elseif (!$sent_any) {
    echo "Checked pending tasks, but none are inside their trigger window right now.";
}
?>