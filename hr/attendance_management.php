<?php
session_start();
require_once '../config/db.php';
require_role('hr');

$hr_name = htmlspecialchars($_SESSION['user']['fullname'] ?? 'HR Lead');

$default_start = date('Y-m-d', strtotime('-6 days'));
$default_end = date('Y-m-d');

$start_date = $_GET['start_date'] ?? $default_start;
$end_date = $_GET['end_date'] ?? $default_end;

$start_obj = DateTime::createFromFormat('Y-m-d', $start_date) ?: new DateTime($default_start);
$end_obj = DateTime::createFromFormat('Y-m-d', $end_date) ?: new DateTime($default_end);

$start_datetime = $start_obj->format('Y-m-d 00:00:00');
$end_datetime = $end_obj->format('Y-m-d 23:59:59');

$logs_stmt = $pdo->prepare("
    SELECT al.*, u.fullname, u.email, s.shop_name
    FROM attendance_logs al
    JOIN users u ON al.staff_user_id = u.id
    JOIN shops s ON al.shop_id = s.id
    WHERE al.clock_in BETWEEN ? AND ?
    ORDER BY al.clock_in DESC
");
$logs_stmt->execute([$start_datetime, $end_datetime]);
$logs = $logs_stmt->fetchAll();

$total_logs = count($logs);
$open_logs = 0;
$total_minutes = 0;

foreach ($logs as $log) {
    if (empty($log['clock_out'])) {
        $open_logs++;
        continue;
    }
    $start = new DateTime($log['clock_in']);
    $end = new DateTime($log['clock_out']);
    $interval = $start->diff($end);
    $total_minutes += ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
}

$total_hours_display = $total_minutes > 0
    ? sprintf('%d h %02d m', floor($total_minutes / 60), $total_minutes % 60)
    : '0 h 00 m';

function format_attendance_time(?string $timestamp): string {
    if (!$timestamp) {
        return '—';
    }
    return date('M d, Y g:i A', strtotime($timestamp));
}

function format_duration(?string $clock_in, ?string $clock_out): string {
    if (!$clock_in || !$clock_out) {
        return 'Open';
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
    <title>Attendance Management - HR</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .attendance-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1.5rem;
        }
        .attendance-filters .form-group {
            min-width: 200px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 18px;
        }
        .summary-card h3 {
            margin-bottom: 6px;
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
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
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
                <li><a href="attendance_management.php" class="nav-link active">Attendance</a></li>
                <li><a href="analytics_reporting.php" class="nav-link">Analytics</a></li>
                <li><a href="../auth/logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <main class="container">
        <section class="page-header">
            <div>
                <h1>Attendance Management</h1>
                <p class="text-muted">Review staff clock-ins and clock-outs by date range.</p>
            </div>
            <span class="badge badge-primary"><i class="fas fa-calendar-check"></i> Module 9</span>
        </section>

        <form class="attendance-filters" method="GET">
            <div class="form-group">
                <label for="start_date">Start date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_obj->format('Y-m-d')); ?>">
            </div>
            <div class="form-group">
                <label for="end_date">End date</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_obj->format('Y-m-d')); ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply
            </button>
        </form>

        <section class="summary-grid">
            <div class="summary-card">
                <p class="text-muted mb-1">Total logs</p>
                <h3><?php echo $total_logs; ?></h3>
                <small class="text-muted">From <?php echo htmlspecialchars($start_obj->format('M d')); ?> to <?php echo htmlspecialchars($end_obj->format('M d')); ?></small>
            </div>
            <div class="summary-card">
                <p class="text-muted mb-1">Open logs</p>
                <h3><?php echo $open_logs; ?></h3>
                <small class="text-muted">Clocked-in without clock-out.</small>
            </div>
            <div class="summary-card">
                <p class="text-muted mb-1">Total tracked time</p>
                <h3><?php echo $total_hours_display; ?></h3>
                <small class="text-muted">Excludes open logs.</small>
            </div>
        </section>

        <div class="card">
            <h3>Attendance Logs</h3>
            <p class="text-muted">Showing attendance records for the selected dates.</p>
            <div class="table-responsive">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Shop</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Duration</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$logs): ?>
                            <tr>
                                <td colspan="6" class="text-muted">No attendance records found for this range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['fullname']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['shop_name']); ?></td>
                                    <td><?php echo format_attendance_time($log['clock_in']); ?></td>
                                    <td><?php echo format_attendance_time($log['clock_out']); ?></td>
                                    <td><?php echo format_duration($log['clock_in'], $log['clock_out']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($log['method'] ?? 'self')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>