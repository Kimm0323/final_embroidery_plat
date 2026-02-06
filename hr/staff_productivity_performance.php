<?php
session_start();
require_once '../config/db.php';
require_role('hr');

$hr_name = htmlspecialchars($_SESSION['user']['fullname'] ?? 'HR Lead');

$orderTotals = $pdo->query("
    SELECT
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        AVG(CASE WHEN status = 'completed' AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, completed_at) END) as avg_completion_hours,
        SUM(CASE WHEN status = 'completed' THEN quantity ELSE 0 END) as completed_pieces
    FROM orders
")->fetch();

$qcTotals = $pdo->query("
    SELECT
        COUNT(*) as total_events,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_events
    FROM order_fulfillment_history
")->fetch();

$staffProductivity = $pdo->query("
    SELECT
        u.id,
        u.fullname,
        COUNT(o.id) as total_assigned,
        SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN o.status IN ('accepted', 'in_progress') THEN 1 ELSE 0 END) as active_orders,
        AVG(CASE WHEN o.status = 'completed' AND o.completed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, o.created_at, o.completed_at) END) as avg_completion_hours,
        SUM(CASE WHEN o.status = 'completed' THEN o.quantity ELSE 0 END) as completed_pieces,
        COALESCE(qc.qc_failures, 0) as qc_failures
    FROM users u
    LEFT JOIN orders o ON o.assigned_to = u.id
    LEFT JOIN (
        SELECT
            o2.assigned_to as staff_id,
            COUNT(ofh.id) as qc_failures
        FROM order_fulfillment_history ofh
        JOIN order_fulfillments ofl ON ofl.id = ofh.fulfillment_id
        JOIN orders o2 ON o2.id = ofl.order_id
        WHERE ofh.status = 'failed'
        GROUP BY o2.assigned_to
    ) qc ON qc.staff_id = u.id
    WHERE u.role = 'staff'
    GROUP BY u.id, u.fullname, qc.qc_failures
    ORDER BY completed_orders DESC, completed_pieces DESC, u.fullname
")->fetchAll();

$totalOrders = (int) ($orderTotals['total_orders'] ?? 0);
$completedOrders = (int) ($orderTotals['completed_orders'] ?? 0);
$avgCompletionHours = $orderTotals['avg_completion_hours'] !== null ? (float) $orderTotals['avg_completion_hours'] : null;
$completedPieces = (int) ($orderTotals['completed_pieces'] ?? 0);
$totalQcEvents = (int) ($qcTotals['total_events'] ?? 0);
$failedQcEvents = (int) ($qcTotals['failed_events'] ?? 0);

$completionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : null;
$qcFailureRate = $totalQcEvents > 0 ? ($failedQcEvents / $totalQcEvents) * 100 : null;


$productivity_kpis = [
    [
        'label' => 'Completion rate',
        'value' => $completionRate !== null ? number_format($completionRate, 1) . '%' : '—',
        'note' => $totalOrders > 0
            ? sprintf('%d of %d orders completed', $completedOrders, $totalOrders)
            : 'No orders logged yet',
        'icon' => 'fas fa-check-double',
        'tone' => 'success',
    ],
    [
        'label' => 'Avg. cycle time',
        'value' => $avgCompletionHours !== null ? number_format($avgCompletionHours, 1) . ' hrs' : '—',
        'note' => $avgCompletionHours !== null ? 'Based on completed orders' : 'Awaiting completed orders',
        'icon' => 'fas fa-stopwatch',
        'tone' => 'primary',
    ],
    [
        'label' => 'QC failure rate',
        'value' => $qcFailureRate !== null ? number_format($qcFailureRate, 1) . '%' : '—',
        'note' => $totalQcEvents > 0
            ? sprintf('%d failures logged', $failedQcEvents)
            : 'No QC events tracked',
        'icon' => 'fas fa-triangle-exclamation',
        'tone' => 'warning',
    ],
    [
        'label' => 'Output volume',
       'value' => number_format($completedPieces) . ' pcs',
        'note' => $completedPieces > 0 ? 'Completed order quantity' : 'No completed output yet',
        'icon' => 'fas fa-box-open',
        'tone' => 'info',
    ],
];

$topCompleter = null;
$fastestStaff = null;
$highestQc = null;
$topOutput = null;

foreach ($staffProductivity as $staff) {
    if ($topCompleter === null || (int) $staff['completed_orders'] > (int) $topCompleter['completed_orders']) {
        $topCompleter = $staff;
    }
    if ($staff['avg_completion_hours'] !== null) {
        if ($fastestStaff === null || (float) $staff['avg_completion_hours'] < (float) $fastestStaff['avg_completion_hours']) {
            $fastestStaff = $staff;
        }
    }
    if ($highestQc === null || (int) $staff['qc_failures'] > (int) $highestQc['qc_failures']) {
        $highestQc = $staff;
    }
    if ($topOutput === null || (int) $staff['completed_pieces'] > (int) $topOutput['completed_pieces']) {
        $topOutput = $staff;
    }
}

$focus_insights = [
    [
        'title' => 'Top completer',
        'detail' => $topCompleter && $topCompleter['completed_orders'] > 0
            ? sprintf('%s completed %d orders.', $topCompleter['fullname'], $topCompleter['completed_orders'])
            : 'No completed orders recorded yet.',
        'icon' => 'fas fa-chart-line',
    ],
    [
       'title' => 'Fastest cycle',
        'detail' => $fastestStaff && $fastestStaff['avg_completion_hours'] !== null
            ? sprintf('%s averages %s hrs per completed order.', $fastestStaff['fullname'], number_format($fastestStaff['avg_completion_hours'], 1))
            : 'Cycle time will populate once orders complete.',
        'icon' => 'fas fa-gauge-high',
    ],
    [
        'title' => 'QC failures',
        'detail' => $failedQcEvents > 0 && $highestQc
            ? sprintf('%s has %d logged QC failures.', $highestQc['fullname'], $highestQc['qc_failures'])
            : 'No QC failures logged in fulfillment history.',
        'icon' => 'fas fa-screwdriver-wrench',
    ],
    [
        'title' => 'Output volume',
         'detail' => $topOutput && $topOutput['completed_pieces'] > 0
            ? sprintf('%s delivered %d completed pieces.', $topOutput['fullname'], $topOutput['completed_pieces'])
            : 'Output volume will update after completions.',
        'icon' => 'fas fa-layer-group',
    ],
];

$anomalies = [];
if ($qcFailureRate !== null && $qcFailureRate > 0) {
    $anomalies[] = [
        'title' => 'QC failures logged',
        'detail' => sprintf('%d QC failure events recorded in fulfillment history.', $failedQcEvents),
        'time' => 'Current period',
        'tone' => 'warning',
     ];
}
if ($avgCompletionHours !== null && $avgCompletionHours > 6) {
    $anomalies[] = [
        'title' => 'Cycle time above 6 hrs',
        'detail' => sprintf('Average completion time is %s hrs.', number_format($avgCompletionHours, 1)),
        'time' => 'Current period',
        'tone' => 'danger',
    ];
}
if ($completedPieces > 0) {
    $anomalies[] = [
        'title' => 'Output recorded',
        'detail' => sprintf('%d pieces completed across all staff.', $completedPieces),
        'time' => 'Current period',
        'tone' => 'success',
    ];
}

$automation_items = [
    [
        'title' => 'KPI computation',
        'detail' => 'Daily refresh of completion, speed, QC, and output metrics.',
        'icon' => 'fas fa-robot',
    ],
    [
        'title' => 'Anomaly detection',
        'detail' => 'Alert HR when metrics deviate beyond threshold bands.',
        'icon' => 'fas fa-bell',
    ],
    [
        'title' => 'Performance nudges',
        'detail' => 'Auto-send coaching prompts for teams below targets.',
        'icon' => 'fas fa-lightbulb',
    ],
];

$workflow_steps = [
    [
        'title' => 'Collect workforce signals',
        'detail' => 'Pull production, QC, and attendance inputs every hour.',
    ],
    [
        'title' => 'Compute KPIs',
        'detail' => 'Normalize metrics by team size, shift, and order complexity.',
    ],
    [
        'title' => 'Detect anomalies',
        'detail' => 'Flag spikes in cycle time and QC failures in real time.',
    ],
    [
        'title' => 'Recommend actions',
        'detail' => 'Surface coaching tasks, maintenance checks, and staffing shifts.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Productivity &amp; Performance Module</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .performance-kpi {
            grid-column: span 3;
        }

        .performance-kpi .metric {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .performance-kpi .metric i {
            font-size: 1.5rem;
        }

        .purpose-card,
        .workflow-card {
            grid-column: span 12;
        }

        .team-performance-card {
            grid-column: span 8;
        }

        .insights-card,
        .anomalies-card {
            grid-column: span 4;
        }

        .automation-card {
            grid-column: span 6;
        }

        .anomaly-item,
        .automation-item,
        .workflow-step,
        .insight-item {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1rem;
            background: white;
        }

        .anomaly-item + .anomaly-item,
        .automation-item + .automation-item,
        .workflow-step + .workflow-step,
        .insight-item + .insight-item {
            margin-top: 1rem;
        }

        .trend-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.85rem;
            background: var(--gray-100);
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
                <li><a href="staff_productivity_performance.php" class="nav-link active">Productivity</a></li>
                <li><a href="payroll_compensation.php" class="nav-link">Payroll</a></li>
                <li><a href="analytics_reporting.php" class="nav-link">Analytics</a></li>
                <li><a href="../auth/logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <main class="container">
        <section class="page-header">
            <div>
                <h1>Staff Productivity &amp; Performance</h1>
                <p class="text-muted">Monitor workforce efficiency, quality, and output with automated insights.</p>
            </div>
            <span class="badge badge-primary"><i class="fas fa-chart-line"></i> Module 28</span>
        </section>

        <section class="performance-grid">
            <?php foreach ($productivity_kpis as $kpi): ?>
                <div class="card performance-kpi">
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

            <div class="card purpose-card">
                <h2>Purpose</h2>
                 <p class="text-muted">
                    Give HR a single view of staff throughput, speed, and quality for proactive coaching. QC failure rates are
                    derived from fulfillment history entries marked as failed.
                </p>
            </div>

            <div class="card team-performance-card">
                 <h2>Staff productivity snapshot</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Staff member</th>
                                <th>Completion</th>
                                <th>Speed</th>
                                <th>QC failures</th>
                                <th>Output</th>
                                <th>Active</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php if (empty($staffProductivity)): ?>
                                <tr>
                                    <td colspan="6" class="text-muted">No staff productivity data available yet.</td>
                                </tr>
                                  <?php else: ?>
                                <?php foreach ($staffProductivity as $staff): ?>
                                    <?php
                                        $completion = (int) $staff['total_assigned'] > 0
                                            ? number_format(((int) $staff['completed_orders'] / (int) $staff['total_assigned']) * 100, 1) . '%'
                                            : '—';
                                        $speed = $staff['avg_completion_hours'] !== null
                                            ? number_format($staff['avg_completion_hours'], 1) . ' hrs'
                                            : '—';
                                        $qcFailures = number_format((int) $staff['qc_failures']);
                                        $output = number_format((int) $staff['completed_pieces']) . ' pcs';
                                        $activeOrders = (int) $staff['active_orders'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($staff['fullname']); ?></td>
                                        <td><?php echo $completion; ?></td>
                                        <td><?php echo $speed; ?></td>
                                        <td><?php echo $qcFailures; ?></td>
                                        <td><?php echo $output; ?></td>
                                        <td><span class="trend-pill"><?php echo $activeOrders; ?> active</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card insights-card">
                <h2>Focus insights</h2>
                <?php foreach ($focus_insights as $insight): ?>
                    <div class="insight-item">
                        <div class="d-flex align-center gap-2 mb-2">
                            <i class="<?php echo $insight['icon']; ?> text-primary"></i>
                            <strong><?php echo $insight['title']; ?></strong>
                        </div>
                        <p class="text-muted mb-0"><?php echo $insight['detail']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card anomalies-card">
                <h2>Anomaly detection</h2>
                 <?php if (empty($anomalies)): ?>
                    <p class="text-muted mb-0">No anomalies detected from current order and QC data.</p>
                <?php else: ?>
                    <?php foreach ($anomalies as $anomaly): ?>
                        <div class="anomaly-item">
                            <span class="badge badge-<?php echo $anomaly['tone']; ?> mb-2"><?php echo $anomaly['title']; ?></span>
                            <p class="mb-2"><?php echo $anomaly['detail']; ?></p>
                            <small class="text-muted"><?php echo $anomaly['time']; ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card automation-card">
                <h2>Automation</h2>
                <?php foreach ($automation_items as $automation): ?>
                    <div class="automation-item">
                        <div class="d-flex align-center gap-2 mb-2">
                            <i class="<?php echo $automation['icon']; ?> text-primary"></i>
                            <strong><?php echo $automation['title']; ?></strong>
                        </div>
                        <p class="text-muted mb-0"><?php echo $automation['detail']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card workflow-card">
                <h2>Performance workflow</h2>
                <?php foreach ($workflow_steps as $step): ?>
                    <div class="workflow-step">
                        <strong><?php echo $step['title']; ?></strong>
                        <p class="text-muted mb-0"><?php echo $step['detail']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</body>
</html>
