<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid payload"]);
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$endpoint = $conn->real_escape_string($data['endpoint']);
$p256dh   = $conn->real_escape_string($data['keys']['p256dh']);
$auth     = $conn->real_escape_string($data['keys']['auth']);

// Remove old/expired tokens for this user so only the current device is kept
$conn->query("DELETE FROM push_subscriptions WHERE user_id = $user_id OR endpoint = '$endpoint'");

// Insert the single active subscription
$conn->query("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES ($user_id, '$endpoint', '$p256dh', '$auth')");

echo json_encode(["status" => "success"]);
?>