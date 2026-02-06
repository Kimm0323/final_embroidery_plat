<?php
session_start();
require_once '../config/db.php';
require_role('staff');

$staff_name = htmlspecialchars($_SESSION['user']['fullname'] ?? 'Staff');
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = 'Notification preferences updated.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Preferences - Staff</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .preferences-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
        }
        .preference-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .preference-item:last-child {
            border-bottom: none;
        }
        .preference-item h4 {
            margin-bottom: 6px;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: #d1fae5;
            transition: 0.3s;
            border-radius: 999px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: #fff;
            transition: 0.3s;
            border-radius: 50%;
        }
        .switch input:checked + .slider {
            background-color: #16a34a;
        }
        .switch input:checked + .slider:before {
            transform: translateX(24px);
        }
        .preferences-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container d-flex justify-between align-center">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-user-tie"></i> <?php echo $staff_name; ?>
            </a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="assigned_jobs.php" class="nav-link">My Jobs</a></li>
                <li><a href="schedule.php" class="nav-link">Schedule</a></li>
                <li><a href="update_status.php" class="nav-link">Update Status</a></li>
                <li><a href="upload_photos.php" class="nav-link">Upload Photos</a></li>
                <li class="dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-user"></i> Account
                    </a>
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user-cog"></i> Profile</a>
                        <a href="../auth/logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <main class="container">
        <div class="dashboard-header">
            <h2>Notification Preferences</h2>
            <p class="text-muted">Control the alerts you receive while managing assignments.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" class="preferences-card">
            <div class="preference-item">
                <div>
                    <h4>New job assignments</h4>
                    <p class="text-muted mb-0">Get notified when you're assigned to an order.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="job_assignments" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Schedule changes</h4>
                    <p class="text-muted mb-0">Alerts when shifts or delivery windows change.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="schedule_changes" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Status update reminders</h4>
                    <p class="text-muted mb-0">Gentle nudges to keep order statuses current.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="status_reminders">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Photo upload requests</h4>
                    <p class="text-muted mb-0">Prompts to upload progress or completion photos.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="photo_requests" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Training &amp; policy updates</h4>
                    <p class="text-muted mb-0">Optional alerts for new training modules.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="training_updates">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preferences-footer">
                <p class="text-muted mb-0">These preferences affect in-app and email alerts.</p>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save preferences</button>
            </div>
        </form>
    </main>
</body>
</html>