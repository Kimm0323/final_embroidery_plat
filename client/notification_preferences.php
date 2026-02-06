<?php
session_start();
require_once '../config/db.php';
require_role('client');

$client_id = $_SESSION['user']['id'];
$unread_notifications = fetch_unread_notification_count($pdo, $client_id);
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = 'Your notification preferences have been updated.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Preferences</title>
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
            background-color: #cbd5f5;
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
            background-color: #4f46e5;
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
                <i class="fas fa-user"></i> Client Portal
            </a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-clipboard-list"></i> Orders
                    </a>
                    <div class="dropdown-menu">
                        <a href="place_order.php" class="dropdown-item"><i class="fas fa-plus-circle"></i> Place Order</a>
                        <a href="track_order.php" class="dropdown-item"><i class="fas fa-route"></i> Track Orders</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-layer-group"></i> Services
                    </a>
                    <div class="dropdown-menu">
                        <a href="customize_design.php" class="dropdown-item"><i class="fas fa-paint-brush"></i> Customize Design</a>
                        <a href="rate_provider.php" class="dropdown-item"><i class="fas fa-star"></i> Rate Provider</a>
                        <a href="search_discovery.php" class="dropdown-item"><i class="fas fa-compass"></i> Search &amp; Discovery</a>
                        <a href="design_proofing.php" class="dropdown-item"><i class="fas fa-clipboard-check"></i> Design Proofing &amp; Approval</a>
                        <a href="pricing_quotation.php" class="dropdown-item"><i class="fas fa-calculator"></i> Pricing &amp; Quotation</a>
                        <a href="order_management.php" class="dropdown-item"><i class="fas fa-clipboard-list"></i> Order Management</a>
                        <a href="payment_handling.php" class="dropdown-item"><i class="fas fa-hand-holding-dollar"></i> Payment Handling &amp; Release</a>
                        <a href="client_posting_community.php" class="dropdown-item"><i class="fas fa-comments"></i> Client Posting &amp; Community</a>
                    </div>
                </li>
                <li><a href="messages.php" class="nav-link">Messages</a></li>
                <li><a href="notifications.php" class="nav-link">Notifications
                    <?php if ($unread_notifications > 0): ?>
                        <span class="badge badge-danger"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </a></li>
                <li class="dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user']['fullname']); ?>
                    </a>
                    <div class="dropdown-menu">
                        <a href="../auth/logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <main class="container">
        <div class="dashboard-header">
            <h2>Notification Preferences</h2>
            <p class="text-muted">Choose the updates you want to receive during your order journey.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" class="preferences-card">
            <div class="preference-item">
                <div>
                    <h4>Order status updates</h4>
                    <p class="text-muted mb-0">Get alerts when your order moves through production.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="order_updates" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Design proof notifications</h4>
                    <p class="text-muted mb-0">Be notified when proofs are ready for approval.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="design_proofs" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Payment reminders</h4>
                    <p class="text-muted mb-0">Receive reminders for invoices and deposit requests.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="payment_reminders">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Messages from shops</h4>
                    <p class="text-muted mb-0">Stay in sync with shop updates and clarifications.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="shop_messages" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Promotions &amp; tips</h4>
                    <p class="text-muted mb-0">Optional product tips and seasonal offers.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="promotions">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preferences-footer">
                <p class="text-muted mb-0">Changes apply instantly across email and in-app alerts.</p>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save preferences</button>
            </div>
        </form>
    </main>
</body>
</html>