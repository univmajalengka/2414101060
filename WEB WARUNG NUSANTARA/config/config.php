<?php
// config.php

// Mulai session pada setiap halaman
session_start();

// --- Konfigurasi Database ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'tugaspabw_2414101060'); // Ganti dengan username DB Anda
define('DB_PASSWORD', 'ANGGA2414101060_');     // Ganti dengan password DB Anda
define('DB_NAME', 'tugaspabw_2414101060'); // Ganti dengan nama DB Anda

// Buat koneksi PDO
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    // Set mode error PDO ke exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: Tidak bisa terhubung. " . $e->getMessage());
}

// Fungsi helper untuk memeriksa login admin
function is_admin_logged_in() {
    return isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin';
}
?>