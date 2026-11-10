<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "test";
$port = 3306;

$koneksi = mysqli_connect(
    $host,
    $user,
    $pass,
    $db,
    $port
);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>