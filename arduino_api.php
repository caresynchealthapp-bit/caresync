<?php
require_once 'db.php';
date_default_timezone_set('Asia/Colombo');

$current_time = date('Y-m-d H:i:s');
// Calculate 1 hour ahead in PHP instead of SQL
$clinic_trigger_time = date('Y-m-d H:i:s', strtotime('+1 hour'));

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

$sql = "SELECT tasks.id 
        FROM tasks 
        JOIN members ON tasks.member_id = members.id 
        WHERE members.user_id = '$user_id' 
        AND tasks.status = 'Pending' 
        AND (
            (tasks.task_type = 'Medicine' AND tasks.schedule_time <= '$current_time')
            OR 
            (tasks.task_type = 'Clinic Date' AND tasks.schedule_time <= '$clinic_trigger_time')
        )
        LIMIT 1";
        
$result = $conn->query($sql);

header('Content-Type: application/json');

if ($result && $result->num_rows > 0) {
    echo json_encode(["led_state" => "ON"]);
} else {
    echo json_encode(["led_state" => "OFF"]);
}
?>