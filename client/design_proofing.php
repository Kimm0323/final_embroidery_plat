<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_role('client');

$client_id = $_SESSION['user']['id'];
$unread_notifications = fetch_unread_notification_count($pdo, $client_id);

$success = null;
$error = null;

function proof_is_image(string $filename): bool {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ALLOWED_IMAGE_TYPES, true);
}

function proof_public_path(string $file): string {
    $file = ltrim($file, '/');
    if (str_starts_with($file, 'assets/')) {
        return '../' . $file;
    }
    return '../assets/uploads/' . $file;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $approval_id = (int) ($_POST['approval_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $approval_stmt = $pdo->prepare("
        SELECT da.id, da.order_id, da.status, o.order_number, o.shop_id, o.assigned_to, s.owner_id
        FROM design_approvals da
        JOIN orders o ON da.order_id = o.id
        JOIN shops s ON o.shop_id = s.id
        WHERE da.id = ? AND o.client_id = ?
        LIMIT 1
    ");
    $approval_stmt->execute([$approval_id, $client_id]);
    $approval = $approval_stmt->fetch();

    if (!$approval) {
        $error = 'Unable to find that proof approval request.';
    } elseif ($approval['status'] !== 'pending') {
        $error = 'This proof is no longer pending.';
    } elseif (!in_array($action, ['approve', 'revision'], true)) {
        $error = 'Invalid action selected.';
    } else {
        if ($action === 'approve') {
            $update_stmt = $pdo->prepare("
                UPDATE design_approvals
                SET status = 'approved', approved_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$approval_id]);

            $order_update_stmt = $pdo->prepare("
                UPDATE orders
                SET design_approved = 1, updated_at = NOW()
                WHERE id = ?
            ");
            $order_update_stmt->execute([$approval['order_id']]);

            $message = sprintf(
                'Design proof approved for order #%s.',
                $approval['order_number']
            );
            $notification_type = 'success';
            $success = 'Thanks! The proof has been approved.';
        } else {
            $update_stmt = $pdo->prepare("
                UPDATE design_approvals
                SET status = 'revision', revision_count = revision_count + 1, approved_at = NULL, updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$approval_id]);

            $order_update_stmt = $pdo->prepare("
                UPDATE orders
                SET design_approved = 0, revision_count = revision_count + 1, revision_requested_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $order_update_stmt->execute([$approval['order_id']]);

            $message = sprintf(
                'Revision requested for design proof on order #%s.',
                $approval['order_number']
            );
            $notification_type = 'warning';
            $success = 'Your revision request has been sent.';
        }

        create_notification($pdo, (int) $approval['owner_id'], (int) $approval['order_id'], 'proof', $message);
        if (!empty($approval['assigned_to'])) {
            create_notification($pdo, (int) $approval['assigned_to'], (int) $approval['order_id'], 'proof', $message);
        }
        create_notification($pdo, (int) $client_id, (int) $approval['order_id'], $notification_type, $message);
    }
}

$proofs_stmt = $pdo->prepare("
    SELECT da.id,
           da.order_id,
           da.design_file,
           da.status,
           da.revision_count,
           da.updated_at,
           o.order_number,
           o.service_type,
           s.shop_name
    FROM design_approvals da
    JOIN orders o ON da.order_id = o.id
    JOIN shops s ON o.shop_id = s.id
    WHERE o.client_id = ?
      AND da.status = 'pending'
    ORDER BY da.updated_at DESC
"
);
$proofs_stmt->execute([$client_id]);
$pending_proofs = $proofs_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design Proofing & Approval Module - Client</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .proof-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .proof-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.5rem;
            background: #fff;
        }

       .proof-meta {
            display: grid;
            gap: 0.35rem;
            margin-bottom: 1rem;
        }

        .proof-preview {
            margin: 1rem 0;
        }

         .proof-preview img {
            max-width: 100%;
            border-radius: var(--radius);
           border: 1px solid var(--gray-200);
        }

       .proof-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
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
                    <a href="#" class="nav-link dropdown-toggle active">
                        <i class="fas fa-layer-group"></i> Services
                    </a>
                    <div class="dropdown-menu">
                        <a href="customize_design.php" class="dropdown-item"><i class="fas fa-paint-brush"></i> Customize Design</a>
                        <a href="rate_provider.php" class="dropdown-item"><i class="fas fa-star"></i> Rate Provider</a>
                        <a href="search_discovery.php" class="dropdown-item"><i class="fas fa-compass"></i> Search &amp; Discovery</a>
                        <a href="design_proofing.php" class="dropdown-item active"><i class="fas fa-clipboard-check"></i> Design Proofing &amp; Approval</a>
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

    <div class="container">
        <div class="dashboard-header fade-in">
            <div class="d-flex justify-between align-center">
                <div>
                    <h2>Design Proofing &amp; Approval</h2>
                    <p class="text-muted">Review proofs from your shop and respond to keep production on track.</p>
                </div>
                <span class="badge badge-primary"><i class="fas fa-clipboard-check"></i> Module 9</span>
            </div>
        </div>

       <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

            <div class="proof-grid">
            <?php if (empty($pending_proofs)): ?>
                <div class="card">
                    <div class="text-center p-4">
                        <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                        <h4>No pending proofs</h4>
                        <p class="text-muted">You will see proofs here once your shop submits them.</p>
                    </div>
                </div>
                 <?php else: ?>
                <?php foreach ($pending_proofs as $proof): ?>
                    <?php
                        $proof_file = $proof['design_file'] ?? '';
                        $proof_path = $proof_file !== '' ? proof_public_path($proof_file) : '';
                        $is_image = $proof_file !== '' ? proof_is_image($proof_file) : false;
                    ?>
                    <div class="proof-card">
                        <div class="proof-meta">
                            <strong>Order #<?php echo htmlspecialchars($proof['order_number']); ?></strong>
                            <span class="text-muted"><?php echo htmlspecialchars($proof['service_type']); ?> · <?php echo htmlspecialchars($proof['shop_name']); ?></span>
                            <span class="text-muted">Revision count: <?php echo (int) $proof['revision_count']; ?></span>
                        </div>
                    <?php if ($proof_path): ?>
                            <div class="proof-preview">
                                <?php if ($is_image): ?>
                                    <img src="<?php echo htmlspecialchars($proof_path); ?>" alt="Design proof for order #<?php echo htmlspecialchars($proof['order_number']); ?>">
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($proof_path); ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-file-download"></i> View proof file
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="proof-actions">
                            <input type="hidden" name="approval_id" value="<?php echo (int) $proof['id']; ?>">
                            <button type="submit" name="action" value="approve" class="btn btn-primary">
                                <i class="fas fa-check-circle"></i> Approve
                            </button>
                            <button type="submit" name="action" value="revision" class="btn btn-outline">
                                <i class="fas fa-pen"></i> Request Revision
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
