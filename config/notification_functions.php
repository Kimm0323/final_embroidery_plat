<?php
// config/notification_functions.php

/**
 * Create a notification log entry.
 *
 * @param PDO $pdo
 * @param int $user_id
 * @param int|null $order_id

 * @param string $type
 * @param string $message
 * @return void
 */
function create_notification(PDO $pdo, int $user_id, ?int $order_id, string $type, string $message): void {

 $preferenceStmt = $pdo->prepare("
        SELECT enabled
        FROM notification_preferences
        WHERE user_id = ? AND event_key = ? AND channel = 'in_app'
        LIMIT 1
    ");

    $preferenceStmt->execute([$user_id, $type]);
    $preference = $preferenceStmt->fetch(PDO::FETCH_ASSOC);

    if ($preference && (int) $preference['enabled'] !== 1) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, order_id, type, message, read_at, created_at)
        VALUES (?, ?, ?, ?, NULL, NOW())
    ");

    $stmt->execute([$user_id, $order_id, $type, $message]);
}
?>



