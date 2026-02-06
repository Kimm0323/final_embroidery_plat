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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
$design_json = $_POST['design_json'] ?? '';

if ($project_id <= 0 || $design_json === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing project_id or design_json']);
    exit();
}

$client_id = (int) $_SESSION['user']['id'];

try {
    $pdo->beginTransaction();

    $order_stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND client_id = ?");
    $order_stmt->execute([$project_id, $client_id]);
    $order = $order_stmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        exit();
    }

    $version_stmt = $pdo->prepare("SELECT COALESCE(MAX(version_no), 0) FROM design_versions WHERE project_id = ? FOR UPDATE");
    $version_stmt->execute([$project_id]);
    $next_version = (int) $version_stmt->fetchColumn() + 1;

    $png_preview = null;
    if (isset($_FILES['png_preview']) && $_FILES['png_preview']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['png_preview']['error'] !== UPLOAD_ERR_OK) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Failed to upload preview']);
            exit();
        }

        $file = $_FILES['png_preview'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['png'];

        if (!in_array($file_ext, $allowed_exts, true)) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Preview must be a PNG image']);
            exit();
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        if ($mime_type !== 'image/png') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid preview MIME type']);
            exit();
        }

        $upload_dir = '../assets/uploads/design_previews/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = $project_id . '_v' . $next_version . '_' . uniqid('preview_', true) . '.png';
        $target_file = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to store preview']);
            exit();
        }

        $png_preview = $filename;
    }

    $insert_stmt = $pdo->prepare("
        INSERT INTO design_versions (project_id, version_no, design_json, png_preview, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert_stmt->execute([$project_id, $next_version, $design_json, $png_preview, $client_id]);

    $version_id = (int) $pdo->lastInsertId();
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'version_id' => $version_id,
        'version_no' => $next_version,
        'png_preview' => $png_preview,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save design']);
}