<?php
class Database {
    private static $conn = null;

    public static function connect() {
        if (self::$conn === null) {
            self::$conn = new mysqli("localhost", "root", "", "aiexamsystem");
            if (self::$conn->connect_error) {
                die("Database connection failed.");
            }
            self::$conn->set_charset("utf8mb4");
        }
        return self::$conn;
    }
}
