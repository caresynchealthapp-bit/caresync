<?php
require_once 'db.php';
date_default_timezone_set('Asia/Colombo');

$current_timestamp = time();
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

$sql = "SELECT tasks.task_type, tasks.schedule_time, tasks.clinic_early_minutes 
        FROM tasks 
        JOIN members ON tasks.member_id = members.id 
        WHERE members.user_id = '$user_id' 
        AND tasks.status = 'Pending'";
        
$result = $conn->query($sql);
$led_state = "OFF";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $task_ts = strtotime($row['schedule_time']);
        $task_type = $row['task_type'];
        $early_minutes = isset($row['clinic_early_minutes']) ? (int)$row['clinic_early_minutes'] : 60;
        
        if ($task_type === 'Medicine') {
            // Hardware: Trigger at exact medicine time
            if ($current_timestamp >= $task_ts) {
                $led_state = "ON";
                break; 
            }
        } elseif ($task_type === 'Clinic Date') {
            // Hardware: Trigger at user-requested offset time
            $trigger_ts = $task_ts - ($early_minutes * 60);
            if ($current_timestamp >= $trigger_ts) {
                $led_state = "ON";
                break; 
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode(["led_state" => $led_state]);
?>