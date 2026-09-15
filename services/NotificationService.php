<?php
require_once __DIR__ . "/../repositories/NotificationRepository.php";

class NotificationService { // Primary method to trigger alerts
    public function notify(int $userId, string $message): void {
        $repo = new NotificationRepository();
        $repo->create($userId, $message);
    }
    // Returns unread count for the Dashboard badge
    public function countUnread(int $userId): int {
        $repo = new NotificationRepository();
        return $repo->countUnread($userId);
    }
    // Fetches history of alerts for the Notification page
    public function listForUser(int $userId): array {
        $repo = new NotificationRepository();
        return $repo->listForUser($userId);
    }
    // Updates all alerts to 'seen' status
    public function markAllRead(int $userId): void {
        $repo = new NotificationRepository();
        $repo->markAllRead($userId);
    }

    public function markAsRead(int $notificationId, int $userId): void {
        $repo = new NotificationRepository();
        $repo->markAsRead($notificationId, $userId);
    }
}
