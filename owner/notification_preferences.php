<?php
session_start();
require_once '../config/db.php';
require_role('owner');

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = 'Notification preferences updated for your shop.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Preferences - Owner</title>
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
            background-color: #d6e0f5;
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
            background-color: #2563eb;
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
                <i class="fas fa-store"></i> Shop Owner
            </a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="shop_profile.php" class="nav-link">Shop Profile</a></li>
                <li><a href="manage_staff.php" class="nav-link">Staff</a></li>
                <li><a href="shop_orders.php" class="nav-link">Orders</a></li>
                <li><a href="reviews.php" class="nav-link">Reviews</a></li>
                <li><a href="messages.php" class="nav-link">Messages</a></li>
                <li><a href="delivery_management.php" class="nav-link">Delivery &amp; Pickup</a></li>
                <li><a href="payment_verifications.php" class="nav-link">Payments</a></li>
                <li><a href="earnings.php" class="nav-link">Earnings</a></li>
                <li><a href="analytics_reporting.php" class="nav-link">Analytics</a></li>
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
        <div class="dashboard-header">
            <h2>Notification Preferences</h2>
            <p class="text-muted">Fine-tune the alerts you receive about shop activity.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" class="preferences-card">
            <div class="preference-item">
                <div>
                    <h4>New order requests</h4>
                    <p class="text-muted mb-0">Instantly know when a client submits a new order.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="new_orders" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Payment verification alerts</h4>
                    <p class="text-muted mb-0">Get notified when deposits or balances are paid.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="payment_alerts" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Staff productivity updates</h4>
                    <p class="text-muted mb-0">Stay informed about attendance and job completion.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="staff_updates">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Customer review reminders</h4>
                    <p class="text-muted mb-0">Receive alerts when new reviews arrive.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="review_alerts" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preference-item">
                <div>
                    <h4>Weekly shop digest</h4>
                    <p class="text-muted mb-0">A curated weekly summary of orders and revenue.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="weekly_digest">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="preferences-footer">
                <p class="text-muted mb-0">Changes apply to email and in-app notifications.</p>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save preferences</button>
            </div>
        </form>
    </main>
</body>
</html>