<?php

session_set_cookie_params([
    'lifetime' => 31536000,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

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

// --- 4. HANDLE MARK AS DONE (FIXED FOR POSTGRESQL) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_done_id'])) {
    $task_id = (int)$_POST['mark_done_id'];
    
    // First, fetch the scheduled time of this specific task directly
    $check_sql = "SELECT schedule_time FROM tasks WHERE id=$task_id AND member_id IN (SELECT id FROM members WHERE user_id='$user_id') LIMIT 1";
    $result = $conn->query($check_sql);
    $row = ($result) ? $result->fetch_assoc() : null;
    
    if ($row) {
        $scheduled_time = $row['schedule_time'];
        $current_time = date('Y-m-d H:i'); 
        
        // Compare times to determine if it's late
        $new_status = ($current_time > $scheduled_time) ? 'Done_Late' : 'Done_OnTime';
        
        // Update the task with the new accurate status
        $conn->query("UPDATE tasks SET status='$new_status' WHERE id=$task_id");
    }
    
    header("Location: index.php");
    exit();
}

// --- DATA FETCHING ---
// 1. Fetch Members reliably into an array
$members_list = [];
$members_res = $conn->query("SELECT * FROM members WHERE user_id = '$user_id' ORDER BY id ASC");
if ($members_res) {
    while ($m = $members_res->fetch_assoc()) {
        $members_list[] = $m;
    }
}

// 2. Fetch Tasks
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

if ($all_tasks_result) {
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
}

// 3. Fetch Medicines from PharmaConnect Supabase Project
$medicines_list = [];
$target_conn = (isset($pharma_conn) && $pharma_conn) ? $pharma_conn : $conn;

$med_query = $target_conn->query("SELECT DISTINCT name FROM public.medicines ORDER BY name ASC");
if ($med_query) {
    while ($med_row = $med_query->fetch_assoc()) {
        if (!empty($med_row['name'])) {
            $medicines_list[] = $med_row['name'];
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
    <!-- Supabase JS SDK for live PharmaConnect Stock & Geolocation Queries -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <link rel="stylesheet" href="style.css?v=3">
    <style>
        .form-card { background: var(--white); border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; position: relative; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; }
        .btn-primary { background-color: var(--primary); color: white; padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* Autocomplete dropdown styles */
        .med-suggestions-box {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            max-height: 220px;
            overflow-y: auto;
            z-index: 9999;
            margin-top: 4px;
        }
        .med-suggestion-item {
            padding: 10px 14px;
            font-size: 14px;
            color: #1e293b;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s ease;
        }
        .med-suggestion-item:last-child { border-bottom: none; }
        .med-suggestion-item:hover {
            background-color: #eff6ff;
            color: var(--primary);
            font-weight: 600;
        }
        .med-suggestion-empty {
            padding: 12px 14px;
            font-size: 13px;
            color: #94a3b8;
            font-style: italic;
        }

        /* User ID Pill Styles */
        .user-id-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            color: #475569;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 12px;
        }
        .user-id-badge:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Nearest Pharmacies Modal Styles */
        .pharma-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 16px;
        }
        .pharma-modal-backdrop.active {
            display: flex;
        }
        .pharma-modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 620px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: modalPop 0.2s ease-out;
        }
        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .pharma-modal-header {
            padding: 18px 22px;
            background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pharma-modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pharma-modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pharma-modal-body {
            padding: 20px 22px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .pharma-result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 12px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .pharma-result-item:hover {
            border-color: #0d9488;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.08);
        }
        .pharma-rank-badge {
            display: inline-block;
            background: #ccfbf1;
            color: #0f766e;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            margin-bottom: 4px;
        }
        .pharma-stock-pill {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 6px;
            margin-top: 6px;
        }
        .btn-directions {
            background: #0d9488;
            color: #ffffff;
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s ease;
        }
        .btn-directions:hover {
            background: #0f766e;
        }
    </style>
    <link rel="icon" type="image/jpg" href="Gemini_Generated_Image_h1uw8kh1uw8kh1uw.jpg">
    <link rel="manifest" href="manifest.json">
</head>
<body>

    <!-- Mobile Top Header Bar -->
    <header class="mobile-topbar">
        <button id="menu-toggle-btn" class="menu-btn" aria-label="Toggle Navigation" onclick="toggleMobileMenu(event)">
            <i class="fas fa-bars"></i>
        </button>
        <div class="brand"><i class="fas fa-heartbeat"></i> CareSync</div>
        <button id="request-notif-btn" class="mobile-bell-btn" title="Enable System Notifications">
            <i class="fas fa-bell"></i>
        </button>
    </header>

    <!-- Overlay backdrop when menu is open on mobile -->
    <div id="sidebar-overlay" class="sidebar-overlay"></div>

    <aside class="sidebar" id="app-sidebar">
        <div class="brand desktop-brand"><i class="fas fa-heartbeat"></i> CareSync</div>
        <ul class="nav-list" id="nav-list">
            <li class="nav-item active" onclick="switchView('dashboard', this, 'Family Overview'); closeMobileMenu();">
                <i class="fas fa-chart-line"></i> Main Dashboard
            </li>
            <div class="section-title">Family Members</div>
            <?php 
            if (count($members_list) > 0) {
                foreach($members_list as $row) { 
                    echo "<li class='nav-item' onclick=\"switchView('member-{$row['id']}', this, '" . addslashes($row['name']) . "\'s Profile'); closeMobileMenu();\">
                            <i class='fas fa-user-circle'></i> " . htmlspecialchars($row['name']) . "
                          </li>";
                }
            } else {
                echo '<li style="padding: 12px 16px; color: #94a3b8; font-size: 13px;">No members added.</li>';
            }
            ?>
            <li class="nav-item" style="color: var(--primary); margin-top: 10px;" onclick="switchView('add-new-member', this, 'Add Family Member'); closeMobileMenu();">
                <i class="fas fa-plus-circle"></i> Add Member
            </li>
        </ul>

        <!-- Sidebar User ID Footer Box -->
        <div style="margin-top: auto; padding: 14px 16px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b;">
            <div style="font-weight: 600; color: #334155; margin-bottom: 4px;"><i class="fas fa-id-badge"></i> Your Account ID</div>
            <div style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 6px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <code style="color: #0f172a; font-weight: 700; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($user_id); ?></code>
                <button type="button" onclick="copyUserId('<?php echo addslashes($user_id); ?>')" style="background: none; border: none; color: var(--primary); cursor: pointer; font-size: 13px;" title="Copy User ID">
                    <i class="far fa-copy"></i>
                </button>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1 id="page-title">Family Overview</h1>
            <div class="user-controls" style="display: flex; align-items: center;">
                <!-- Clickable User ID Badge in Header -->
                <div class="user-id-badge" onclick="copyUserId('<?php echo addslashes($user_id); ?>')" title="Click to copy your User ID">
                    <i class="fas fa-fingerprint"></i>
                    <span>ID: <?php echo htmlspecialchars($user_id); ?></span>
                    <i class="far fa-copy" style="font-size: 11px; opacity: 0.7;"></i>
                </div>

                <button id="desktop-bell-btn" class="notification-btn" style="background:none; border:none; font-size:22px; color:var(--text-gray); margin-right:20px; cursor:pointer;" title="Enable Notifications">
                    <i class="fas fa-bell"></i>
                </button>
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
                            <?php foreach($dashboard_today as $task): 
                                $raw_note = trim($task['instructions'] ?? '');
                                $has_valid_note = (!empty($raw_note) && strtolower($raw_note) !== 'none');
                            ?>
                                <div class="task-item-modern">
                                    <div class="task-info-group">
                                        <div class="task-title-row">
                                            <span class="member-badge"><i class="fas fa-user"></i> <?php echo htmlspecialchars($task['member_name']); ?></span>
                                            <h4 class="task-main-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                        </div>
                                        <p class="task-meta-time">
                                            <i class="far fa-clock"></i> <?php echo formatDateTime($task['schedule_time']); ?>
                                        </p>
                                        <?php if ($has_valid_note): ?>
                                            <p class="task-meta-note"><?php echo htmlspecialchars($raw_note); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <form action="index.php" method="POST" class="task-actions-group">
                                        <input type="hidden" name="mark_done_id" value="<?php echo $task['id']; ?>">
                                        <button type="submit" class="btn-action-done">
                                            <i class="fas fa-check"></i> Done
                                        </button>
                                        <?php if ($task['task_type'] === 'Medicine'): ?>
                                            <button type="button" class="btn-action-find find-med-btn" onclick="findNearestPharmacies('<?php echo addslashes($task['title']); ?>')">
                                                <i class="fas fa-search-location"></i> Find Medicine
                                            </button>
                                        <?php endif; ?>
                                    </form>
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
                                <div class="task-item" style="border-left: 4px solid var(--warning); display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($task['member_name']); ?> - <?php echo htmlspecialchars($task['title']); ?></h4>
                                        <p style="font-size: 13px; color: var(--text-gray);"><i class="fas fa-clock"></i> <?php echo formatDateTime($task['schedule_time']); ?></p>
                                        <span class="tag upcoming" style="display: inline-block; margin-top: 8px;">Pending</span>
                                    </div>
                                    <?php if ($task['task_type'] === 'Medicine'): ?>
                                        <button type="button" class="btn-action-find find-med-btn" onclick="findNearestPharmacies('<?php echo addslashes($task['title']); ?>')">
                                            <i class="fas fa-search-location"></i> Find Medicine
                                        </button>
                                    <?php endif; ?>
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
            foreach($members_list as $row) { 
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
                                    echo "<div class='task-item' style='display:flex; justify-content:space-between; align-items:center;'>";
                                    echo "<div>";
                                    echo "<h4>".htmlspecialchars($task['title'])."</h4>";
                                    echo "<p style='font-size:13px; color:var(--text-gray);'><i class='fas fa-clock'></i> ".formatDateTime($task['schedule_time'])."</p>";
                                    echo "<p style='font-size:13px; margin-top:5px;'>".htmlspecialchars($task['instructions'])."</p>";
                                    echo "</div>";
                                    if ($task['task_type'] === 'Medicine') {
                                        $safeTitle = addslashes($task['title']);
                                        echo "<button type='button' class='btn-action-find find-med-btn' onclick=\"findNearestPharmacies('{$safeTitle}')\"><i class='fas fa-search-location'></i> Find Medicine</button>";
                                    }
                                    echo "</div>";
                                }
                            }
                            if(!$has_pending) echo "<p class='empty-state'>No pending tasks.</p>";
                            ?>
                        </div>

                        <!-- Member's History Log -->
                        <div class="dash-column">
                            <h3><i class="fas fa-history text-success"></i> History Log</h3>
                            <?php 
                            $has_done = false;
                            foreach($this_member_tasks as $task) {
                                if(strpos($task['status'], 'Done') !== false) {
                                    $has_done = true;
                                    echo "<div class='task-item task-done'>";
                                    echo "<h4>".htmlspecialchars($task['title'])."</h4>";
                                    
                                    if ($task['status'] == 'Done_Late') {
                                        echo "<p style='font-size:13px; color: var(--danger); font-weight: 500;'><i class='fas fa-exclamation-triangle'></i> Completed late</p>";
                                    } else if ($task['status'] == 'Done_OnTime') {
                                        echo "<p style='font-size:13px; color: var(--success); font-weight: 500;'><i class='fas fa-check-circle'></i> Completed on time</p>";
                                    } else {
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
                                    <select name="task_type" class="task-type-select" onchange="handleTaskTypeChange(this)" required>
                                        <option value="Medicine">Medicine</option>
                                        <option value="Clinic Date">Clinic Date</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="title-label">Name / Title</label>
                                    <div style="display: flex; gap: 8px;">
                                        <input type="text" 
                                               name="title" 
                                               class="task-title-input" 
                                               required 
                                               placeholder="e.g. Metformin 500mg" 
                                               autocomplete="off"
                                               oninput="handleMedicineInput(this)">
                                        <button type="button" class="btn-action-find find-med-btn" style="white-space: nowrap;" onclick="findNearestPharmacies(this.previousElementSibling.value)">
                                            <i class="fas fa-search-location"></i> Find
                                        </button>
                                    </div>
                                    <!-- Suggestions Container -->
                                    <div class="med-suggestions-box" style="display: none;"></div>
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

    <!-- NEAREST PHARMACIES RESULTS MODAL -->
    <div id="pharma-finder-modal" class="pharma-modal-backdrop" onclick="if(event.target === this) closePharmaModal()">
        <div class="pharma-modal-card">
            <div class="pharma-modal-header">
                <h3 id="pharma-modal-title"><i class="fas fa-mortar-pestle"></i> Nearest Pharmacies</h3>
                <button type="button" class="pharma-modal-close" onclick="closePharmaModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="pharma-modal-body" class="pharma-modal-body">
                <!-- Populated dynamically via JS -->
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>

    <script>
        const todayTasks = <?php echo json_encode($dashboard_today); ?>;
        const availableMedicines = <?php echo json_encode($medicines_list); ?>;

        // PharmaConnect Supabase Configuration
        const PHARMA_SUPABASE_URL = 'https://juobkaftyrzlxbviudpy.supabase.co';
        const PHARMA_SUPABASE_KEY = 'sb_publishable_qdME8AYk9rz5B4FHs4Eb1g_0vvhN73v';
        const pharmaSupabase = window.supabase.createClient(PHARMA_SUPABASE_URL, PHARMA_SUPABASE_KEY);

        // Copy User ID helper
        function copyUserId(uid) {
            navigator.clipboard.writeText(uid).then(() => {
                alert('Your User ID (' + uid + ') has been copied to your clipboard!');
            }).catch(() => {
                prompt('Copy your User ID:', uid);
            });
        }

        // 1. Dynamic placeholder and button toggle
        function handleTaskTypeChange(selectElem) {
            const formCard = selectElem.closest('.form-card');
            const labelElem = formCard.querySelector('.title-label');
            const inputElem = formCard.querySelector('.task-title-input');
            const findBtn = formCard.querySelector('.find-med-btn');
            const box = formCard.querySelector('.med-suggestions-box');

            if (selectElem.value === 'Clinic Date') {
                labelElem.innerText = 'Clinic Name';
                inputElem.placeholder = 'e.g. Asiri Central Hospital Clinic';
                if (findBtn) findBtn.style.display = 'none';
                if (box) box.style.display = 'none';
            } else {
                labelElem.innerText = 'Name / Title';
                inputElem.placeholder = 'e.g. Metformin 500mg';
                if (findBtn) findBtn.style.display = 'inline-block';
            }
        }

        // 2. Autocomplete filtering based on prefix
        function handleMedicineInput(inputElem) {
            const formCard = inputElem.closest('.form-card');
            const selectElem = formCard.querySelector('.task-type-select');
            const box = formCard.querySelector('.med-suggestions-box');

            if (selectElem.value !== 'Medicine') {
                box.style.display = 'none';
                return;
            }

            const query = inputElem.value.trim().toLowerCase();
            if (query.length === 0) {
                box.style.display = 'none';
                return;
            }

            // Prefix matching first, then fuzzy match
            const matches = availableMedicines.filter(med => 
                med.toLowerCase().startsWith(query) || med.toLowerCase().includes(query)
            );

            box.innerHTML = '';
            if (matches.length > 0) {
                matches.forEach(med => {
                    const item = document.createElement('div');
                    item.className = 'med-suggestion-item';
                    item.innerText = med;
                    item.onmousedown = function () {
                        inputElem.value = med;
                        box.style.display = 'none';
                    };
                    box.appendChild(item);
                });
            } else {
                const empty = document.createElement('div');
                empty.className = 'med-suggestion-empty';
                empty.innerText = 'No such medicine found';
                box.appendChild(empty);
            }

            box.style.display = 'block';
        }

        // 3. Find Nearest 5 Pharmacies with Stock from PharmaConnect
        function findNearestPharmacies(medicineName) {
            const cleanMed = (medicineName || '').trim();
            if (!cleanMed) {
                alert('Please enter or select a medicine name first.');
                return;
            }

            const modal = document.getElementById('pharma-finder-modal');
            const titleEl = document.getElementById('pharma-modal-title');
            const bodyEl = document.getElementById('pharma-modal-body');

            titleEl.innerHTML = `<i class="fas fa-mortar-pestle"></i> Finding "${escapeHtml(cleanMed)}"`;
            bodyEl.innerHTML = `
                <div style="text-align: center; padding: 30px 10px; color: #64748b;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 28px; color: #0d9488; margin-bottom: 12px;"></i>
                    <p style="margin: 0; font-weight: 600; color: #1e293b;">Acquiring your GPS coordinates & scanning live pharmacy inventory...</p>
                </div>
            `;
            modal.classList.add('active');

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => queryPharmaConnectStock(cleanMed, pos.coords.latitude, pos.coords.longitude, true),
                    () => queryPharmaConnectStock(cleanMed, 6.7951, 79.9009, false), // Default fallback: Moratuwa
                    { enableHighAccuracy: true, timeout: 7000 }
                );
            } else {
                queryPharmaConnectStock(cleanMed, 6.7951, 79.9009, false);
            }
        }

        async function queryPharmaConnectStock(medicineName, userLat, userLng, gpsLocked) {
            const bodyEl = document.getElementById('pharma-modal-body');
            const todayStr = new Date().toISOString().split('T')[0];

            try {
                // 1. Query active, non-expired stock in PharmaConnect's grn_stock table
                const { data: stockRows, error: stockErr } = await pharmaSupabase
                    .from('grn_stock')
                    .select('pharmacy_id, medicine_name, current_quantity, selling_price, expiry_date')
                    .ilike('medicine_name', `%${medicineName}%`)
                    .gt('current_quantity', 0)
                    .gt('expiry_date', todayStr);

                if (stockErr) throw stockErr;

                if (!stockRows || stockRows.length === 0) {
                    bodyEl.innerHTML = `
                        <div style="text-align: center; padding: 28px 16px; color: #64748b;">
                            <i class="fas fa-box-open" style="font-size: 36px; color: #94a3b8; margin-bottom: 10px;"></i>
                            <h4 style="color: #1e293b; margin: 0 0 6px 0;">Out of Stock Nearby</h4>
                            <p style="font-size: 13px; margin: 0;">No registered PharmaConnect pharmacies currently have active, non-expired stock for <strong>${escapeHtml(medicineName)}</strong>.</p>
                        </div>
                    `;
                    return;
                }

                // 2. Group available stock by pharmacy_id (sum units and find lowest MRP)
                const stockByPharmacy = {};
                stockRows.forEach(row => {
                    const pid = row.pharmacy_id;
                    if (!stockByPharmacy[pid]) {
                        stockByPharmacy[pid] = {
                            totalUnits: 0,
                            minPrice: Number(row.selling_price),
                            matchedName: row.medicine_name
                        };
                    }
                    stockByPharmacy[pid].totalUnits += Number(row.current_quantity || 0);
                    if (Number(row.selling_price) < stockByPharmacy[pid].minPrice) {
                        stockByPharmacy[pid].minPrice = Number(row.selling_price);
                    }
                });

                const pharmacyIds = Object.keys(stockByPharmacy);

                // 3. Fetch pharmacy coordinates and contact info
                const { data: pharmacies, error: pharmErr } = await pharmaSupabase
                    .from('pharmacies')
                    .select('*')
                    .in('id', pharmacyIds);

                if (pharmErr) throw pharmErr;

                // 4. Calculate distance from user to each pharmacy and sort nearest first
                const rankedPharmacies = (pharmacies || []).map(p => {
                    const pLat = parseFloat(p.latitude) || 6.7951;
                    const pLng = parseFloat(p.longitude) || 79.9009;
                    const distanceKm = calculateHaversineKm(userLat, userLng, pLat, pLng);
                    return {
                        ...p,
                        distanceKm,
                        stock: stockByPharmacy[p.id]
                    };
                }).sort((a, b) => a.distanceKm - b.distanceKm).slice(0, 5);

                // 5. Render top 5 nearest pharmacies
                const gpsNote = gpsLocked
                    ? `<div style="font-size: 12px; color: #059669; margin-bottom: 14px; font-weight: 600;"><i class="fas fa-location-arrow"></i> Sorted by live GPS distance from your current location</div>`
                    : `<div style="font-size: 12px; color: #d97706; margin-bottom: 14px; font-weight: 600;"><i class="fas fa-info-circle"></i> Location permission blocked — showing distances from default city center</div>`;

                const cardsHtml = rankedPharmacies.map((p, idx) => `
                    <div class="pharma-result-item">
                        <div>
                            <span class="pharma-rank-badge">#${idx + 1} Nearest • ${p.distanceKm.toFixed(1)} km away</span>
                            <h4 style="margin: 2px 0 4px 0; color: #0f172a; font-size: 16px;">${escapeHtml(p.pharmacy_name)}</h4>
                            <p style="margin: 0; font-size: 13px; color: #64748b;">
                                <i class="fas fa-map-marker-alt" style="color: #0d9488;"></i> ${escapeHtml(p.address)}, ${escapeHtml(p.city)}
                                ${p.phone ? ` • <i class="fas fa-phone"></i> ${escapeHtml(p.phone)}` : ''}
                            </p>
                            <div class="pharma-stock-pill">
                                <i class="fas fa-pills"></i> ${p.stock.totalUnits} units in stock • LKR ${p.stock.minPrice.toFixed(2)} / unit
                            </div>
                        </div>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(p.latitude + ',' + p.longitude)}" target="_blank" rel="noopener" class="btn-directions">
                            <i class="fas fa-directions"></i> Directions
                        </a>
                    </div>
                `).join('');

                bodyEl.innerHTML = gpsNote + cardsHtml;

            } catch (err) {
                console.error('PharmaConnect lookup error:', err);
                bodyEl.innerHTML = `
                    <div style="text-align: center; padding: 24px; color: #dc2626;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 28px; margin-bottom: 8px;"></i>
                        <p style="margin: 0; font-weight: 600;">Error connecting to PharmaConnect inventory: ${escapeHtml(err.message)}</p>
                    </div>
                `;
            }
        }

        function closePharmaModal() {
            document.getElementById('pharma-finder-modal').classList.remove('active');
        }

        function calculateHaversineKm(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth radius in km
            const dLat = (lat2 - lat1) * (Math.PI / 180);
            const dLon = (lon2 - lon1) * (Math.PI / 180);
            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * (Math.PI / 180)) * Math.cos(lat2 * (Math.PI / 180)) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.task-title-input') && 
                !e.target.closest('.med-suggestions-box') && 
                !e.target.closest('.find-med-btn')) {
                document.querySelectorAll('.med-suggestions-box').forEach(b => b.style.display = 'none');
            }
        });
    </script>
    <script src="script.js"></script>
</body>
</html>