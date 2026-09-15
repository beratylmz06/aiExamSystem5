<?php
require_once __DIR__ . "/../services/NotificationService.php";

class NotificationController {        // Get count of unread alerts for the user
    public function countUnread(int $userId): int {
        $svc = new NotificationService();
        return $svc->countUnread($userId);
    }
   
    public function listForUser(int $userId): array {
        $svc = new NotificationService();
        return $svc->listForUser($userId);
    }

    public function markAllRead(int $userId): void {
        $svc = new NotificationService();            
        $svc->markAllRead($userId);
    }

    public function markAsRead(int $notificationId, int $userId): void {
        $svc = new NotificationService();
        $svc->markAsRead($notificationId, $userId);
    }
}
