<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SESSION['user']['role'] !== 'client') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$project_id = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
$requested_version = isset($_GET['version_no']) ? (int) $_GET['version_no'] : null;

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing project_id']);
    exit();
}

$client_id = (int) $_SESSION['user']['id'];

try {
    $order_stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND client_id = ?");
    $order_stmt->execute([$project_id, $client_id]);
    $order = $order_stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        exit();
    }

    $versions_stmt = $pdo->prepare("
        SELECT id, version_no, png_preview, created_at
        FROM design_versions
        WHERE project_id = ?
        ORDER BY version_no DESC
    ");
    $versions_stmt->execute([$project_id]);
    $versions = $versions_stmt->fetchAll();

    $selected = null;
    if (!empty($versions)) {
        if ($requested_version !== null) {
            $selected_stmt = $pdo->prepare("
                SELECT id, version_no, design_json, png_preview, created_at
                FROM design_versions
                WHERE project_id = ? AND version_no = ?
                LIMIT 1
            ");
            $selected_stmt->execute([$project_id, $requested_version]);
        } else {
            $selected_stmt = $pdo->prepare("
                SELECT id, version_no, design_json, png_preview, created_at
                FROM design_versions
                WHERE project_id = ?
                ORDER BY version_no DESC
                LIMIT 1
            ");
            $selected_stmt->execute([$project_id]);
        }

        $selected = $selected_stmt->fetch() ?: null;
    }

    echo json_encode([
        'project_id' => $project_id,
        'versions' => $versions,
        'selected' => $selected,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load design versions']);
}