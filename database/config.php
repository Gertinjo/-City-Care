<?php
// config.php - CityCare database configuration

// --- BASIC SETTINGS (change if needed) ---
$db_host = '127.0.0.1';    // or 'localhost'
$db_name = 'citycare_db';
$db_user = 'root';
$db_pass = '';             // XAMPP default: empty password
$db_charset = 'utf8mb4';

// --- PDO CONNECTION HELPER ---
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // use globals defined above
        global $db_host, $db_name, $db_user, $db_pass, $db_charset;

        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        } catch (PDOException $e) {
            // For development: show clear error
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
