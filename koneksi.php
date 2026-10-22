<?php
/**
 * koneksi.php - Konfigurasi database & helper umum
 * Ganti nilai di bawah sesuai panel InfinityFree
 * (menu "MySQL Databases" -> "Account Details").
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

// ---------- KONFIGURASI DATABASE ----------
$db_host = 'sql313.infinityfree.com';   // contoh: sql123.infinityfree.com
$db_user = 'if0_43214607';              // username MySQL
$db_pass = 'Reportitebis123';    // password akun hosting/MySQL
$db_name = 'if0_43214607_lab_report';   // nama database lengkap dengan prefix

// Ubah ke true SEMENTARA untuk melihat pesan error asli, lalu kembalikan ke false.
define('DEBUG', false);

// ---------- AKUN ADMIN (GANTI SEBELUM DI-UPLOAD!) ----------
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'adminganteng123');

// ---------- UPLOAD ----------
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024); // 2 MB

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $koneksi = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $koneksi->set_charset('utf8mb4');
    $koneksi->query("SET time_zone = '+07:00'");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die('Koneksi database gagal. Periksa pengaturan di koneksi.php.'
        . (DEBUG ? '<br><small>Detail: ' . htmlspecialchars($e->getMessage()) . '</small>' : ''));
}

// ---------- HELPER ----------
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid($token) {
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

function is_admin() {
    return !empty($_SESSION['admin_login']);
}

function set_flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
