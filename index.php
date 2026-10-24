<?php

session_set_cookie_params([
    'lifetime' => 31536000,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
date_default_timezone_set('Asia/Colombo');

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
    
    $task_date = $conn->real_escape_string($_POST['task_date']);
    $task_time = $conn->real_escape_string($_POST['task_time']);
    $schedule_time = $task_date . ' ' . $task_time;
    
    $sql = "INSERT INTO tasks (member_id, task_type, title, instructions, schedule_time, status) 
            VALUES ($member_id, '$task_type', '$title', '$instructions', '$schedule_time', 'Pending')";
    $conn->query($sql);
    header("Location: index.php");
    exit();
}

// --- 4. HANDLE MARK AS DONE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_done_id'])) {
    $task_id = (int)$_POST['mark_done_id'];
    
    $check_sql = "SELECT schedule_time FROM tasks WHERE id=$task_id AND member_id IN (SELECT id FROM members WHERE user_id='$user_id') LIMIT 1";
    $result = $conn->query($check_sql);
    $row = ($result) ? $result->fetch_assoc() : null;
    
    if ($row) {
        $scheduled_time = $row['schedule_time'];
        $current_time = date('Y-m-d H:i'); 
        $new_status = ($current_time > $scheduled_time) ? 'Done_Late' : 'Done_OnTime';
        $conn->query("UPDATE tasks SET status='$new_status' WHERE id=$task_id");
    }
    
    header("Location: index.php");
    exit();
}

// --- DATA FETCHING ---
$members_list = [];
$members_res = $conn->query("SELECT * FROM members WHERE user_id = '$user_id' ORDER BY id ASC");
if ($members_res) {
    while ($m = $members_res->fetch_assoc()) {
        $members_list[] = $m;
    }
}

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

// Fetch Medicines from PharmaConnect Supabase Project
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
    return date("M d, Y • h:i A", strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareSync | Family Health Hub</title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Supabase JS SDK -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <link rel="stylesheet" href="style.css?v=4">
    <style>
        :root {
            --cs-primary: #2563eb;
            --cs-primary-dark: #1d4ed8;
            --cs-teal: #0d9488;
            --cs-bg: #f8fafc;
            --cs-surface: #ffffff;
            --cs-border: #e2e8f0;
            --cs-text-main: #0f172a;
            --cs-text-muted: #64748b;
            --cs-danger: #ef4444;
            --cs-warning: #f59e0b;
            --cs-success: #10b981;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif !important;
            background: radial-gradient(circle at top right, #eff6ff 0%, #f8fafc 45%, #f0fdf4 100%) !important;
            color: var(--cs-text-main);
        }

        /* Upgraded Sidebar */
        .sidebar {
            background: #ffffff !important;
            border-right: 1px solid var(--cs-border) !important;
            box-shadow: 4px 0 24px rgba(15, 23, 42, 0.03);
            display: flex;
            flex-direction: column;
        }
        .sidebar .desktop-brand {
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #2563eb 0%, #0d9488 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar .desktop-brand i {
            background: linear-gradient(135deg, #2563eb 0%, #0d9488 100%);
            color: #fff;
            -webkit-text-fill-color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .nav-item {
            margin: 4px 12px;
            padding: 11px 16px !important;
            border-radius: 10px !important;
            font-weight: 600;
            font-size: 0.92rem;
            color: #475569;
            transition: all 0.2s ease;
        }
        .nav-item:hover {
            background: #f1f5f9 !important;
            color: var(--cs-primary) !important;
            transform: translateX(3px);
        }
        .nav-item.active {
            background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%) !important;
            color: var(--cs-primary) !important;
            font-weight: 700;
            box-shadow: inset 3px 0 0 var(--cs-primary);
        }

        /* Sidebar Account ID Card */
        .sidebar-id-card {
            margin: auto 14px 16px 14px;
            padding: 14px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 14px;
            color: #f8fafc;
            box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.25);
        }
        .sidebar-id-card .id-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sidebar-id-card .id-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.08);
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .sidebar-id-card code {
            color: #38bdf8;
            font-weight: 700;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sidebar-id-card button {
            background: rgba(56, 189, 248, 0.15);
            border: none;
            color: #38bdf8;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .sidebar-id-card button:hover {
            background: #38bdf8;
            color: #0f172a;
        }

        /* Glassmorphic Header */
        .header {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--cs-border) !important;
            padding: 18px 32px !important;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header h1 {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.4px;
            color: var(--cs-text-main);
        }
        .user-id-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: #0369a1;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 700;
            border: 1px solid #bae6fd;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 14px;
            box-shadow: 0 2px 6px rgba(2, 132, 199, 0.08);
        }
        .user-id-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.18);
            background: #e0f2fe;
        }

        /* Hero Welcome & KPI Banner */
        .hero-summary-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0d9488 100%);
            border-radius: 20px;
            padding: 28px 32px;
            color: #ffffff;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 16px 32px -10px rgba(37, 99, 235, 0.3);
            position: relative;
            overflow: hidden;
        }
        .hero-summary-banner::after {
            content: '';
            position: absolute;
            right: -40px;
            top: -40px;
            width: 220px;
            height: 220px;
            background: rgba(255, 255, 255, 0.07);
            border-radius: 50%;
            pointer-events: none;
        }
        .hero-banner-text h2 {
            margin: 0 0 6px 0;
            font-size: 1.55rem;
            font-weight: 800;
            letter-spacing: -0.3px;
        }
        .hero-banner-text p {
            margin: 0;
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.92rem;
        }
        .hero-kpi-strip {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }
        .hero-kpi-pill {
            background: rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.22);
            padding: 12px 18px;
            border-radius: 14px;
            min-width: 125px;
            text-align: center;
        }
        .hero-kpi-pill strong {
            display: block;
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.1;
        }
        .hero-kpi-pill span {
            font-size: 11.5px;
            font-weight: 600;
            opacity: 0.88;
        }

        /* Dashboard Columns & Task Cards */
        .dash-column {
            background: #ffffff !important;
            border-radius: 18px !important;
            padding: 24px !important;
            border: 1px solid var(--cs-border) !important;
            box-shadow: 0 6px 20px -4px rgba(15, 23, 42, 0.04) !important;
        }
        .dash-column h3 {
            font-size: 1.08rem;
            font-weight: 800;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .task-item-modern, .task-item {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-left: 5px solid var(--cs-danger) !important;
            border-radius: 14px !important;
            padding: 16px 18px !important;
            margin-bottom: 14px !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02) !important;
        }
        .task-item-modern:hover, .task-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px -6px rgba(15, 23, 42, 0.08) !important;
            border-color: #cbd5e1 !important;
        }
        .member-badge {
            background: #eff6ff !important;
            color: #1d4ed8 !important;
            font-weight: 700 !important;
            font-size: 11.5px !important;
            padding: 4px 10px !important;
            border-radius: 999px !important;
            border: 1px solid #bfdbfe;
        }
        .task-main-title {
            font-size: 1.02rem !important;
            font-weight: 800 !important;
            color: #0f172a !important;
        }

        /* Action Buttons */
        .btn-action-done {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: #ffffff !important;
            border: none !important;
            padding: 9px 15px !important;
            border-radius: 10px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
            transition: all 0.2s ease;
        }
        .btn-action-done:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(16, 185, 129, 0.35);
        }

        .btn-action-find {
            background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%) !important;
            color: #ffffff !important;
            border: none !important;
            padding: 9px 15px !important;
            border-radius: 10px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 10px rgba(13, 148, 136, 0.22);
            transition: all 0.2s ease;
        }
        .btn-action-find:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(2, 132, 199, 0.35);
        }

        /* Form Card & Inputs */
        .form-card {
            background: var(--cs-surface);
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 8px 24px -6px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--cs-border);
            margin-bottom: 24px;
        }
        .form-group { margin-bottom: 18px; position: relative; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; font-size: 13.5px; color: #334155; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 14px;
            background: #f8fafc;
            transition: all 0.2s ease;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            background: #ffffff;
            border-color: var(--cs-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 12px 22px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
        }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

        /* Autocomplete dropdown styles */
        .med-suggestions-box {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            box-shadow: 0 15px 30px -5px rgba(15, 23, 42, 0.14);
            max-height: 220px;
            overflow-y: auto;
            z-index: 9999;
            margin-top: 6px;
        }
        .med-suggestion-item {
            padding: 11px 16px;
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s ease;
        }
        .med-suggestion-item:last-child { border-bottom: none; }
        .med-suggestion-item:hover {
            background-color: #eff6ff;
            color: var(--cs-primary);
            font-weight: 700;
        }
        .med-suggestion-empty {
            padding: 12px 16px;
            font-size: 13px;
            color: #94a3b8;
            font-style: italic;
        }

        /* Nearest Pharmacies Modal Styles */
        .pharma-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 16px;
        }
        .pharma-modal-backdrop.active { display: flex; }
        .pharma-modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 650px;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);
            overflow: hidden;
            animation: modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalPop {
            from { transform: translateY(15px) scale(0.96); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        .pharma-modal-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #0f172a 0%, #0d9488 100%);
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pharma-modal-header h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pharma-modal-close {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: #fff;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
        }
        .pharma-modal-close:hover { background: rgba(255, 255, 255, 0.3); }
        .pharma-modal-body {
            padding: 22px 24px;
            max-height: 72vh;
            overflow-y: auto;
            background: #f8fafc;
        }
        .pharma-result-item {
            background: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 16px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
        }
        .pharma-result-item:hover {
            border-color: #0d9488;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -6px rgba(13, 148, 136, 0.14);
        }
        .pharma-rank-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #ccfbf1;
            color: #0f766e;
            font-size: 11.5px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            margin-bottom: 6px;
        }
        .pharma-stock-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            font-size: 12.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 8px;
            margin-top: 8px;
        }
        .btn-directions {
            background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            box-shadow: 0 4px 10px rgba(13, 148, 136, 0.25);
            transition: all 0.2s ease;
        }
        .btn-directions:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(2, 132, 199, 0.35);
        }

        @media (max-width: 768px) {
            .two-col { grid-template-columns: 1fr; }
            .hero-summary-banner { padding: 22px; }
            .pharma-result-item { flex-direction: column; align-items: flex-start; }
            .btn-directions { width: 100%; justify-content: center; box-sizing: border-box; }
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
                <i class="fas fa-th-large"></i> Main Dashboard
            </li>
            <div class="section-title" style="padding: 16px 20px 6px; font-size: 11px; font-weight: 800; letter-spacing: 0.8px; color: #94a3b8; text-transform: uppercase;">Family Members</div>
            <?php 
            if (count($members_list) > 0) {
                foreach($members_list as $row) { 
                    echo "<li class='nav-item' onclick=\"switchView('member-{$row['id']}', this, '" . addslashes($row['name']) . "\'s Health Profile'); closeMobileMenu();\">
                            <i class='fas fa-user-Astronaut' style='display:none'></i><i class='fas fa-user-circle'></i> " . htmlspecialchars($row['name']) . "
                          </li>";
                }
            } else {
                echo '<li style="padding: 12px 20px; color: #94a3b8; font-size: 13px;">No members added yet.</li>';
            }
            ?>
            <li class="nav-item" style="color: var(--cs-primary); margin-top: 8px; border: 1px dashed #bfdbfe; background: #f8fafc;" onclick="switchView('add-new-member', this, 'Add Family Member'); closeMobileMenu();">
                <i class="fas fa-user-plus"></i> Add Family Member
            </li>
        </ul>

        <!-- Sleek Dark Sidebar Account ID Box -->
        <div class="sidebar-id-card">
            <div class="id-label"><i class="fas fa-fingerprint"></i> CareSync User ID</div>
            <div class="id-box">
                <code title="<?php echo htmlspecialchars($user_id); ?>"><?php echo htmlspecialchars($user_id); ?></code>
                <button type="button" onclick="copyUserId('<?php echo addslashes($user_id); ?>')" title="Copy User ID">
                    <i class="far fa-copy"></i>
                </button>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1 id="page-title">Family Overview</h1>
            <div class="user-controls" style="display: flex; align-items: center;">
                <!-- Clickable User ID Pill -->
                <div class="user-id-badge" onclick="copyUserId('<?php echo addslashes($user_id); ?>')" title="Click to copy your User ID">
                    <i class="fas fa-id-badge"></i>
                    <span>ID: <?php echo htmlspecialchars($user_id); ?></span>
                    <i class="far fa-copy" style="font-size: 11px; opacity: 0.75;"></i>
                </div>

                <button id="desktop-bell-btn" class="notification-btn" style="background:#f1f5f9; border:none; width:38px; height:38px; border-radius:10px; font-size:18px; color:#475569; margin-right:16px; cursor:pointer; transition:all 0.2s;" title="Enable Notifications">
                    <i class="fas fa-bell"></i>
                </button>
                <div class="user-profile" style="display:flex; align-items:center; gap:12px;">
                    <span class="user-name" style="font-weight:700; color:#1e293b;"><i class="fas fa-user-circle" style="color:var(--cs-primary);"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="logout.php" class="logout-btn" style="font-weight:700; border-radius:10px; padding:8px 14px;"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
                </div>
            </div>
        </header>

        <div class="content-area">
            
            <!-- 1. DASHBOARD VIEW -->
            <div id="dashboard" class="view-section active">
                
                <!-- Eye-Catching Gradient Hero Banner -->
                <div class="hero-summary-banner">
                    <div class="hero-banner-text">
                        <h2>Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?> 👋</h2>
                        <p>Track your family's daily prescriptions, clinic visits, and locate nearby pharmacy stock in real time.</p>
                    </div>
                    <div class="hero-kpi-strip">
                        <div class="hero-kpi-pill">
                            <strong><?php echo count($members_list); ?></strong>
                            <span>Family Members</span>
                        </div>
                        <div class="hero-kpi-pill">
                            <strong><?php echo count($dashboard_today); ?></strong>
                            <span>Due Today</span>
                        </div>
                        <div class="hero-kpi-pill">
                            <strong><?php echo count($dashboard_upcoming); ?></strong>
                            <span>Upcoming</span>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-grid">
                    <!-- LEFT COLUMN: TODAY / URGENT -->
                    <div class="dash-column">
                        <h3><i class="fas fa-bolt" style="color: var(--cs-danger);"></i> Today's Active Schedule</h3>
                        <?php if (count($dashboard_today) > 0): ?>
                            <?php foreach($dashboard_today as $task): 
                                $raw_note = trim($task['instructions'] ?? '');
                                $has_valid_note = (!empty($raw_note) && strtolower($raw_note) !== 'none');
                            ?>
                                <div class="task-item-modern">
                                    <div class="task-info-group">
                                        <div class="task-title-row" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px;">
                                            <span class="member-badge"><i class="fas fa-user"></i> <?php echo htmlspecialchars($task['member_name']); ?></span>
                                            <h4 class="task-main-title" style="margin:0;"><?php echo htmlspecialchars($task['title']); ?></h4>
                                        </div>
                                        <p class="task-meta-time" style="margin:0; font-size:13px; color:#64748b; font-weight:500;">
                                            <i class="far fa-clock" style="color:var(--cs-danger);"></i> <?php echo formatDateTime($task['schedule_time']); ?>
                                        </p>
                                        <?php if ($has_valid_note): ?>
                                            <p class="task-meta-note" style="margin:6px 0 0 0; font-size:12.5px; color:#475569; background:#f8fafc; padding:6px 10px; border-radius:8px; border-left:3px solid #cbd5e1;"><?php echo htmlspecialchars($raw_note); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <form action="index.php" method="POST" class="task-actions-group" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:12px;">
                                        <input type="hidden" name="mark_done_id" value="<?php echo $task['id']; ?>">
                                        <button type="submit" class="btn-action-done">
                                            <i class="fas fa-check"></i> Done
                                        </button>
                                        <?php if ($task['task_type'] === 'Medicine'): ?>
                                            <button type="button" class="btn-action-find find-med-btn" onclick="findNearestPharmacies('<?php echo addslashes($task['title']); ?>')">
                                                <i class="fas fa-map-marker-alt"></i> Find a Pharmacy
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding:36px 16px; color:#94a3b8;">
                                <i class="fas fa-check-circle" style="font-size:38px; color:#10b981; margin-bottom:10px; opacity:0.8;"></i>
                                <p style="margin:0; font-weight:600; color:#475569;">All caught up for today!</p>
                                <small>No urgent medicines or clinics remaining.</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- RIGHT COLUMN: UPCOMING -->
                    <div class="dash-column">
                        <h3><i class="fas fa-calendar-check" style="color: var(--cs-warning);"></i> Upcoming Schedule</h3>
                        <?php if (count($dashboard_upcoming) > 0): ?>
                            <?php foreach($dashboard_upcoming as $task): ?>
                                <div class="task-item" style="border-left: 5px solid var(--cs-warning) !important; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                                    <div>
                                        <span class="member-badge" style="margin-bottom:6px; display:inline-block;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($task['member_name']); ?></span>
                                        <h4 style="margin: 2px 0 5px 0; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($task['title']); ?></h4>
                                        <p style="font-size: 13px; color: var(--cs-text-muted); margin:0;"><i class="far fa-clock"></i> <?php echo formatDateTime($task['schedule_time']); ?></p>
                                    </div>
                                    <?php if ($task['task_type'] === 'Medicine'): ?>
                                        <button type="button" class="btn-action-find find-med-btn" onclick="findNearestPharmacies('<?php echo addslashes($task['title']); ?>')">
                                            <i class="fas fa-map-marker-alt"></i> Find a Pharmacy
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding:36px 16px; color:#94a3b8;">
                                <i class="far fa-calendar-alt" style="font-size:36px; margin-bottom:10px; opacity:0.6;"></i>
                                <p style="margin:0; font-weight:600; color:#475569;">No future tasks scheduled</p>
                                <small>Select a family member from the sidebar to add schedules.</small>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- ADD MEMBER VIEW -->
            <div id="add-new-member" class="view-section">
                <div class="form-card" style="max-width: 580px; margin: 10px auto;">
                    <h2 style="margin-top:0; font-weight:800; color:#0f172a;"><i class="fas fa-user-plus" style="color:var(--cs-primary);"></i> Add a Family Member</h2>
                    <p style="color:#64748b; font-size:14px; margin-bottom:22px;">Create a dedicated health profile to track prescriptions and clinic appointments.</p>
                    <form action="index.php" method="POST">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Chooty / Kasun Perera">
                        </div>
                        <div class="form-group">
                            <label>Relationship *</label>
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
                        <button type="submit" name="add_member" class="btn-primary"><i class="fas fa-check-circle"></i> Create Member Profile</button>
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
                    
                    <div class="member-profile-header" style="background:#fff; padding:22px 26px; border-radius:18px; border:1px solid var(--cs-border); margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 14px rgba(15,23,42,0.03);">
                        <div style="display:flex; align-items:center; gap:16px;">
                            <div style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg,#2563eb,#0d9488); color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800;">
                                <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h2 style="margin: 0 0 4px 0; font-weight:800; font-size:1.35rem;"><?php echo $member_name; ?></h2>
                                <span class="member-badge">Relation: <?php echo htmlspecialchars($row['relation']); ?></span>
                            </div>
                        </div>
                        <form action="index.php" method="POST" onsubmit="return confirm('Delete this member and all their scheduled tasks?');">
                            <input type="hidden" name="delete_member_id" value="<?php echo $member_id; ?>">
                            <button type="submit" class="btn-danger" style="border-radius:10px; padding:10px 16px; font-weight:700; cursor:pointer;"><i class="fas fa-trash"></i> Remove Profile</button>
                        </form>
                    </div>

                    <!-- PENDING AND DONE TABS -->
                    <div class="dashboard-grid" style="margin-bottom: 24px;">
                        
                        <!-- Member's Pending Tasks -->
                        <div class="dash-column">
                            <h3><i class="fas fa-hourglass-half" style="color: var(--cs-warning);"></i> Pending Schedule</h3>
                            <?php 
                            $has_pending = false;
                            foreach($this_member_tasks as $task) {
                                if($task['status'] == 'Pending') {
                                    $has_pending = true;
                                    echo "<div class='task-item' style='border-left:5px solid var(--cs-primary) !important; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;'>";
                                    echo "<div>";
                                    echo "<h4 style='margin:0 0 4px 0; font-weight:800;'>".htmlspecialchars($task['title'])."</h4>";
                                    echo "<p style='font-size:13px; color:var(--cs-text-muted); margin:0;'><i class='far fa-clock'></i> ".formatDateTime($task['schedule_time'])."</p>";
                                    if (!empty($task['instructions'])) {
                                        echo "<p style='font-size:12.5px; margin:6px 0 0 0; color:#475569;'>".htmlspecialchars($task['instructions'])."</p>";
                                    }
                                    echo "</div>";
                                    if ($task['task_type'] === 'Medicine') {
                                        $safeTitle = addslashes($task['title']);
                                        echo "<button type='button' class='btn-action-find find-med-btn' onclick=\"findNearestPharmacies('{$safeTitle}')\"><i class='fas fa-map-marker-alt'></i> Find a Pharmacy</button>";
                                    }
                                    echo "</div>";
                                }
                            }
                            if(!$has_pending) echo "<p class='empty-state' style='text-align:center; color:#94a3b8; padding:20px;'>No pending tasks.</p>";
                            ?>
                        </div>

                        <!-- Member's History Log -->
                        <div class="dash-column">
                            <h3><i class="fas fa-history" style="color: var(--cs-success);"></i> Completed History</h3>
                            <?php 
                            $has_done = false;
                            foreach($this_member_tasks as $task) {
                                if(strpos($task['status'], 'Done') !== false) {
                                    $has_done = true;
                                    $borderCol = ($task['status'] == 'Done_Late') ? 'var(--cs-danger)' : 'var(--cs-success)';
                                    echo "<div class='task-item task-done' style='border-left:5px solid {$borderCol} !important; opacity:0.9;'>";
                                    echo "<h4 style='margin:0 0 4px 0; font-weight:700;'>".htmlspecialchars($task['title'])."</h4>";
                                    
                                    if ($task['status'] == 'Done_Late') {
                                        echo "<p style='font-size:12.5px; color: var(--cs-danger); font-weight: 700; margin:0;'><i class='fas fa-exclamation-triangle'></i> Completed Late</p>";
                                    } else {
                                        echo "<p style='font-size:12.5px; color: var(--cs-success); font-weight: 700; margin:0;'><i class='fas fa-check-circle'></i> Completed On Time</p>";
                                    }
                                    echo "</div>";
                                }
                            }
                            if(!$has_done) echo "<p class='empty-state' style='text-align:center; color:#94a3b8; padding:20px;'>No completed history yet.</p>";
                            ?>
                        </div>

                    </div>

                    <!-- FORM TO ADD TASK -->
                    <div class="form-card">
                        <h3 style="margin: 0 0 18px 0; font-weight:800;"><i class="fas fa-calendar-plus" style="color:var(--cs-primary);"></i> Schedule New Medicine or Clinic</h3>
                        <form action="index.php" method="POST">
                            <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                            
                            <div class="two-col">
                                <div class="form-group">
                                    <label>Schedule Type</label>
                                    <select name="task_type" class="task-type-select" onchange="handleTaskTypeChange(this)" required>
                                        <option value="Medicine">Medicine Prescription</option>
                                        <option value="Clinic Date">Clinic Appointment</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="title-label">Medicine Name</label>
                                    <div style="display: flex; gap: 8px;">
                                        <input type="text" 
                                               name="title" 
                                               class="task-title-input" 
                                               required 
                                               placeholder="Start typing medicine (e.g. Paracetamol)..." 
                                               autocomplete="off"
                                               oninput="handleMedicineInput(this)">
                                        <button type="button" class="btn-action-find find-med-btn" style="white-space: nowrap;" onclick="findNearestPharmacies(this.previousElementSibling.value)">
                                            <i class="fas fa-map-marker-alt"></i> Find a Pharmacy
                                        </button>
                                    </div>
                                    <div class="med-suggestions-box" style="display: none;"></div>
                                </div>
                            </div>
                            
                            <div class="two-col">
                                <div class="form-group">
                                    <label>Scheduled Date</label>
                                    <input type="date" name="task_date" required>
                                </div>
                                <div class="form-group">
                                    <label>Scheduled Time</label>
                                    <input type="time" name="task_time" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Dosage / Special Instructions (Optional)</label>
                                <input type="text" name="instructions" placeholder="e.g. Take 1 tablet after dinner with warm water">
                            </div>
                            
                            <button type="submit" name="add_task" class="btn-primary"><i class="fas fa-plus-circle"></i> Save to Schedule</button>
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
                <h3 id="pharma-modal-title"><i class="fas fa-mortar-pestle"></i> Live Pharmacy Stock Finder</h3>
                <button type="button" class="pharma-modal-close" onclick="closePharmaModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="pharma-modal-body" class="pharma-modal-body"></div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>

    <footer class="cs-footer">
    <div class="cs-footer-container">
        <div class="cs-brand">
            <img src="Gemini_Generated_Image_h1uw8kh1uw8kh1uw.jpg" alt="CareSync Logo" class="cs-logo">
            <span class="cs-brand-name">CareSync</span>
        </div>
        
        <div class="cs-powered">
            <span class="cs-powered-text">Powered by</span>
            <svg class="supabase-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11.9997 2.25L4.54228 15.1765C4.24925 15.6845 4.61453 16.3267 5.20235 16.3267H10.4997V21.75L17.9572 8.82353C18.2502 8.31553 17.8849 7.67333 17.2971 7.67333H11.9997V2.25Z" fill="#3ECF8E"/>
            </svg>
            <span class="cs-supabase-text">Supabase</span>
        </div>
    </div>
    
    <div class="cs-copyright">
        &copy; 2026 Codecrafters. All rights reserved.
    </div>
</footer>

    <script>
        const todayTasks = <?php echo json_encode($dashboard_today); ?>;
        const availableMedicines = <?php echo json_encode($medicines_list); ?>;

        // PharmaConnect Supabase Configuration
        const PHARMA_SUPABASE_URL = 'https://juobkaftyrzlxbviudpy.supabase.co';
        const PHARMA_SUPABASE_KEY = 'sb_publishable_qdME8AYk9rz5B4FHs4Eb1g_0vvhN73v';
        const pharmaSupabase = window.supabase.createClient(PHARMA_SUPABASE_URL, PHARMA_SUPABASE_KEY);

        function copyUserId(uid) {
            navigator.clipboard.writeText(uid).then(() => {
                alert('Your CareSync User ID (' + uid + ') has been copied to your clipboard!');
            }).catch(() => {
                prompt('Copy your CareSync User ID:', uid);
            });
        }

        function handleTaskTypeChange(selectElem) {
            const formCard = selectElem.closest('.form-card');
            const labelElem = formCard.querySelector('.title-label');
            const inputElem = formCard.querySelector('.task-title-input');
            const findBtn = formCard.querySelector('.find-med-btn');
            const box = formCard.querySelector('.med-suggestions-box');

            if (selectElem.value === 'Clinic Date') {
                labelElem.innerText = 'Clinic / Hospital Name';
                inputElem.placeholder = 'e.g. National Hospital Cardiology Clinic';
                if (findBtn) findBtn.style.display = 'none';
                if (box) box.style.display = 'none';
            } else {
                labelElem.innerText = 'Medicine Name';
                inputElem.placeholder = 'Start typing medicine (e.g. Paracetamol)...';
                if (findBtn) findBtn.style.display = 'inline-flex';
            }
        }

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

            const matches = availableMedicines.filter(med => 
                med.toLowerCase().startsWith(query) || med.toLowerCase().includes(query)
            );

            box.innerHTML = '';
            if (matches.length > 0) {
                matches.forEach(med => {
                    const item = document.createElement('div');
                    item.className = 'med-suggestion-item';
                    item.innerHTML = `<i class="fas fa-capsules" style="color:#0d9488; margin-right:8px;"></i> ${escapeHtml(med)}`;
                    item.onmousedown = function () {
                        inputElem.value = med;
                        box.style.display = 'none';
                    };
                    box.appendChild(item);
                });
            } else {
                const empty = document.createElement('div');
                empty.className = 'med-suggestion-empty';
                empty.innerText = 'No matching medicine in PharmaConnect catalog';
                box.appendChild(empty);
            }

            box.style.display = 'block';
        }

        // Live GPS + Nearest 5 Pharmacies Lookup
        function findNearestPharmacies(medicineName) {
            const cleanMed = (medicineName || '').trim();
            if (!cleanMed) {
                alert('Please enter or select a medicine name first.');
                return;
            }

            const modal = document.getElementById('pharma-finder-modal');
            const titleEl = document.getElementById('pharma-modal-title');
            const bodyEl = document.getElementById('pharma-modal-body');

            titleEl.innerHTML = `<i class="fas fa-mortar-pestle"></i> Pharmacies with "${escapeHtml(cleanMed)}"`;
            bodyEl.innerHTML = `
                <div style="text-align: center; padding: 36px 16px; color: #64748b;">
                    <i class="fas fa-satellite-dish fa-spin" style="font-size: 32px; color: #0d9488; margin-bottom: 14px;"></i>
                    <h4 style="margin: 0 0 4px 0; color: #0f172a; font-weight:800;">Locating Nearest Pharmacies...</h4>
                    <p style="margin: 0; font-size: 13px;">Checking real-time PharmaConnect GRN stock batches & calculating GPS distance.</p>
                </div>
            `;
            modal.classList.add('active');

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => queryPharmaConnectStock(cleanMed, pos.coords.latitude, pos.coords.longitude, true),
                    () => queryPharmaConnectStock(cleanMed, 6.7951, 79.9009, false),
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
                const { data: stockRows, error: stockErr } = await pharmaSupabase
                    .from('grn_stock')
                    .select('pharmacy_id, medicine_name, current_quantity, selling_price, expiry_date')
                    .ilike('medicine_name', `%${medicineName}%`)
                    .gt('current_quantity', 0)
                    .gt('expiry_date', todayStr);

                if (stockErr) throw stockErr;

                if (!stockRows || stockRows.length === 0) {
                    bodyEl.innerHTML = `
                        <div style="text-align: center; padding: 32px 16px; background:#fff; border-radius:14px; border:1px dashed #cbd5e1;">
                            <i class="fas fa-box-open" style="font-size: 38px; color: #94a3b8; margin-bottom: 12px;"></i>
                            <h4 style="color: #0f172a; margin: 0 0 6px 0; font-weight:800;">Currently Out of Stock</h4>
                            <p style="font-size: 13.5px; color:#64748b; margin: 0;">No registered PharmaConnect pharmacies have active, non-expired stock for <strong>${escapeHtml(medicineName)}</strong> right now.</p>
                        </div>
                    `;
                    return;
                }

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

                const { data: pharmacies, error: pharmErr } = await pharmaSupabase
                    .from('pharmacies')
                    .select('*')
                    .in('id', pharmacyIds);

                if (pharmErr) throw pharmErr;

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

                const gpsBanner = gpsLocked
                    ? `<div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:10px 14px; border-radius:10px; font-size:12.5px; margin-bottom:14px; font-weight:700; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-location-crosshairs"></i> Showing top ${rankedPharmacies.length} nearest pharmacies based on your live GPS location
                       </div>`
                    : `<div style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:10px 14px; border-radius:10px; font-size:12.5px; margin-bottom:14px; font-weight:700; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-info-circle"></i> Browser GPS blocked — distances calculated from default reference center
                       </div>`;

                const cardsHtml = rankedPharmacies.map((p, idx) => `
                    <div class="pharma-result-item">
                        <div>
                            <span class="pharma-rank-badge"><i class="fas fa-award"></i> #${idx + 1} Nearest • ${p.distanceKm.toFixed(1)} km away</span>
                            <h4 style="margin: 2px 0 5px 0; color: #0f172a; font-size: 16.5px; font-weight:800;">${escapeHtml(p.pharmacy_name)}</h4>
                            <p style="margin: 0; font-size: 13px; color: #64748b; font-weight:500;">
                                <i class="fas fa-map-marker-alt" style="color: #0d9488;"></i> ${escapeHtml(p.address)}, ${escapeHtml(p.city)}
                                ${p.phone ? ` &bull; <i class="fas fa-phone-alt" style="color:#2563eb;"></i> ${escapeHtml(p.phone)}` : ''}
                            </p>
                            <div class="pharma-stock-pill">
                                <i class="fas fa-boxes-stacked"></i> ${p.stock.totalUnits} units available &bull; LKR ${p.stock.minPrice.toFixed(2)} / unit
                            </div>
                        </div>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(p.latitude + ',' + p.longitude)}" target="_blank" rel="noopener" class="btn-directions">
                            <i class="fas fa-diamond-turn-right"></i> Get Directions
                        </a>
                    </div>
                `).join('');

                bodyEl.innerHTML = gpsBanner + cardsHtml;

            } catch (err) {
                console.error('PharmaConnect lookup error:', err);
                bodyEl.innerHTML = `
                    <div style="text-align: center; padding: 24px; color: #dc2626; background:#fef2f2; border-radius:12px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 28px; margin-bottom: 8px;"></i>
                        <p style="margin: 0; font-weight: 700;">Failed to query PharmaConnect inventory: ${escapeHtml(err.message)}</p>
                    </div>
                `;
            }
        }

        function closePharmaModal() {
            document.getElementById('pharma-finder-modal').classList.remove('active');
        }

        function calculateHaversineKm(lat1, lon1, lat2, lon2) {
            const R = 6371;
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