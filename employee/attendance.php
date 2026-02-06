<?php
session_start();
require_once '../config/db.php';
require_role('staff');

$staff_id = $_SESSION['user']['id'];

$emp_stmt = $pdo->prepare("
    SELECT se.*, s.shop_name, s.logo
    FROM shop_staffs se
    JOIN shops s ON se.shop_id = s.id
    WHERE se.user_id = ? AND se.status = 'active'
");
$emp_stmt->execute([$staff_id]);
$staff = $emp_stmt->fetch();

if (!$staff) {
    die('You are not assigned to any shop. Please contact your shop owner.');
}

$shop_id = (int) $staff['shop_id'];
$message = null;
$message_tone = 'success';

$open_stmt = $pdo->prepare("
    SELECT *
    FROM attendance_logs
    WHERE staff_user_id = ? AND shop_id = ? AND clock_out IS NULL
    ORDER BY clock_in DESC
    LIMIT 1
");
$open_stmt->execute([$staff_id, $shop_id]);
$open_log = $open_stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clock_in') {
        if ($open_log) {
            $message = 'You already have an open attendance log. Please clock out first.';
            $message_tone = 'warning';
        } else {
            $insert_stmt = $pdo->prepare("
                INSERT INTO attendance_logs (shop_id, staff_user_id, clock_in, method)
                VALUES (?, ?, NOW(), 'self')
            ");
            $insert_stmt->execute([$shop_id, $staff_id]);
            $message = 'Clock-in recorded successfully.';
        }
    }

    if ($action === 'clock_out') {
        if (!$open_log) {
            $message = 'No open attendance log found. Please clock in first.';
            $message_tone = 'warning';
        } else {
            $update_stmt = $pdo->prepare("
                UPDATE attendance_logs
                SET clock_out = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([(int) $open_log['id']]);
            $message = 'Clock-out recorded successfully.';
            $open_log = null;
        }
    }

    $open_stmt->execute([$staff_id, $shop_id]);
    $open_log = $open_stmt->fetch();
}

$recent_stmt = $pdo->prepare("
    SELECT *
    FROM attendance_logs
    WHERE staff_user_id = ? AND shop_id = ?
    ORDER BY clock_in DESC
    LIMIT 15
");
$recent_stmt->execute([$staff_id, $shop_id]);
$recent_logs = $recent_stmt->fetchAll();

function format_attendance_time(?string $timestamp): string {
    if (!$timestamp) {
        return '—';
    }
    return date('M d, Y g:i A', strtotime($timestamp));
}

function calculate_duration(?string $clock_in, ?string $clock_out): string {
    if (!$clock_in || !$clock_out) {
        return 'In progress';
    }
    $start = new DateTime($clock_in);
    $end = new DateTime($clock_out);
    $interval = $start->diff($end);
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    return sprintf('%02dh %02dm', $hours, $minutes);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo htmlspecialchars($staff['shop_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .attendance-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
        }
        .attendance-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .attendance-actions form {
            margin: 0;
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }
        .attendance-table th,
        .attendance-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            text-align: left;
        }
        .attendance-table th {
            background: #f8f9fa;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-pill.success {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }
        .status-pill.warning {
            background: rgba(255, 193, 7, 0.2);
            color: #8a6d1f;
        }
        .alert-message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .alert-message.success {
            background: rgba(40, 167, 69, 0.12);
            color: #1e7e34;
        }
        .alert-message.warning {
            background: rgba(255, 193, 7, 0.2);
            color: #856404;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container d-flex justify-between align-center">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-user-tie"></i> staff Dashboard
            </a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="assigned_jobs.php" class="nav-link">My Jobs</a></li>
                <li><a href="schedule.php" class="nav-link">Schedule</a></li>
                <li><a href="attendance.php" class="nav-link active">Attendance</a></li>
                <li><a href="update_status.php" class="nav-link">Update Status</a></li>
                <li><a href="upload_photos.php" class="nav-link">Upload Photos</a></li>
                <li class="dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-user"></i> <?php echo $_SESSION['user']['fullname']; ?>
                    </a>
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user-cog"></i> Profile</a>
                        <a href="../auth/logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h2>Clock In / Out</h2>
            <p class="text-muted">Manage your daily attendance for <?php echo htmlspecialchars($staff['shop_name']); ?>.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert-message <?php echo $message_tone; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="attendance-grid">
            <div class="attendance-card">
                <h3>Current Status</h3>
                <p class="text-muted mb-1">Stay aware of your active attendance log.</p>
                <?php if ($open_log): ?>
                    <span class="status-pill success">
                        <i class="fas fa-clock"></i> Clocked in since <?php echo format_attendance_time($open_log['clock_in']); ?>
                    </span>
                <?php else: ?>
                    <span class="status-pill warning">
                        <i class="fas fa-user-clock"></i> Not clocked in
                    </span>
                <?php endif; ?>
            </div>

            <div class="attendance-card">
                <h3>Quick Actions</h3>
                <p class="text-muted mb-1">Use the buttons below to record your attendance.</p>
                <div class="attendance-actions">
                    <form method="POST">
                        <input type="hidden" name="action" value="clock_in">
                        <button type="submit" class="btn btn-primary" <?php echo $open_log ? 'disabled' : ''; ?>>
                            <i class="fas fa-sign-in-alt"></i> Clock In
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="clock_out">
                        <button type="submit" class="btn btn-outline" <?php echo $open_log ? '' : 'disabled'; ?>>
                            <i class="fas fa-sign-out-alt"></i> Clock Out
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Recent Attendance Logs</h3>
            <p class="text-muted">Your last 15 attendance records.</p>
            <div class="table-responsive">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Duration</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recent_logs): ?>
                            <tr>
                                <td colspan="4" class="text-muted">No attendance logs found yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><?php echo format_attendance_time($log['clock_in']); ?></td>
                                    <td><?php echo format_attendance_time($log['clock_out']); ?></td>
                                    <td><?php echo calculate_duration($log['clock_in'], $log['clock_out']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($log['method'] ?? 'self')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>