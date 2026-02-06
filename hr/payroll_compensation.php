<?php
session_start();
require_once '../config/db.php';
require_role('hr');

$hr_name = htmlspecialchars($_SESSION['user']['fullname'] ?? 'HR Lead');
$message = null;
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $period_start = sanitize($_POST['pay_period_start'] ?? '');
    $period_end = sanitize($_POST['pay_period_end'] ?? '');

    if (empty($period_start) || empty($period_end)) {
        $message = 'Please provide both the start and end dates for the pay period.';
        $message_type = 'warning';
    } else {
        $start_date = DateTime::createFromFormat('Y-m-d', $period_start);
        $end_date = DateTime::createFromFormat('Y-m-d', $period_end);

        if (!$start_date || !$end_date || $start_date > $end_date) {
            $message = 'The pay period dates are invalid. Please confirm the range.';
            $message_type = 'warning';
        } else {
            $insert_stmt = $pdo->prepare("
                INSERT INTO payroll (staff_id, pay_period_start, pay_period_end, basic_salary, allowances, deductions, net_salary, status)
                SELECT s.id, :period_start, :period_end, s.salary, 0, 0, COALESCE(s.salary, 0), 'pending'
                FROM staffs s
                JOIN users u ON u.id = s.user_id
                WHERE s.status = 'active'
                  AND u.status = 'active'
                  AND NOT EXISTS (
                      SELECT 1 FROM payroll p
                      WHERE p.staff_id = s.id
                        AND p.pay_period_start = :period_start
                        AND p.pay_period_end = :period_end
                  )
            ");

            $insert_stmt->execute([
                ':period_start' => $period_start,
                ':period_end' => $period_end,
            ]);

            $inserted = $insert_stmt->rowCount();

            if ($inserted > 0) {
                $message = "Generated {$inserted} payroll entries for {$period_start} to {$period_end}.";
            } else {
                $message = 'Payroll entries already exist for the selected period.';
                $message_type = 'warning';
            }
        }
    }
}

$active_staff_count = (int) $pdo->query("SELECT COUNT(*) FROM staffs WHERE status = 'active'")->fetchColumn();
$pending_total = $pdo->query("SELECT COALESCE(SUM(net_salary), 0) FROM payroll WHERE status = 'pending'")->fetchColumn();
$total_payroll_count = (int) $pdo->query("SELECT COUNT(*) FROM payroll")->fetchColumn();
$paid_payroll_count = (int) $pdo->query("SELECT COUNT(*) FROM payroll WHERE status = 'paid'")->fetchColumn();
$release_rate = $total_payroll_count > 0 ? round(($paid_payroll_count / $total_payroll_count) * 100) : 0;

$latest_period_end = $pdo->query("SELECT MAX(pay_period_end) FROM payroll")->fetchColumn();
$next_pay_run = 'TBD';
if (!empty($latest_period_end)) {
    $next_pay_run = (new DateTime($latest_period_end))->modify('+5 days')->format('M d');
}

$periods_stmt = $pdo->query("
    SELECT pay_period_start, pay_period_end,
           COUNT(*) AS staff_count,
           COALESCE(SUM(net_salary), 0) AS total_net,
           SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
           MIN(created_at) AS created_at
    FROM payroll
    GROUP BY pay_period_start, pay_period_end
    ORDER BY pay_period_start DESC
");
$pay_periods = $periods_stmt->fetchAll();

$selected_start = sanitize($_GET['start'] ?? '');
$selected_end = sanitize($_GET['end'] ?? '');
if (empty($selected_start) && !empty($pay_periods)) {
    $selected_start = $pay_periods[0]['pay_period_start'];
    $selected_end = $pay_periods[0]['pay_period_end'];
}

$entries = [];
if (!empty($selected_start) && !empty($selected_end)) {
    $entries_stmt = $pdo->prepare("
        SELECT p.*, u.fullname, s.department, s.position
        FROM payroll p
        JOIN staffs s ON s.id = p.staff_id
        JOIN users u ON u.id = s.user_id
        WHERE p.pay_period_start = ? AND p.pay_period_end = ?
        ORDER BY u.fullname ASC
    ");
    $entries_stmt->execute([$selected_start, $selected_end]);
    $entries = $entries_stmt->fetchAll();
}
$payroll_kpis = [
    [
        'label' => 'Active staffs',
        'value' => number_format($active_staff_count),
        'note' => 'Eligible for payroll processing',
        'icon' => 'fas fa-users',
        'tone' => 'primary',
    ],
    [
        'label' => 'Next pay run',
       'value' => $next_pay_run,
        'note' => 'Based on latest period end',
        'icon' => 'fas fa-calendar-day',
        'tone' => 'info',
    ],
    [
        'label' => 'Draft payroll total',
        'value' => '₱' . number_format((float) $pending_total, 2),
        'note' => 'Pending owner approval',
        'icon' => 'fas fa-coins',
        'tone' => 'warning',
    ],
    [
        'label' => 'Payslips released',
         'value' => $release_rate . '%',
        'note' => 'Paid payroll entries',
        'icon' => 'fas fa-file-invoice-dollar',
        'tone' => 'success',
    ],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll &amp; Compensation Module</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payroll-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .payroll-kpi {
            grid-column: span 3;
        }

        .payroll-kpi .metric {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .purpose-card,
        .workflow-card {
            grid-column: span 12;
        }

        .periods-card {
            grid-column: span 7;
        }

        .entries-card,
        .generate-card {
            grid-column: span 5;
        }

        .entries-card,
        .exceptions-card {
            grid-column: span 12;
        }

        .empty-state {
            padding: 1.5rem;
            border-radius: var(--radius);
              border: 1px dashed var(--gray-300);
            background: var(--gray-50);
        }

        .table-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

       .badge-soft {
            background: rgba(44, 123, 229, 0.1);
            color: var(--primary-color);
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
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
                <li><a href="payroll_compensation.php" class="nav-link active">Payroll</a></li>
                <li><a href="analytics_reporting.php" class="nav-link">Analytics</a></li>
                <li><a href="../auth/logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>
    <main class="container">
        <section class="page-header">
            <div>
                <h1>Payroll &amp; Compensation</h1>
               <p class="text-muted">Generate pay period entries, route approvals, and view released payslips.</p>
            </div>
           <span class="badge badge-primary"><i class="fas fa-file-invoice-dollar"></i> Module 6.3</span>
        </section>
             <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-3">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <section class="payroll-grid">
            <?php foreach ($payroll_kpis as $kpi): ?>
                <div class="card payroll-kpi">
                    <div class="metric">
                        <div>
                            <p class="text-muted mb-1"><?php echo $kpi['label']; ?></p>
                            <h3 class="mb-1"><?php echo $kpi['value']; ?></h3>
                            <small class="text-muted"><?php echo $kpi['note']; ?></small>
                        </div>
                        <div class="icon-circle bg-<?php echo $kpi['tone']; ?> text-white">
                            <i class="<?php echo $kpi['icon']; ?>"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            

            <div class="card periods-card">
                <h2>Pay period overview</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Status</th>
                                 <th>Entries</th>
                                <th>Total Net</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php if (empty($pay_periods)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">No payroll entries yet. Generate a pay period to begin.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pay_periods as $period): ?>
                                    <?php
                                        $is_paid = (int) $period['paid_count'] === (int) $period['staff_count'];
                                        $status_label = $is_paid ? 'Paid' : 'Pending owner approval';
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($period['pay_period_start']); ?> -
                                            <?php echo htmlspecialchars($period['pay_period_end']); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-outline"><?php echo $status_label; ?></span>
                                        </td>
                                        <td><?php echo (int) $period['staff_count']; ?> staff</td>
                                        <td>₱<?php echo number_format((float) $period['total_net'], 2); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a class="badge-soft" href="payroll_compensation.php?start=<?php echo urlencode($period['pay_period_start']); ?>&amp;end=<?php echo urlencode($period['pay_period_end']); ?>">View entries</a>
                                                <?php if (!$is_paid): ?>
                                                    <span class="badge badge-warning">Awaiting owner approval</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card generate-card">
                <h2>Generate payroll entries</h2>
                <p class="text-muted">Create payroll rows for all active staffs in a new pay period.</p>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="generate">
                    <div class="form-group">
                        <label for="pay_period_start">Pay period start</label>
                        <input type="date" id="pay_period_start" name="pay_period_start" required>
                    </div>
                    <div class="form-group">
                        <label for="pay_period_end">Pay period end</label>
                        <input type="date" id="pay_period_end" name="pay_period_end" required>
                    </div>
                 <button type="submit" class="btn btn-primary">
                        <i class="fas fa-rocket"></i> Generate payroll
                    </button>
                </form>
            </div>

           <div class="card entries-card">
                <h2>Payslip preview</h2>
                <p class="text-muted">Review payroll entries for the selected period and open the payslip view.</p>
                <?php if (empty($entries)): ?>
                    <div class="empty-state">Select a pay period to view payroll entries.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Role</th>
                                    <th>Net Pay</th>
                                    <th>Status</th>
                                    <th>Payslip</th>
                                </tr>
                             </thead>
                            <tbody>
                                <?php foreach ($entries as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entry['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['department'] ?? ''); ?> <?php echo htmlspecialchars($entry['position'] ?? ''); ?></td>
                                        <td>₱<?php echo number_format((float) $entry['net_salary'], 2); ?></td>
                                        <td><span class="badge badge-outline"><?php echo ucfirst($entry['status']); ?></span></td>
                                        <td><a class="badge-soft" href="payslip_view.php?id=<?php echo (int) $entry['id']; ?>">View payslip</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
