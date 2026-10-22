<?php
require_once 'db.php';
date_default_timezone_set('Asia/Colombo'); //[cite: 12]

$current_time = date('Y-m-d H:i:s'); //[cite: 12]

// Get the user_id from the Python script URL, default to 1 (Kavindi) if missing[cite: 13]
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

// Join tasks with members to accurately filter by user_id[cite: 10]
$sql = "SELECT tasks.id 
        FROM tasks 
        JOIN members ON tasks.member_id = members.id 
        WHERE members.user_id = '$user_id' 
        AND tasks.schedule_time <= '$current_time' 
        AND tasks.status = 'Pending' 
        LIMIT 1";
        
$result = $conn->query($sql);

header('Content-Type: application/json'); //[cite: 12]

if ($result && $result->num_rows > 0) { //[cite: 12]
    echo json_encode(["led_state" => "ON"]); //[cite: 12]
} else {
    echo json_encode(["led_state" => "OFF"]); //[cite: 12]
}
?>