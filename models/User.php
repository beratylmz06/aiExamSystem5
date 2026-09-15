<?php
class User {
    // Unique identifier for the user
    public int $id;
    public string $full_name;
    public string $email;      // Email address used for secure login
    public string $role;
    // Constructor to initialize user data
    public function __construct(int $id, string $full_name, string $email, string $role) {
        $this->id = $id;
        $this->full_name = $full_name;
        $this->email = $email;
        $this->role = $role;
    }
}
