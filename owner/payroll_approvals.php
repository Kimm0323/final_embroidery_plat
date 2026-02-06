<?php
session_start();
require_once '../config/db.php';
require_role('owner');

$owner_name = htmlspecialchars($_SESSION['user']['fullname'] ?? 'Owner');
$message = null;
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    $period_start = sanitize($_POST['pay_period_start'] ?? '');
    $period_end = sanitize($_POST['pay_period_end'] ?? '');

    if (empty($period_start) || empty($period_end)) {
        $message = 'Please select a valid pay period to approve.';
        $message_type = 'warning';
    } else {
        $update_stmt = $pdo->prepare("
            UPDATE payroll
            SET status = 'paid', paid_at = NOW()
            WHERE pay_period_start = ? AND pay_period_end = ? AND status = 'pending'
        ");
        $update_stmt->execute([$period_start, $period_end]);

        if ($update_stmt->rowCount() > 0) {
            $message = "Approved payroll for {$period_start} to {$period_end}.";
        } else {
            $message = 'No pending payroll entries found for the selected period.';
            $message_type = 'warning';
        }
    }
}

$periods_stmt = $pdo->query("
    SELECT pay_period_start, pay_period_end,
           COUNT(*) AS staff_count,
           COALESCE(SUM(net_salary), 0) AS total_net,
           SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count
    FROM payroll
    GROUP BY pay_period_start, pay_period_end
    ORDER BY pay_period_start DESC
");
$pay_periods = $periods_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Approvals</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .approval-card {
            margin-top: 2rem;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container d-flex justify-between align-center">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-store"></i> <?php echo $owner_name; ?>
            </a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="shop_profile.php" class="nav-link">Shop Profile</a></li>
                <li><a href="manage_staff.php" class="nav-link">Staff</a></li>
                <li><a href="shop_orders.php" class="nav-link">Orders</a></li>
                <li><a href="reviews.php" class="nav-link">Reviews</a></li>
                <li><a href="messages.php" class="nav-link">Messages</a></li>
                <li><a href="payment_verifications.php" class="nav-link">Payments</a></li>
                <li><a href="earnings.php" class="nav-link">Earnings</a></li>
                <li><a href="payroll_approvals.php" class="nav-link active">Payroll</a></li>
                <li class="dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user']['fullname']); ?>
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
        <section class="page-header">
            <div>
                <h1>Payroll Approvals</h1>
                <p class="text-muted">Approve payroll drafts after HR completes the pay period build.</p>
            </div>
        </section>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-3">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card approval-card">
            <h2>Pay period approval queue</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Entries</th>
                            <th>Total Net</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pay_periods)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">No payroll periods have been generated yet.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pay_periods as $period): ?>
                                <?php
                                    $is_paid = (int) $period['paid_count'] === (int) $period['staff_count'];
                                    $status_label = $is_paid ? 'Paid' : 'Pending approval';
                                ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($period['pay_period_start']); ?> -
                                        <?php echo htmlspecialchars($period['pay_period_end']); ?>
                                    </td>
                                    <td><?php echo (int) $period['staff_count']; ?> staff</td>
                                    <td>₱<?php echo number_format((float) $period['total_net'], 2); ?></td>
                                    <td>
                                        <span class="badge badge-outline status-pill">
                                            <i class="fas <?php echo $is_paid ? 'fa-check-circle text-success' : 'fa-clock text-warning'; ?>"></i>
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($is_paid): ?>
                                            <span class="text-muted">Approved</span>
                                        <?php else: ?>
                                            <form method="post">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="pay_period_start" value="<?php echo htmlspecialchars($period['pay_period_start']); ?>">
                                                <input type="hidden" name="pay_period_end" value="<?php echo htmlspecialchars($period['pay_period_end']); ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-check"></i> Approve payroll
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
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