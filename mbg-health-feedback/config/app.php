<?php
/**
 * config/app.php
 * Konstanta dan konfigurasi global aplikasi
 */

// === Informasi Aplikasi ===
define('APP_NAME',    'MBG-Health & Feedback');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     'development'); // ganti ke 'production' saat deploy

// === Base URL ===
// Sesuaikan dengan nama folder di htdocs Laragon
define('BASE_URL', 'http://localhost/mbg-health-feedback');

// === Path Absolut ===
define('ROOT_PATH', dirname(__DIR__));

// === Role Pengguna ===
define('ROLE_ADMIN',   'admin');
define('ROLE_PETUGAS', 'petugas');
define('ROLE_USER',    'user');

// === Konfigurasi Session ===
define('SESSION_LIFETIME', 7200);       // 2 jam dalam detik
define('SESSION_NAME',     'mbg_sess'); // ← WAJIB ADA: dipakai auth/session.php

// === Konfigurasi Tampilan ===
define('ITEMS_PER_PAGE', 10);

// === Error Reporting ===
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// === Timezone ===
date_default_timezone_set('Asia/Jakarta');
