<?php
session_start();
require_once '../config/db.php';
require_role('owner');

$owner_id = $_SESSION['user']['id'];
$shop_stmt = $pdo->prepare("SELECT shop_name FROM shops WHERE owner_id = ?");
$shop_stmt->execute([$owner_id]);
$shop = $shop_stmt->fetch();


$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $material_id = (int) ($_POST['material_id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $unit = sanitize($_POST['unit'] ?? '');
    $current_stock = $_POST['current_stock'] ?? '';
    $min_stock_level = $_POST['min_stock_level'] ?? '';
    $max_stock_level = $_POST['max_stock_level'] ?? '';
    $unit_cost = $_POST['unit_cost'] ?? '';
    $supplier = sanitize($_POST['supplier'] ?? '');
    $status = $_POST['status'] ?? 'active';

    $current_stock = $current_stock === '' ? 0 : (float) $current_stock;
    $min_stock_level = $min_stock_level === '' ? null : (float) $min_stock_level;
    $max_stock_level = $max_stock_level === '' ? null : (float) $max_stock_level;
    $unit_cost = $unit_cost === '' ? null : (float) $unit_cost;

    if ($action === 'delete' && $material_id > 0) {
        $delete_stmt = $pdo->prepare("DELETE FROM raw_materials WHERE id = ?");
        $delete_stmt->execute([$material_id]);
        $_SESSION['flash'] = 'Material removed successfully.';
        header('Location: raw_material_inventory.php');
        exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        if ($name === '') {
            $error = 'Material name is required.';
        } elseif ($action === 'create') {
            $create_stmt = $pdo->prepare("
                INSERT INTO raw_materials
                    (name, category, unit, current_stock, min_stock_level, max_stock_level, unit_cost, supplier, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $create_stmt->execute([
                $name,
                $category !== '' ? $category : null,
                $unit !== '' ? $unit : null,
                $current_stock,
                $min_stock_level,
                $max_stock_level,
                $unit_cost,
                $supplier !== '' ? $supplier : null,
                $status === 'inactive' ? 'inactive' : 'active',
            ]);
            $new_id = (int) $pdo->lastInsertId();
            if ($current_stock > 0 && $new_id > 0) {
                $transaction_stmt = $pdo->prepare("
                    INSERT INTO inventory_transactions (material_id, transaction_type, quantity, notes)
                    VALUES (?, 'addition', ?, ?)
                ");
                $transaction_stmt->execute([$new_id, $current_stock, 'Initial stock on creation']);
            }
            $_SESSION['flash'] = 'Material added successfully.';
            header('Location: raw_material_inventory.php');
            exit;
        } elseif ($action === 'update' && $material_id > 0) {
            $current_stmt = $pdo->prepare("SELECT current_stock FROM raw_materials WHERE id = ?");
            $current_stmt->execute([$material_id]);
            $previous = $current_stmt->fetch();
            $previous_stock = $previous ? (float) $previous['current_stock'] : $current_stock;

            $update_stmt = $pdo->prepare("
                UPDATE raw_materials
                SET name = ?,
                    category = ?,
                    unit = ?,
                    current_stock = ?,
                    min_stock_level = ?,
                    max_stock_level = ?,
                    unit_cost = ?,
                    supplier = ?,
                    status = ?
                WHERE id = ?
            ");
            $update_stmt->execute([
                $name,
                $category !== '' ? $category : null,
                $unit !== '' ? $unit : null,
                $current_stock,
                $min_stock_level,
                $max_stock_level,
                $unit_cost,
                $supplier !== '' ? $supplier : null,
                $status === 'inactive' ? 'inactive' : 'active',
                $material_id,
            ]);
            if ($previous && $previous_stock !== $current_stock) {
                $adjustment = abs($current_stock - $previous_stock);
                if ($adjustment > 0) {
                    $transaction_stmt = $pdo->prepare("
                        INSERT INTO inventory_transactions (material_id, transaction_type, quantity, notes)
                        VALUES (?, 'adjustment', ?, ?)
                    ");
                    $note = $current_stock > $previous_stock ? 'Manual increase' : 'Manual decrease';
                    $transaction_stmt->execute([$material_id, $adjustment, $note]);
                }
            }
            $_SESSION['flash'] = 'Material updated successfully.';
            header('Location: raw_material_inventory.php');
            exit;
        }
    }
}

$editing = false;
$edit_material = null;
if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    if ($edit_id > 0) {
        $edit_stmt = $pdo->prepare("SELECT * FROM raw_materials WHERE id = ?");
        $edit_stmt->execute([$edit_id]);
        $edit_material = $edit_stmt->fetch();
        $editing = (bool) $edit_material;
    }
}

$materials_stmt = $pdo->query("SELECT * FROM raw_materials ORDER BY created_at DESC");
$materials = $materials_stmt->fetchAll();

$material_count = count($materials);
$low_stock_count = 0;
$total_value = 0.0;
$out_of_stock = 0;
foreach ($materials as $material) {
    $current = (float) ($material['current_stock'] ?? 0);
    $min = $material['min_stock_level'] !== null ? (float) $material['min_stock_level'] : null;
    $unit_cost_value = $material['unit_cost'] !== null ? (float) $material['unit_cost'] : 0.0;

    if ($min !== null && $current <= $min) {
        $low_stock_count++;
    }
    if ($current <= 0) {
        $out_of_stock++;
    }
    $total_value += $current * $unit_cost_value;
}
$inventory_kpis = [
    [
        'label' => 'Materials tracked',
        'value' => $material_count,
        'note' => 'Active and inactive materials in catalog.',
        'icon' => 'fas fa-boxes-stacked',
        'tone' => 'primary',
    ],
    [
        'label' => 'Low-stock items',
        'value' => $low_stock_count,
        'note' => 'At or below minimum threshold.',
        'icon' => 'fas fa-triangle-exclamation',
        'tone' => 'warning',
    ],
    [
         'label' => 'Inventory value',
        'value' => '₱' . number_format($total_value, 2),
        'note' => 'Estimated based on unit cost.',
        'icon' => 'fas fa-receipt',
        'tone' => 'info',
    ],
    [
        'label' => 'Out of stock',
        'value' => $out_of_stock,
        'note' => 'Materials with zero on-hand.',
        'icon' => 'fas fa-box-open',
        'tone' => 'danger',
    ],
];
$transactions_stmt = $pdo->query("
    SELECT it.id, it.order_id, it.transaction_type, it.quantity, it.notes, it.created_at,
           rm.name AS material_name, rm.unit AS material_unit
    FROM inventory_transactions it
    JOIN raw_materials rm ON rm.id = it.material_id
    ORDER BY it.created_at DESC
    LIMIT 10
");
$transactions = $transactions_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Material Inventory Management Module - Owner</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .inventory-kpi {
            grid-column: span 3;
        }

       .purpose-card,
        .transactions-card {
            grid-column: span 12;
        }

        .stock-card {
            grid-column: span 8;
        }

         .form-card {
            grid-column: span 4;
        }

        .kpi-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .kpi-item i {
            font-size: 1.5rem;
        }

       .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

         .form-grid .full {
            grid-column: span 2;
        }

        .form-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

         .badge-soft {
            background: rgba(37, 99, 235, 0.1);
             color: var(--primary-600);
              border-radius: 999px;
            padding: 0.2rem 0.75rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar--compact">
        <div class="container d-flex justify-between align-center">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-store"></i> <?php echo htmlspecialchars($shop['shop_name'] ?? 'Shop Owner'); ?>
            </a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="shop_profile.php" class="nav-link">Shop Profile</a></li>
                <li><a href="manage_staff.php" class="nav-link">Staff</a></li>
                <li><a href="shop_orders.php" class="nav-link">Orders</a></li>
                <li><a href="messages.php" class="nav-link">Messages</a></li>
                <li><a href="payment_verifications.php" class="nav-link">Payments</a></li>
                <li><a href="earnings.php" class="nav-link">Earnings</a></li>
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

    <div class="container">
        <div class="dashboard-header fade-in">
            <div class="d-flex justify-between align-center">
                <div>
                    <h2>Raw Material Inventory Management</h2>
                    <p class="text-muted">Track embroidery materials with live stock visibility and automated replenishment support.</p>
                </div>
                <span class="badge badge-primary"><i class="fas fa-warehouse"></i> Module 22</span>
            </div>
        </div>

         <?php if ($flash): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="inventory-grid">
            <div class="card purpose-card">
                <div class="card-header">
                    <h3><i class="fas fa-bullseye text-primary"></i> Purpose</h3>
                </div>
                <p class="text-muted mb-0">
                    Tracks embroidery materials such as thread, stabilizers, and backing fabric while keeping stock levels aligned
                    with live production demand and supplier lead times.
                </p>
            </div>

            <?php foreach ($inventory_kpis as $kpi): ?>
                <div class="card inventory-kpi">
                    <div class="kpi-item">
                        <div>
                            <p class="text-muted mb-1"><?php echo $kpi['label']; ?></p>
                            <h3 class="mb-1"><?php echo $kpi['value']; ?></h3>
                            <small class="text-muted"><?php echo $kpi['note']; ?></small>
                        </div>
                        <span class="badge badge-<?php echo $kpi['tone']; ?>">
                            <i class="<?php echo $kpi['icon']; ?>"></i>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="card stock-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group text-primary"></i> Material Stock Levels</h3>
                    <p class="text-muted">Current on-hand quantities with reorder thresholds.</p>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Category</th>
                            <th>On-hand</th>
                            <th>Min stock</th>
                            <th>Max stock</th>
                            <th>Unit cost</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                         <?php if (!$materials): ?>
                            <tr>
                                <td colspan="8" class="text-muted">No materials added yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($materials as $material): ?>
                            <?php
                            $is_low = $material['min_stock_level'] !== null
                                && (float) $material['current_stock'] <= (float) $material['min_stock_level'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($material['name']); ?></strong>
                                    <?php if (!empty($material['unit'])): ?>
                                        <div class="text-muted">Unit: <?php echo htmlspecialchars($material['unit']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($material['category'] ?? '—'); ?></td>
                                <td><?php echo number_format((float) $material['current_stock'], 2); ?></td>
                                <td><?php echo $material['min_stock_level'] !== null ? number_format((float) $material['min_stock_level'], 2) : '—'; ?></td>
                                <td><?php echo $material['max_stock_level'] !== null ? number_format((float) $material['max_stock_level'], 2) : '—'; ?></td>
                                <td><?php echo $material['unit_cost'] !== null ? '₱' . number_format((float) $material['unit_cost'], 2) : '—'; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $material['status'] === 'inactive' ? 'secondary' : ($is_low ? 'warning' : 'success'); ?>">
                                        <?php echo $material['status'] === 'inactive' ? 'Inactive' : ($is_low ? 'Low' : 'Healthy'); ?>
                                    </span>
                                </td>
                                 <td>
                                    <div class="form-actions">
                                        <a class="btn btn-sm btn-outline" href="raw_material_inventory.php?edit=<?php echo (int) $material['id']; ?>">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Delete this material?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="material_id" value="<?php echo (int) $material['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

             <div class="card form-card">
                <div class="card-header">
                    <h3><i class="fas fa-pen-to-square text-primary"></i> <?php echo $editing ? 'Update material' : 'Add new material'; ?></h3>
                    <p class="text-muted">Manage raw materials and adjust current stock levels.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="material_id" value="<?php echo (int) $edit_material['id']; ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="full">
                            <label>Material name</label>
                            <input type="text" name="name" required value="<?php echo htmlspecialchars($edit_material['name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Category</label>
                            <input type="text" name="category" value="<?php echo htmlspecialchars($edit_material['category'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Unit</label>
                            <input type="text" name="unit" value="<?php echo htmlspecialchars($edit_material['unit'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Current stock</label>
                            <input type="number" step="0.01" name="current_stock" value="<?php echo htmlspecialchars($edit_material['current_stock'] ?? '0'); ?>">
                        </div>
                        <div>
                            <label>Min stock level</label>
                            <input type="number" step="0.01" name="min_stock_level" value="<?php echo htmlspecialchars($edit_material['min_stock_level'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Max stock level</label>
                            <input type="number" step="0.01" name="max_stock_level" value="<?php echo htmlspecialchars($edit_material['max_stock_level'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Unit cost</label>
                            <input type="number" step="0.01" name="unit_cost" value="<?php echo htmlspecialchars($edit_material['unit_cost'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Supplier</label>
                            <input type="text" name="supplier" value="<?php echo htmlspecialchars($edit_material['supplier'] ?? ''); ?>">
                        </div>
                        <div class="full">
                            <label>Status</label>
                            <select name="status">
                                <option value="active" <?php echo ($edit_material['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($edit_material['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top: 1rem;">
                        <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Update material' : 'Add material'; ?></button>
                        <?php if ($editing): ?>
                            <a class="btn btn-outline" href="raw_material_inventory.php">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card transactions-card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list text-primary"></i> Recent inventory transactions</h3>
                    <p class="text-muted">Latest adjustments and order deductions.</p>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Order</th>
                            <th>Notes</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$transactions): ?>
                            <tr>
                                <td colspan="6" class="text-muted">No transactions logged yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['material_name']); ?></td>
                                <td>
                                    <span class="badge-soft">
                                        <?php echo htmlspecialchars(ucfirst($transaction['transaction_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo number_format((float) $transaction['quantity'], 2); ?>
                                    <?php echo htmlspecialchars($transaction['material_unit'] ?? ''); ?>
                                </td>
                                <td><?php echo $transaction['order_id'] ? 'Order #' . (int) $transaction['order_id'] : '—'; ?></td>
                                <td><?php echo htmlspecialchars($transaction['notes'] ?? ''); ?></td>
                                <td><?php echo date('M d, Y g:i A', strtotime($transaction['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
