<?php
require_once __DIR__ . "/../config/database.php";

class MessageRepository {
    public function send(int $from, int $to, string $content): bool {  // Saves a new message to the database
        $db = Database::connect();
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?,?,?)");
        $stmt->bind_param("iis", $from, $to, $content);
        return $stmt->execute();
    }
    // Fetches full chat history between two actors
    public function getConversation(int $a, int $b): array {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT * FROM messages
             WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
             ORDER BY id ASC"
        );
        $stmt->bind_param("iiii", $a, $b, $b, $a);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    // Updates message status to 'read' for the receiver
    public function markReadBetween(int $userId, int $otherId): void {
        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE messages SET is_read=1
             WHERE receiver_id=? AND sender_id=?"
        );
        $stmt->bind_param("ii", $userId, $otherId);
        $stmt->execute();
    }
}
