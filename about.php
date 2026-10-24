<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | CareSync by Codecrafters</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --cs-primary: #2563eb;
            --cs-teal: #0d9488;
            --cs-bg: #f8fafc;
            --cs-text-main: #0f172a;
            --cs-text-muted: #64748b;
            --cs-border: #e2e8f0;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at top right, #eff6ff 0%, #f8fafc 45%, #f0fdf4 100%);
            color: var(--cs-text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .navbar {
            width: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--cs-border);
            padding: 16px 32px;
            box-sizing: border-box;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .btn-back {
            background: #f1f5f9;
            color: var(--cs-text-main);
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-back:hover {
            background: #e2e8f0;
            transform: translateX(-2px);
        }
        .container {
            max-width: 900px;
            width: 100%;
            padding: 40px 20px;
            box-sizing: border-box;
            margin: 0 auto;
        }
        .hero-section {
            text-align: center;
            margin-bottom: 48px;
        }
        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 0 12px 0;
            background: linear-gradient(135deg, #2563eb 0%, #0d9488 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-section p {
            font-size: 1.1rem;
            color: var(--cs-text-muted);
            line-height: 1.6;
            max-width: 700px;
            margin: 0 auto;
        }
        .project-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--cs-border);
            margin-bottom: 40px;
            text-align: center;
        }
        .uni-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 8px 16px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 16px;
            border: 1px solid #bfdbfe;
        }
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        .team-member {
            background: #f8fafc;
            border: 1px solid var(--cs-border);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
        }
        .team-member:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(15, 23, 42, 0.04);
            background: #ffffff;
        }
        .member-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #e0f2fe 0%, #ccfbf1 100%);
            color: #0369a1;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
        }
        .member-info {
            text-align: left;
        }
        .member-id {
            font-weight: 800;
            color: var(--cs-text-main);
            font-size: 15px;
            display: block;
        }
        .member-role {
            font-size: 12px;
            color: var(--cs-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <div style="font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; gap: 8px;">
            <img src="Firefly.png" alt="CareSync Logo" class="cs-logo">
            CareSync
        </div>
    </nav>

    <div class="container">
        <div class="hero-section">
            <h1>Engineered by Codecrafters</h1>
            <p>CareSync is a unified family health tracking and prescription management platform designed to integrate seamlessly with the PharmaConnect real-time POS and GRN infrastructure.</p>
        </div>

        <div class="project-card">
            <div class="uni-badge">
                <i class="fas fa-university"></i> University of Moratuwa • CSE Dept (Intake 25)
            </div>
            
            <p style="color: var(--cs-text-muted); font-size: 15px; margin-bottom: 32px; line-height: 1.6;">
                Developed as a comprehensive UG(UOM)ing group project. Directed by Anuhas Nimasara, our dedicated team of undergraduate UG(UOM)s built this cloud-based system to bridge the gap between pharmacy inventory logistics and everyday family health monitoring.
            </p>

            <h3 style="text-align: left; margin: 0 0 16px 0; font-weight: 800; font-size: 1.1rem; color: var(--cs-text-main); border-bottom: 2px solid var(--cs-border); padding-bottom: 12px;">
                <i class="fas fa-code-branch" style="color: var(--cs-primary); margin-right: 6px;"></i> CODECRAFTERS
            </h3>
            
            <div class="team-grid">
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250322M</span><span class="member-role">UG(UOM)</span></div></div>
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250295F</span><span class="member-role">UG(UOM)</span></div></div>
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250384D</span><span class="member-role">UG(UOM)</span></div></div>
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250296J</span><span class="member-role">UG(UOM)</span></div></div>
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250016E</span><span class="member-role">UG(UOM)</span></div></div>
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250734D</span><span class="member-role">UG(UOM)</span></div></div>
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250525M</span><span class="member-role">UG(UOM)</span></div></div>
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250283R</span><span class="member-role">UG(UOM)</span></div></div>
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250292T</span><span class="member-role">UG(UOM)</span></div></div>
                <div class="team-member"><div class="member-icon"><i class="fas fa-user-graduate"></i></div><div class="member-info"><span class="member-id">250070L</span><span class="member-role">UG(UOM)</span></div></div>
            </div>
        </div>
    </div>
</body>
</html>