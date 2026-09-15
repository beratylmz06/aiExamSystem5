<?php
require_once __DIR__ . "/../repositories/UserRepository.php";
require_once __DIR__ . "/../repositories/NotificationRepository.php";
require_once __DIR__ . "/../config/database.php";

class AuthController {

     
      // LOGIN
        
    public function login(string $email, string $password, ?string $requiredRole = null): string|bool {

        $repo = new UserRepository();
        $user = $repo->findByEmail($email);

        if (!$user) return false;
        if (!password_verify($password, $user["password"])) return false;

        // Role Check
        if ($requiredRole && $user["role"] !== $requiredRole) {
            // User exists but wrong role for this login page
            return "role_mismatch";
        }

        $_SESSION["user"] = [
            "id"             => (int)$user["id"],
            "first_name"     => $user["first_name"],
            "last_name"      => $user["last_name"],
            "student_number" => $user["student_number"] ?? null,
            "email"          => $user["email"],
            "role"           => $user["role"]
        ];

        return true;
    }

    // REGISTER
       
    public function register(array $data): bool {

        $db = Database::connect();

        
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $data["email"]);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            return false;
        }

        
        $studentNumber = ($data["role"] === "student")
            ? ($data["student_number"] ?? null)
            : null;

        /* Kullanıcı ekleme */
        $stmt = $db->prepare("
            INSERT INTO users
            (first_name, last_name, student_number, email, password, role)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $hash = password_hash($data["password"], PASSWORD_DEFAULT);

        $stmt->bind_param(
            "ssssss",
            $data["first_name"],
            $data["last_name"],
            $studentNumber,
            $data["email"],
            $hash,
            $data["role"]
        );

        $ok = $stmt->execute();
        if (!$ok) return false;   

         
          // STUDENT REGISTER → TEACHER NOTIFICATION
            
        if ($data["role"] === "student") {
            $userRepo  = new UserRepository();
            $teachers  = $userRepo->listByRole("teacher");

            $notifRepo = new NotificationRepository();

            $message = "A new student has registered: "
                     . $data["first_name"] . " " . $data["last_name"];

            foreach ($teachers as $t) {
                $notifRepo->create((int)$t["id"], $message);
            }
        }

        return true;
    }
}
