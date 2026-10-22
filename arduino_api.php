<?php
require_once 'db.php';
date_default_timezone_set('Asia/Colombo'); // Ensures the time check matches your local time

$current_time = date('Y-m-d H:i:s'); // Captures the exact local time right now[cite: 3]

// Query for any tasks that have reached their schedule time but are still marked as 'Pending'[cite: 1]
$sql = "SELECT id FROM tasks WHERE schedule_time <= '$current_time' AND status = 'Pending' LIMIT 1";
$result = $conn->query($sql);

header('Content-Type: application/json');

// If a pending task's time has arrived or passed, command the LED to turn ON
if ($result && $result->num_rows > 0) {
    echo json_encode(["led_state" => "ON"]);
} else {
    echo json_encode(["led_state" => "OFF"]);
}
?>