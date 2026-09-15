<?php
// MessageController manages private communication between system actors.
require_once __DIR__ . "/../services/MessageService.php";

class MessageController {
    public function listUsersForChat(string $roleToList): array { // Lists potential contacts based on their role.
        $svc = new MessageService();
        return $svc->listUsersForChat($roleToList);
    }

    public function inbox(int $userId, int $otherId): array { // Supports the goal of providing private messaging within the system
        $svc = new MessageService();
        return $svc->inbox($userId, $otherId);
    }

    public function send(int $from, int $to, string $content): bool {
        $svc = new MessageService();
        return $svc->send($from, $to, $content);
    }
}
