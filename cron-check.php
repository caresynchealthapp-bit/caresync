<?php
date_default_timezone_set('Asia/Colombo');

require 'db.php';
require __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use GuzzleHttp\Client as GuzzleClient;

$secretKey = "CARE_SYNC_SECRET_CRON";
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die("Access Denied: Invalid Key");
}

$now = date('Y-m-d H:i:s');

// Initialize WebPush with your keys
$auth = [
    'VAPID' => [
        'subject'    => 'mailto:caresync.healthapp@gmail.com',
        'publicKey'  => 'BD73uAv-jsUeSLwDal94EqJmiOiVdfLD-O5-qFqUbQSGw0oEkb_Of7sNCT4R1W91ShPJtNw2SOeRM7FNNeaS3cg',
        'privateKey' => 'Qf4I3GW7NIDHhbO8A34LvxCyGvfWoEDXweQH2kksagY',
    ],
];

// Provide an explicit client instance with a 30-second timeout to avoid discovery errors
$webPush = new WebPush($auth);

$query = "SELECT tasks.*, members.name AS member_name, users.id AS user_id, users.email AS user_email 
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
    $userId = (int)$task['user_id'];

    // 1. Dispatch Email via Google Apps Script
    $_POST['task_id'] = $taskId;
    ob_start();
    include 'send-email.php';
    $output = ob_get_clean();

    // 2. Dispatch Background Web Push
    $subRes = $conn->query("SELECT * FROM push_subscriptions WHERE user_id = $userId");
    if ($subRes && $subRes->num_rows > 0) {
        while ($subRow = $subRes->fetch_assoc()) {
            $subscription = Subscription::create([
                'endpoint'  => $subRow['endpoint'],
                'publicKey' => $subRow['p256dh'],
                'authToken' => $subRow['auth'],
            ]);

            $payload = json_encode([
                'title' => "CareSync: Time for {$task['member_name']}'s Task",
                'body'  => "{$task['title']} - " . ($task['instructions'] ?: 'No instructions'),
                'url'   => '/index.php'
            ]);

            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                $endpoint = $conn->real_escape_string($report->getEndpoint());
                $conn->query("DELETE FROM push_subscriptions WHERE endpoint = '$endpoint'");
            }
        }
    }

    // 3. Mark task as sent
    $conn->query("UPDATE tasks SET email_sent = TRUE WHERE id = $taskId");
    $sentCount++;
}

echo "Cron run completed. Tasks found: " . $result->num_rows . ", Notifications dispatched: " . $sentCount;
?>