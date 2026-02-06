<?php
session_start();
require_once '../config/db.php';
require_role('hr');

$hr_name = htmlspecialchars($_SESSION['user']['fullname'] ?? 'HR Lead');
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = 'HR notification preferences have been updated.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Preferences - HR</title>
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
            background-color: #e0e7ff;
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
            background-color: #4338ca;
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
    <nav class="navbar navbar--compact">
        <div class="container d-flex justify-between align-center">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-people-group"></i> <?php echo $hr_name; ?>
            </a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="hiring_management.php" class="nav-link">Hiring</a></li>
                <li><a href="staff_productivity_performance.php" class="nav-link">Productivity</a></li>
                <li><a href="payroll_compensation.php" class="nav-link">Payroll</a></li>
                <li><a href="analytics_reporting.php" class="nav-link">Analytics</a></li>
                <li><a href="../auth/logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <main class="container">
        <div class="dashboard-header">
            <h2>Notification Preferences</h2>
            <p class="text-muted">Set the HR alerts you want to stay ahead of.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" class="preferences-card">
            <div class="preference-item">
                <div>
                    <h4>Hiring pipeline alerts</h4>
                    <p class="text-muted mb-0">New applicants, interview updates, and offer approvals.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="hiring_alerts" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Payroll deadlines</h4>
                    <p class="text-muted mb-0">Reminders for payroll processing and approvals.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="payroll_deadlines" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Compliance updates</h4>
                    <p class="text-muted mb-0">Receive policy or regulatory change notifications.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="compliance_updates">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Performance review reminders</h4>
                    <p class="text-muted mb-0">Stay on track with upcoming review cycles.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="review_reminders" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Workforce analytics digest</h4>
                    <p class="text-muted mb-0">A weekly summary of key HR metrics.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="analytics_digest">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preferences-footer">
                <p class="text-muted mb-0">Preferences apply to email and dashboard alerts.</p>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save preferences</button>
            </div>
        </form>
    </main>
</body>
</html>