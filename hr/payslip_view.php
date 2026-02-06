<?php
session_start();
require_once '../config/db.php';
require_role('hr');

$payroll_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, u.fullname, u.email, s.department, s.position
    FROM payroll p
    JOIN staffs s ON s.id = p.staff_id
    JOIN users u ON u.id = s.user_id
    WHERE p.id = ?
");
$stmt->execute([$payroll_id]);
$payslip = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip View</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payslip-card {
            max-width: 720px;
            margin: 2rem auto;
        }

        .payslip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .payslip-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .payslip-grid .item {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--radius);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar--compact">
        <div class="container d-flex justify-between align-center">
            <a href="payroll_compensation.php" class="navbar-brand">
                <i class="fas fa-arrow-left"></i> Back to Payroll
            </a>
        </div>
    </nav>

    <main class="container">
        <div class="card payslip-card">
            <?php if (!$payslip): ?>
                <div class="empty-state">Payslip not found.</div>
            <?php else: ?>
                <div class="payslip-header">
                    <div>
                        <h2 class="mb-1">Payslip</h2>
                        <p class="text-muted mb-0">Pay period: <?php echo htmlspecialchars($payslip['pay_period_start']); ?> - <?php echo htmlspecialchars($payslip['pay_period_end']); ?></p>
                    </div>
                    <span class="badge badge-outline"><?php echo ucfirst($payslip['status']); ?></span>
                </div>

                <div class="payslip-grid">
                    <div class="item">
                        <p class="text-muted mb-1">Employee</p>
                        <strong><?php echo htmlspecialchars($payslip['fullname']); ?></strong>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($payslip['email']); ?></p>
                    </div>
                    <div class="item">
                        <p class="text-muted mb-1">Department / Position</p>
                        <strong><?php echo htmlspecialchars($payslip['department'] ?? ''); ?></strong>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($payslip['position'] ?? ''); ?></p>
                    </div>
                    <div class="item">
                        <p class="text-muted mb-1">Base Salary</p>
                        <strong>₱<?php echo number_format((float) $payslip['basic_salary'], 2); ?></strong>
                    </div>
                    <div class="item">
                        <p class="text-muted mb-1">Allowances</p>
                        <strong>₱<?php echo number_format((float) $payslip['allowances'], 2); ?></strong>
                    </div>
                    <div class="item">
                        <p class="text-muted mb-1">Deductions</p>
                        <strong>₱<?php echo number_format((float) $payslip['deductions'], 2); ?></strong>
                    </div>
                </div>

                <div class="total-row">
                    <span>Net Pay</span>
                    <span>₱<?php echo number_format((float) $payslip['net_salary'], 2); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>