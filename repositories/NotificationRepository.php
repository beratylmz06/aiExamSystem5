<?php
require_once __DIR__ . "/../config/database.php";

class NotificationRepository {
    public function create(int $userId, string $message): void {
        $db = Database::connect();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)");
        $stmt->bind_param("is", $userId, $message);
        $stmt->execute();
    }
    // Gets count of unread alerts for the Dashboard badge
    public function countUnread(int $userId): int {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row["c"] ?? 0);
    }
    // Lists all alerts for a specific Actor
    public function listForUser(int $userId): array {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    // Batch update to mark alerts as seen
    public function markAllRead(int $userId): void {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }

    // Mark single notification as read
    public function markAsRead(int $notificationId, int $userId): void {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $notificationId, $userId);
        $stmt->execute();
    }
}
