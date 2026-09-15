<?php
require_once __DIR__ . "/../config/database.php";

class UserRepository {

    public function findByEmail(string $email): ?array {
        $db = Database::connect();

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    
    public function listByRole(string $role): array {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT 
                id,
                first_name,
                last_name,
                student_number,
                email,
                role,
                risk_status
            FROM users
            WHERE role = ?
            ORDER BY id ASC
        ");

        $stmt->bind_param("s", $role);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    
    public function updateRiskStatus(int $userId, string $status): void {
        $db = Database::connect();

        $stmt = $db->prepare(
            "UPDATE users SET risk_status = ? WHERE id = ?"
        );
        $stmt->bind_param("si", $status, $userId);
        $stmt->execute();
    }
    
    /**
     * Find user by ID
     */
    public function findById(int $userId): ?array {
        $db = Database::connect();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }
}
