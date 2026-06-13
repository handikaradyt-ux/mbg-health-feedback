<?php
/**
 * config/database.php
 * Konfigurasi koneksi database menggunakan PDO
 */

define('DB_HOST',    '127.0.0.1');
define('DB_NAME',    'db_mbg');
define('DB_USER',    'root');
define('DB_PASS',    'hndk060607');          // ← sesuaikan password MySQL Laragon kamu
define('DB_CHARSET', 'utf8mb4');

/**
 * Singleton PDO connection.
 * Nama fungsi: getDBConnection() — konsisten di seluruh proyek.
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn     = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Jangan expose detail error di production
            die('Koneksi database gagal. Hubungi administrator.');
        }
    }

    return $pdo;
}
