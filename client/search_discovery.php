<?php
session_start();
require_once '../config/db.php';
require_role('client');

$client_id = $_SESSION['user']['id'];
$unread_notifications = fetch_unread_notification_count($pdo, $client_id);

function calculate_dss_score(array $shop): float
{
    $rating = (float) ($shop['rating'] ?? 0);
    $total_orders = (int) ($shop['total_orders'] ?? 0);
    $rating_score = min(1, max(0, $rating / 5)) * 70;
    $volume_score = min(50, $total_orders) / 50 * 30;

 return round($rating_score + $volume_score, 1);
}

$shops_stmt = $pdo->prepare("
    SELECT s.id,
        s.shop_name,
        s.shop_description,
        s.address,
        s.rating,
        s.total_orders,
        s.logo,
        COUNT(hp.id) AS live_posts
    FROM shops s
    LEFT JOIN hiring_posts hp
        ON hp.shop_id = s.id
        AND hp.status = 'live'
    WHERE s.status = 'active'
    GROUP BY s.id
    ORDER BY s.rating DESC, s.total_orders DESC, s.shop_name ASC
");
$shops_stmt->execute();
$shops = $shops_stmt->fetchAll();

$posts_stmt = $pdo->prepare("
    SELECT hp.id,
        hp.title,
        hp.description,
        hp.expires_at,
        hp.created_at,
        s.shop_name,
        s.logo,
        s.address
    FROM hiring_posts hp
    JOIN shops s ON s.id = hp.shop_id
    WHERE hp.status = 'live'
        AND s.status = 'active'
    ORDER BY hp.created_at DESC
");
$posts_stmt->execute();
$hiring_posts = $posts_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search & Discovery Module - Client</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .discovery-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .overview-card {
            grid-column: span 12;
        }

        .shops-card {
            grid-column: span 7;
        }

        .hiring-card {
            grid-column: span 5;
        }

         .shop-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

       .shop-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1rem;
            background: var(--bg-primary);
            display: flex;
            gap: 1rem;
        }

        .shop-logo {
            width: 64px;
            height: 64px;
            border-radius: var(--radius);
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

       .shop-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .shop-meta {
            flex: 1;
        }

         .shop-meta h4 {
            margin-bottom: 0.25rem;
        }

        .shop-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .dss-score {
            background: var(--primary-100);
            color: var(--primary-700);
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
        }

         .hiring-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

         .hiring-item {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1rem;
            background: white;
        }

         .hiring-item h4 {
            margin-bottom: 0.5rem;
        }

        .hiring-meta {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.85rem;
        }

        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
                        <a href="search_discovery.php" class="dropdown-item active"><i class="fas fa-compass"></i> Search & Discovery</a>
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

    <div class="container">
        <div class="dashboard-header fade-in">
            <div class="d-flex justify-between align-center">
                <div>
                    <h2>Search, Discovery & Hiring Visibility</h2>
                    <p class="text-muted">Find the right shop, check availability, and explore hiring opportunities.</p>
                </div>
                <span class="badge badge-primary"><i class="fas fa-compass"></i> Module 6</span>
            </div>
        </div>

        <div class="discovery-grid">
            <div class="card overview-card">
                <div class="card-header">
                    <h3><i class="fas fa-bullseye text-primary"></i> Discovery Snapshot</h3>
                </div>
                <p class="text-muted mb-0">
                     Browse active embroidery shops, review DSS scores, and explore live hiring opportunities.
                </p>
            </div>

            <div class="card functions-card">
                <div class="card-header">
                     <h3><i class="fas fa-store text-primary"></i> Active Shops</h3>
                    <p class="text-muted">Ranked by ratings and recent activity.</p>
                </div>
            </div>

             <div class="shop-list">
                    <?php if (empty($shops)): ?>
                        <p class="text-muted mb-0">No active shops are available right now.</p>
                    <?php else: ?>
                        <?php foreach ($shops as $shop): ?>
                            <div class="shop-card">
                                <div class="shop-logo">
                                    <?php if (!empty($shop['logo'])): ?>
                                        <img src="../assets/uploads/logos/<?php echo htmlspecialchars($shop['logo']); ?>" alt="<?php echo htmlspecialchars($shop['shop_name']); ?> logo">
                                    <?php else: ?>
                                        <i class="fas fa-store text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="shop-meta">
                                    <h4><?php echo htmlspecialchars($shop['shop_name']); ?></h4>
                                    <div class="text-muted small">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($shop['address'] ?: 'Address not provided'); ?>
                                    </div>
                                    <p class="text-muted text-truncate-2 mb-1">
                                        <?php echo htmlspecialchars($shop['shop_description'] ?: 'No shop description available.'); ?>
                                    </p>
                                    <div class="shop-badges">
                                        <span class="dss-score">DSS Score: <?php echo number_format(calculate_dss_score($shop), 1); ?></span>
                                        <span class="badge badge-light">
                                            <i class="fas fa-star text-warning"></i>
                                            <?php echo number_format((float) $shop['rating'], 1); ?>
                                        </span>
                                        <span class="badge badge-light">
                                            <?php echo (int) $shop['total_orders']; ?> orders
                                        </span>
                                        <span class="badge badge-light">
                                            <?php echo (int) $shop['live_posts']; ?> hiring posts
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

             <div class="card hiring-card">
                <div class="card-header">
                     <h3><i class="fas fa-briefcase text-primary"></i> Live Hiring Posts</h3>
                    <p class="text-muted">Opportunities from shops actively hiring.</p>
                </div>
                 <div class="hiring-list">
                    <?php if (empty($hiring_posts)): ?>
                        <p class="text-muted mb-0">No live hiring posts right now.</p>
                    <?php else: ?>
                        <?php foreach ($hiring_posts as $post): ?>
                            <div class="hiring-item">
                                <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                <div class="hiring-meta text-muted">
                                    <span>
                                        <i class="fas fa-store"></i>
                                        <?php echo htmlspecialchars($post['shop_name']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($post['address'] ?: 'Address not provided'); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        Posted <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                    </span>
                                    <?php if (!empty($post['expires_at'])): ?>
                                        <span>
                                            <i class="fas fa-hourglass-end"></i>
                                            Expires <?php echo date('M d, Y', strtotime($post['expires_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted text-truncate-2 mb-0">
                                    <?php echo htmlspecialchars($post['description'] ?: 'No description available.'); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
