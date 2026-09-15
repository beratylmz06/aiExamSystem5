<?php
// MessageService: Orchestrates communication logic and notifications
require_once __DIR__ . "/../repositories/MessageRepository.php";
require_once __DIR__ . "/../repositories/UserRepository.php";
require_once __DIR__ . "/../services/NotificationService.php";

class MessageService {

    // Retrieves target actors
    public function listUsersForChat(string $roleToList): array {

        $ur = new UserRepository();
        $users = $ur->listByRole($roleToList);

       
        foreach ($users as &$u) {
            if (!isset($u["full_name"])) {
                $u["full_name"] = trim(
                    ($u["first_name"] ?? "") . " " . ($u["last_name"] ?? "")
                );
            }
        }
        unset($u);

        return $users;
    }

    // Manages inbox retrieval and status updates
    public function inbox(int $userId, int $otherId): array {
        $repo = new MessageRepository();
        $repo->markReadBetween($userId, $otherId);
        return $repo->getConversation($userId, $otherId);
    }

    // Handles message delivery and alerts the recipient
    public function send(int $from, int $to, string $content): bool {

        $content = trim($content);
        if ($content === "") return false;

        $repo = new MessageRepository();
        $ok = $repo->send($from, $to, $content);

        if ($ok) {
            $ns = new NotificationService();
            $ns->notify($to, "New message received.");
        }

        return $ok;
    }
}

