<?php
session_start();
require_once '../config/db.php';
require_role('hr');

$hr_id = $_SESSION['user']['id'] ?? null;
$hr_name = htmlspecialchars($_SESSION['user']['fullname'] ?? 'HR Lead');

$hr_stmt = $pdo->prepare("
    SELECT se.shop_id, s.shop_name
    FROM shop_staffs se
    JOIN shops s ON se.shop_id = s.id
    WHERE se.user_id = ? AND se.staff_role = 'hr' AND se.status = 'active'
");
$hr_stmt->execute([$hr_id]);
$hr_shop = $hr_stmt->fetch();

if (!$hr_shop) {
    die('You are not assigned to any shop as HR. Please contact your shop owner.');
}

$shop_id = (int) $hr_shop['shop_id'];

$open_posts_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM hiring_posts WHERE shop_id = ? AND status IN ('live','draft')");
$open_posts_count_stmt->execute([$shop_id]);
$open_posts_count = (int) $open_posts_count_stmt->fetchColumn();

$open_posts_stmt = $pdo->prepare("
    SELECT id, title, status, expires_at, created_at
    FROM hiring_posts
    WHERE shop_id = ? AND status IN ('live','draft')
    ORDER BY created_at DESC
    LIMIT 5
");
$open_posts_stmt->execute([$shop_id]);
$open_posts = $open_posts_stmt->fetchAll();

$pending_payroll_count_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM payroll p
    JOIN staffs st ON p.staff_id = st.id
    JOIN shop_staffs ss ON st.user_id = ss.user_id
    WHERE ss.shop_id = ? AND ss.status = 'active' AND p.status = 'pending'
");
$pending_payroll_count_stmt->execute([$shop_id]);
$pending_payroll_count = (int) $pending_payroll_count_stmt->fetchColumn();

$pending_payroll_sum_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(p.net_salary), 0) AS total_pending
    FROM payroll p
    JOIN staffs st ON p.staff_id = st.id
    JOIN shop_staffs ss ON st.user_id = ss.user_id
    WHERE ss.shop_id = ? AND ss.status = 'active' AND p.status = 'pending'
");
$pending_payroll_sum_stmt->execute([$shop_id]);
$pending_payroll_total = (float) $pending_payroll_sum_stmt->fetchColumn();

$pending_payroll_stmt = $pdo->prepare("
    SELECT p.id, p.pay_period_start, p.pay_period_end, p.net_salary, u.fullname
    FROM payroll p
    JOIN staffs st ON p.staff_id = st.id
    JOIN users u ON st.user_id = u.id
    JOIN shop_staffs ss ON u.id = ss.user_id
    WHERE ss.shop_id = ? AND ss.status = 'active' AND p.status = 'pending'
    ORDER BY p.pay_period_end DESC
    LIMIT 5
");
$pending_payroll_stmt->execute([$shop_id]);
$pending_payroll = $pending_payroll_stmt->fetchAll();

$low_stock_stmt = $pdo->query("
    SELECT name, current_stock, min_stock_level, unit
    FROM raw_materials
    WHERE status = 'active'
      AND min_stock_level IS NOT NULL
      AND current_stock <= min_stock_level
    ORDER BY current_stock ASC
    LIMIT 5
");
$low_stock_items = $low_stock_stmt->fetchAll();

$active_staff_count_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM shop_staffs
    WHERE shop_id = ? AND staff_role = 'staff' AND status = 'active'
");
$active_staff_count_stmt->execute([$shop_id]);
$active_staff_count = (int) $active_staff_count_stmt->fetchColumn();

$completed_orders_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM order_status_history osh
    JOIN shop_staffs ss ON osh.staff_id = ss.user_id
    WHERE ss.shop_id = ?
      AND osh.status = 'completed'
      AND osh.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$completed_orders_stmt->execute([$shop_id]);
$completed_orders = (int) $completed_orders_stmt->fetchColumn();

$avg_completed_per_staff = $active_staff_count > 0 ? $completed_orders / $active_staff_count : 0;

$top_staff_stmt = $pdo->prepare("
    SELECT u.fullname, COUNT(*) AS completed_count
    FROM order_status_history osh
    JOIN users u ON osh.staff_id = u.id
    JOIN shop_staffs ss ON u.id = ss.user_id
    WHERE ss.shop_id = ?
      AND osh.status = 'completed'
      AND osh.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY u.id
    ORDER BY completed_count DESC
    LIMIT 3
");
$top_staff_stmt->execute([$shop_id]);
$top_staff = $top_staff_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - <?php echo htmlspecialchars($hr_shop['shop_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hr-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .hr-summary-card {
            grid-column: span 3;
        }

        .hr-summary-card .metric {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .hr-summary-card .metric i {
            font-size: 1.5rem;
        }

        .hr-panel {
            grid-column: span 6;
        }

        .hr-panel--full {
            grid-column: span 12;
        }

        .list-item {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1rem;
            background: white;
        }

        .list-item + .list-item {
            margin-top: 1rem;
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge--success {
            background: rgba(25, 135, 84, 0.12);
            color: #198754;
        }

        .badge--warning {
            background: rgba(255, 193, 7, 0.18);
            color: #a07800;
        }

        .badge--danger {
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
        }

        .table-lite {
            width: 100%;
            border-collapse: collapse;
        }

        .table-lite th,
        .table-lite td {
            padding: 0.65rem 0.5rem;
            border-bottom: 1px solid var(--gray-200);
            text-align: left;
            font-size: 0.9rem;
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
                <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
                <li><a href="hiring_management.php" class="nav-link">Hiring</a></li>
                <li><a href="create_staff.php" class="nav-link">Create Staff</a></li>
                <li><a href="staff_productivity_performance.php" class="nav-link">Productivity</a></li>
                <li><a href="payroll_compensation.php" class="nav-link">Payroll</a></li>
                <li><a href="analytics_reporting.php" class="nav-link">Analytics</a></li>
                <li><a href="../auth/logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <div>
                <h2>HR Dashboard</h2>
                <p class="text-muted">Operational pulse for <?php echo htmlspecialchars($hr_shop['shop_name']); ?>.</p>
            </div>
        </div>

        <div class="hr-dashboard-grid">
            <div class="card hr-summary-card">
                <h3>Open hiring posts</h3>
                <div class="metric">
                    <div>
                        <h2><?php echo $open_posts_count; ?></h2>
                        <p class="text-muted">Live or draft roles</p>
                    </div>
                    <i class="fas fa-briefcase text-primary"></i>
                </div>
            </div>
            <div class="card hr-summary-card">
                <h3>Payroll pending</h3>
                <div class="metric">
                    <div>
                        <h2><?php echo $pending_payroll_count; ?></h2>
                        <p class="text-muted">₱<?php echo number_format($pending_payroll_total, 2); ?> awaiting approval</p>
                    </div>
                    <i class="fas fa-file-invoice-dollar text-warning"></i>
                </div>
            </div>
            <div class="card hr-summary-card">
                <h3>Low-stock alerts</h3>
                <div class="metric">
                    <div>
                        <h2><?php echo count($low_stock_items); ?></h2>
                        <p class="text-muted">Materials below minimum</p>
                    </div>
                    <i class="fas fa-triangle-exclamation text-danger"></i>
                </div>
            </div>
            <div class="card hr-summary-card">
                <h3>30-day output</h3>
                <div class="metric">
                    <div>
                        <h2><?php echo $completed_orders; ?></h2>
                        <p class="text-muted">Completed orders</p>
                    </div>
                    <i class="fas fa-chart-line text-success"></i>
                </div>
            </div>

            <div class="card hr-panel">
                <h3>Open hiring posts</h3>
                <?php if (empty($open_posts)): ?>
                    <p class="text-muted">No open posts right now.</p>
                <?php else: ?>
                    <?php foreach ($open_posts as $post): ?>
                        <div class="list-item">
                            <div class="d-flex justify-between align-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($post['title']); ?></strong>
                                    <p class="text-muted mb-0">Posted <?php echo date('M d, Y', strtotime($post['created_at'])); ?></p>
                                </div>
                                <?php
                                    $badge_class = $post['status'] === 'live' ? 'badge--success' : 'badge--warning';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst(htmlspecialchars($post['status'])); ?>
                                </span>
                            </div>
                            <p class="text-muted mb-0">Expires: <?php echo $post['expires_at'] ? date('M d, Y', strtotime($post['expires_at'])) : 'No expiry'; ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card hr-panel">
                <h3>Payroll pending approval</h3>
                <?php if (empty($pending_payroll)): ?>
                    <p class="text-muted">No pending payroll approvals.</p>
                <?php else: ?>
                    <?php foreach ($pending_payroll as $item): ?>
                        <div class="list-item">
                            <div class="d-flex justify-between align-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['fullname']); ?></strong>
                                    <p class="text-muted mb-0">
                                        <?php echo date('M d, Y', strtotime($item['pay_period_start'])); ?> -
                                        <?php echo date('M d, Y', strtotime($item['pay_period_end'])); ?>
                                    </p>
                                </div>
                                <span class="badge badge--warning">₱<?php echo number_format((float) $item['net_salary'], 2); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card hr-panel">
                <h3>Low-stock alerts</h3>
                <?php if (empty($low_stock_items)): ?>
                    <p class="text-muted">No low-stock alerts. Materials are healthy.</p>
                <?php else: ?>
                    <table class="table-lite">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Stock</th>
                                <th>Minimum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_items as $material): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                                    <td><?php echo number_format((float) $material['current_stock'], 2); ?> <?php echo htmlspecialchars($material['unit'] ?? ''); ?></td>
                                    <td><?php echo number_format((float) $material['min_stock_level'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card hr-panel">
                <h3>Staff performance snapshot</h3>
                <p class="text-muted">
                    Active staff: <?php echo $active_staff_count; ?> · Avg completions per staff (30 days):
                    <?php echo number_format($avg_completed_per_staff, 1); ?>
                </p>
                <?php if (empty($top_staff)): ?>
                    <p class="text-muted">No recent completion data for staff performance yet.</p>
                <?php else: ?>
                    <?php foreach ($top_staff as $staff): ?>
                        <div class="list-item">
                            <div class="d-flex justify-between align-center">
                                <strong><?php echo htmlspecialchars($staff['fullname']); ?></strong>
                                <span class="badge badge--success"><?php echo (int) $staff['completed_count']; ?> completions</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>