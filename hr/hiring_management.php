<?php
session_start();
require_once '../config/db.php';
require_role('hr');

$hr_id = $_SESSION['user']['id'];
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
$shop_name = $hr_shop['shop_name'];
$success = null;
$error = null;

$expire_stmt = $pdo->prepare("
    UPDATE hiring_posts
    SET status = 'expired'
    WHERE shop_id = ?
      AND expires_at IS NOT NULL
      AND expires_at < NOW()
      AND status <> 'expired'
");
$expire_stmt->execute([$shop_id]);

function normalize_datetime(?string $input): ?string {
    if (!$input) {
        return null;
    }
    $timestamp = strtotime($input);
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $timestamp);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = sanitize($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $expires_at = normalize_datetime($_POST['expires_at'] ?? null);

    if (in_array($action, ['create', 'update'], true)) {
        if ($title === '') {
            $error = 'Please provide a role title.';
        } elseif (!in_array($status, ['draft', 'live', 'closed', 'expired'], true)) {
            $error = 'Invalid status selected.';
        }
    }

    if (!$error) {
        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO hiring_posts (shop_id, created_by, title, description, status, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $shop_id,
                $hr_id,
                $title,
                $description ?: null,
                $status,
                $expires_at,
            ]);
            $success = 'Hiring post created successfully.';
        } elseif ($action === 'update') {
            $post_id = (int) ($_POST['post_id'] ?? 0);
            $stmt = $pdo->prepare("
                UPDATE hiring_posts
                SET title = ?, description = ?, status = ?, expires_at = ?
                WHERE id = ? AND shop_id = ?
            ");
            $stmt->execute([
                $title,
                $description ?: null,
                $status,
                $expires_at,
                $post_id,
                $shop_id,
            ]);
            $success = 'Hiring post updated successfully.';
        } elseif ($action === 'delete') {
            $post_id = (int) ($_POST['post_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM hiring_posts WHERE id = ? AND shop_id = ?");
            $stmt->execute([$post_id, $shop_id]);
            $success = 'Hiring post deleted.';
        }
    }
}

$edit_post = null;
$edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($edit_id) {
    $edit_stmt = $pdo->prepare("
        SELECT id, title, description, status, expires_at
        FROM hiring_posts
        WHERE id = ? AND shop_id = ?
    ");
    $edit_stmt->execute([$edit_id, $shop_id]);
    $edit_post = $edit_stmt->fetch();
}

$posts_stmt = $pdo->prepare("
    SELECT id, title, description, status, expires_at, created_at
    FROM hiring_posts
    WHERE shop_id = ?
    ORDER BY created_at DESC
");
$posts_stmt->execute([$shop_id]);
$hiring_posts = $posts_stmt->fetchAll();

function status_badge_class(string $status): string {
    return match ($status) {
        'live' => 'badge-success',
        'draft' => 'badge-warning',
        'closed' => 'badge-secondary',
        'expired' => 'badge-danger',
        default => 'badge-outline',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hiring Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
          .hiring-layout {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
             margin-top: 2rem;
        }

         .hiring-form-card {
            grid-column: span 4;
        }

        .hiring-table-card {
            grid-column: span 8;
        }

        .form-group {
            margin-bottom: 1rem;
        }

         .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            background: #fff;
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

          .form-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .badge-secondary {
            background: var(--gray-400);
            color: #fff;
        }

        .hint {
            font-size: 0.85rem;
            color: var(--gray-600);
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
                <li><a href="hiring_management.php" class="nav-link active">Hiring</a></li>
                <li><a href="staff_productivity_performance.php" class="nav-link">Productivity</a></li>
                <li><a href="payroll_compensation.php" class="nav-link">Payroll</a></li>
                <li><a href="analytics_reporting.php" class="nav-link">Analytics</a></li>
                <li><a href="../auth/logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <main class="container">
        <section class="page-header">
            <div>
               <h1>Hiring Management</h1>
                <p class="text-muted">Create, publish, and track hiring posts for <?php echo htmlspecialchars($shop_name); ?>.</p>
            </div>
            <span class="badge badge-primary"><i class="fas fa-user-tie"></i> Module 6.2</span>
        </section>

        <?php if ($success): ?>
            <div class="alert alert-success mb-2"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger mb-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

             <section class="hiring-layout">
            <div class="card hiring-form-card">
                <h2><?php echo $edit_post ? 'Edit hiring post' : 'Create hiring post'; ?></h2>
                <form method="post" action="hiring_management.php<?php echo $edit_post ? '?edit=' . (int) $edit_post['id'] : ''; ?>">
                    <input type="hidden" name="action" value="<?php echo $edit_post ? 'update' : 'create'; ?>">
                    <?php if ($edit_post): ?>
                        <input type="hidden" name="post_id" value="<?php echo (int) $edit_post['id']; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label class="form-label" for="title">Role title</label>
                        <input class="form-input" id="title" name="title" required value="<?php echo htmlspecialchars($edit_post['title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-textarea" id="description" name="description" placeholder="Role overview, responsibilities, and requirements."><?php echo htmlspecialchars($edit_post['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php
                            $current_status = $edit_post['status'] ?? 'draft';
                            $statuses = ['draft' => 'Draft', 'live' => 'Live', 'closed' => 'Closed', 'expired' => 'Expired'];
                            foreach ($statuses as $value => $label):
                                $selected = $current_status === $value ? 'selected' : '';
                                echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
                            endforeach;
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="expires_at">Expires at</label>
                        <input
                            class="form-input"
                            id="expires_at"
                            name="expires_at"
                            type="datetime-local"
                            value="<?php echo $edit_post && $edit_post['expires_at'] ? date('Y-m-d\TH:i', strtotime($edit_post['expires_at'])) : ''; ?>"
                        >
                        <div class="hint">Posts automatically move to expired once the date/time passes.</div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_post ? 'Save changes' : 'Create post'; ?>
                        </button>
                        <?php if ($edit_post): ?>
                            <a href="hiring_management.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

           <div class="card hiring-table-card">
                <h2>Hiring posts</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hiring_posts)): ?>
                                <tr>
                                   <td colspan="5" class="text-muted">No hiring posts yet. Create your first role.</td>
                                </tr>
                           <?php else: ?>
                                <?php foreach ($hiring_posts as $post): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($post['title']); ?></strong>
                                            <?php if (!empty($post['description'])): ?>
                                                <div class="text-muted small"><?php echo nl2br(htmlspecialchars($post['description'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo status_badge_class($post['status']); ?>">
                                                <?php echo ucfirst($post['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                                        <td>
                                            <?php echo $post['expires_at'] ? date('M d, Y H:i', strtotime($post['expires_at'])) : '—'; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a class="btn btn-sm btn-secondary" href="hiring_management.php?edit=<?php echo (int) $post['id']; ?>">Edit</a>
                                                <form method="post" onsubmit="return confirm('Delete this hiring post?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
