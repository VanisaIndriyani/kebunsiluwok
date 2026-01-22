<?php
$host = 'localhost';
$user = 'root';
$pass = ''; // Default password Laragon kosong
$db   = 'db_karet';

try {
    $koneksi = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    // Set error mode ke exception agar mudah debug
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
?>
