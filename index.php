<?php
session_start();
date_default_timezone_set('Asia/Colombo'); // Ensures the time check matches your local time

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$user_id = $_SESSION['user_id'];

// --- 1. HANDLE ADDING MEMBER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $relation = $conn->real_escape_string($_POST['relation']);
    $conn->query("INSERT INTO members (user_id, name, relation) VALUES ('$user_id', '$name', '$relation')");
    header("Location: index.php");
    exit();
}

// --- 2. HANDLE DELETING MEMBER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_member_id'])) {
    $delete_id = (int)$_POST['delete_member_id'];
    $conn->query("DELETE FROM members WHERE id = $delete_id AND user_id = '$user_id'");
    header("Location: index.php");
    exit();
}

// --- 3. HANDLE ADDING TASK ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_task'])) {
    $member_id = (int)$_POST['member_id'];
    $task_type = $conn->real_escape_string($_POST['task_type']);
    $title = $conn->real_escape_string($_POST['title']);
    $instructions = $conn->real_escape_string($_POST['instructions']);
    
    // Combine date and time
    $task_date = $conn->real_escape_string($_POST['task_date']);
    $task_time = $conn->real_escape_string($_POST['task_time']);
    $schedule_time = $task_date . ' ' . $task_time;
    
    $sql = "INSERT INTO tasks (member_id, task_type, title, instructions, schedule_time, status) 
            VALUES ($member_id, '$task_type', '$title', '$instructions', '$schedule_time', 'Pending')";
    $conn->query($sql);
    header("Location: index.php");
    exit();
}

// --- 4. HANDLE MARK AS DONE (UPDATED FOR LATE CHECK) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_done_id'])) {
    $task_id = (int)$_POST['mark_done_id'];
    
    // First, fetch the scheduled time of this specific task
    $check_sql = "SELECT schedule_time FROM tasks WHERE id=$task_id AND member_id IN (SELECT id FROM members WHERE user_id='$user_id')";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $scheduled_time = $row['schedule_time'];
        $current_time = date('Y-m-d H:i'); // Get the exact time right now
        
        // Compare times to determine if it's late
        if ($current_time > $scheduled_time) {
            $new_status = 'Done_Late';
        } else {
            $new_status = 'Done_OnTime';
        }
        
        // Update the task with the new accurate status
        $update_sql = "UPDATE tasks SET status='$new_status' WHERE id=$task_id";
        $conn->query($update_sql);
    }
    
    header("Location: index.php");
    exit();
}

// --- DATA FETCHING ---
$members_query = "SELECT * FROM members WHERE user_id = '$user_id'";
$members_result = $conn->query($members_query);

$tasks_query = "SELECT tasks.*, members.name AS member_name 
                FROM tasks 
                JOIN members ON tasks.member_id = members.id 
                WHERE members.user_id = '$user_id' 
                ORDER BY tasks.schedule_time ASC";
$all_tasks_result = $conn->query($tasks_query);

$today_str = date('Y-m-d');
$dashboard_today = [];
$dashboard_upcoming = [];
$member_tasks = [];

while($task = $all_tasks_result->fetch_assoc()) {
    $task_date = substr($task['schedule_time'], 0, 10);
    $member_tasks[$task['member_id']][] = $task;
    
    if ($task['status'] == 'Pending') {
        if ($task_date <= $today_str) {
            $dashboard_today[] = $task;
        } else {
            $dashboard_upcoming[] = $task;
        }
    }
}

function formatDateTime($datetime) {
    return date("M d, Y - h:i A", strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareSync | Family Health Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .form-card { background: var(--white); border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; }
        .btn-primary { background-color: var(--primary); color: white; padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    </style>
    <link rel="icon" type="image/jpg" href="Gemini_Generated_Image_h1uw8kh1uw8kh1uw.jpg">
</head>
<body>

    <aside class="sidebar">
        <div class="brand"><i class="fas fa-heartbeat"></i> CareSync</div>
        <ul class="nav-list" id="nav-list">
            <li class="nav-item active" onclick="switchView('dashboard', this, 'Family Overview')">
                <i class="fas fa-chart-line"></i> Main Dashboard
            </li>
            <div class="section-title">Family Members</div>
            <?php 
            $members_result->data_seek(0); 
            if ($members_result->num_rows > 0) {
                while($row = $members_result->fetch_assoc()) { 
                    echo "<li class='nav-item' onclick=\"switchView('member-{$row['id']}', this, '{$row['name']}\'s Profile')\">
                            <i class='fas fa-user-circle'></i> {$row['name']}
                          </li>";
                }
            } else {
                echo '<li style="padding: 12px 16px; color: #94a3b8; font-size: 13px;">No members added.</li>';
            }
            ?>
            <li class="nav-item" style="color: var(--primary); margin-top: 10px;" onclick="switchView('add-new-member', this, 'Add Family Member')">
                <i class="fas fa-plus-circle"></i> Add Member
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1 id="page-title">Family Overview</h1>
            <div class="user-controls">
                <div class="user-profile">
                    <span class="user-name"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
                </div>
            </div>
        </header>

        <div class="content-area">
            
            <!-- 1. DASHBOARD VIEW -->
            <div id="dashboard" class="view-section active">
                <p style="color: var(--text-gray); margin-bottom: 20px;">Manage upcoming medicines and clinics. Mark tasks as done to move them to the history log.</p>
                
                <div class="dashboard-grid">
                    
                    <!-- LEFT COLUMN: TODAY / URGENT -->
                    <div class="dash-column">
                        <h3><i class="fas fa-exclamation-circle text-danger"></i> Today's Schedule</h3>
                        <?php if (count($dashboard_today) > 0): ?>
                            <?php foreach($dashboard_today as $task): ?>
                                <div class="task-item" style="border-left: 4px solid var(--danger);">
                                    <div style="display: flex; justify-content: space-between;">
                                        <div>
                                            <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($task['member_name']); ?> - <?php echo htmlspecialchars($task['title']); ?></h4>
                                            <p style="font-size: 13px; color: var(--text-gray);"><i class="fas fa-clock"></i> <?php echo formatDateTime($task['schedule_time']); ?></p>
                                            <p style="font-size: 13px; margin-top: 5px; font-style: italic;"><?php echo htmlspecialchars($task['instructions']); ?></p>
                                        </div>
                                        <div>
                                            <form action="index.php" method="POST">
                                                <input type="hidden" name="mark_done_id" value="<?php echo $task['id']; ?>">
                                                <button type="submit" class="btn-success"><i class="fas fa-check"></i> Done</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state">No urgent tasks for today!</p>
                        <?php endif; ?>
                    </div>

                    <!-- RIGHT COLUMN: UPCOMING -->
                    <div class="dash-column">
                        <h3><i class="fas fa-calendar-alt text-warning"></i> Upcoming Later</h3>
                        <?php if (count($dashboard_upcoming) > 0): ?>
                            <?php foreach($dashboard_upcoming as $task): ?>
                                <div class="task-item" style="border-left: 4px solid var(--warning);">
                                    <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($task['member_name']); ?> - <?php echo htmlspecialchars($task['title']); ?></h4>
                                    <p style="font-size: 13px; color: var(--text-gray);"><i class="fas fa-clock"></i> <?php echo formatDateTime($task['schedule_time']); ?></p>
                                    <span class="tag upcoming" style="display: inline-block; margin-top: 8px;">Pending</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state">No upcoming tasks scheduled.</p>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- ADD MEMBER VIEW -->
            <div id="add-new-member" class="view-section">
                <h2>Add a New Family Member</h2>
                <div class="form-card" style="margin-top: 20px;">
                    <form action="index.php" method="POST">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" required placeholder="e.g. Sarah Connor">
                        </div>
                        <div class="form-group">
                            <label>Relationship</label>
                            <select name="relation" required>
                                <option value="Mother">Mother</option>
                                <option value="Father">Father</option>
                                <option value="Brother">Brother</option>
                                <option value="Sister">Sister</option>
                                <option value="Son">Son</option>
                                <option value="Daughter">Daughter</option>
                                <option value="Self">Self</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <button type="submit" name="add_member" class="btn-primary">Save Member</button>
                    </form>
                </div>
            </div>

            <!-- 2. MEMBER PROFILE VIEWS -->
            <?php 
            $members_result->data_seek(0); 
            while($row = $members_result->fetch_assoc()) { 
                $member_id = $row['id'];
                $member_name = htmlspecialchars($row['name']);
                $this_member_tasks = isset($member_tasks[$member_id]) ? $member_tasks[$member_id] : [];
            ?>
                <div id="member-<?php echo $member_id; ?>" class="view-section">
                    
                    <div class="member-profile-header">
                        <div>
                            <h2 style="margin-bottom: 5px;"><?php echo $member_name; ?>'s Profile</h2>
                            <span class="tag routine">Relation: <?php echo htmlspecialchars($row['relation']); ?></span>
                        </div>
                        <form action="index.php" method="POST" onsubmit="return confirm('Delete this member and all their data?');">
                            <input type="hidden" name="delete_member_id" value="<?php echo $member_id; ?>">
                            <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete Member</button>
                        </form>
                    </div>

                    <!-- PENDING AND DONE TABS -->
                    <div class="dashboard-grid" style="margin-bottom: 24px;">
                        
                        <!-- Member's Pending Tasks -->
                        <div class="dash-column">
                            <h3><i class="fas fa-hourglass-half text-warning"></i> Pending Tasks</h3>
                            <?php 
                            $has_pending = false;
                            foreach($this_member_tasks as $task) {
                                if($task['status'] == 'Pending') {
                                    $has_pending = true;
                                    echo "<div class='task-item'>";
                                    echo "<h4>".htmlspecialchars($task['title'])."</h4>";
                                    echo "<p style='font-size:13px; color:var(--text-gray);'><i class='fas fa-clock'></i> ".formatDateTime($task['schedule_time'])."</p>";
                                    echo "<p style='font-size:13px; margin-top:5px;'>".htmlspecialchars($task['instructions'])."</p>";
                                    echo "</div>";
                                }
                            }
                            if(!$has_pending) echo "<p class='empty-state'>No pending tasks.</p>";
                            ?>
                        </div>

                        <!-- Member's History Log (UPDATED FOR LATE TRACKING) -->
                        <div class="dash-column">
                            <h3><i class="fas fa-history text-success"></i> History Log</h3>
                            <?php 
                            $has_done = false;
                            foreach($this_member_tasks as $task) {
                                // Check if it's ANY type of "Done" status
                                if(strpos($task['status'], 'Done') !== false) {
                                    $has_done = true;
                                    echo "<div class='task-item task-done'>";
                                    echo "<h4>".htmlspecialchars($task['title'])."</h4>";
                                    
                                    if ($task['status'] == 'Done_Late') {
                                        // Completed Late (Red)
                                        echo "<p style='font-size:13px; color: var(--danger); font-weight: 500;'><i class='fas fa-exclamation-triangle'></i> Completed late</p>";
                                    } else if ($task['status'] == 'Done_OnTime') {
                                        // Completed On Time (Green)
                                        echo "<p style='font-size:13px; color: var(--success); font-weight: 500;'><i class='fas fa-check-circle'></i> Completed on time</p>";
                                    } else {
                                        // Older tasks just marked "Done"
                                        echo "<p style='font-size:13px; color: var(--text-gray);'><i class='fas fa-check-circle'></i> Completed</p>";
                                    }
                                    
                                    echo "</div>";
                                }
                            }
                            if(!$has_done) echo "<p class='empty-state'>No completed history yet.</p>";
                            ?>
                        </div>

                    </div>

                    <!-- FORM TO ADD TASK -->
                    <div class="form-card">
                        <h3 style="margin-bottom: 15px;"><i class="fas fa-plus"></i> Schedule Medicine / Clinic</h3>
                        <form action="index.php" method="POST">
                            <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                            
                            <div class="two-col">
                                <div class="form-group">
                                    <label>Task Type</label>
                                    <select name="task_type" required>
                                        <option value="Medicine">Medicine</option>
                                        <option value="Clinic Date">Clinic Date</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Name / Title</label>
                                    <input type="text" name="title" required placeholder="e.g. Metformin 500mg">
                                </div>
                            </div>
                            
                            <div class="two-col">
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" name="task_date" required>
                                </div>
                                <div class="form-group">
                                    <label>Time</label>
                                    <input type="time" name="task_time" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Instructions (Optional)</label>
                                <input type="text" name="instructions" placeholder="e.g. Take after dinner with water">
                            </div>
                            
                            <button type="submit" name="add_task" class="btn-primary">Add to Schedule</button>
                        </form>
                    </div>
                </div>
            <?php } ?>

        </div>
    </main>
    <div id="toast-container" class="toast-container"></div>

    <script>
        // Pass the "Today's Tasks" from PHP directly into a JavaScript variable!
        const todayTasks = <?php echo json_encode($dashboard_today); ?>;
    </script>

    <script src="script.js"></script>
</body>
</html>