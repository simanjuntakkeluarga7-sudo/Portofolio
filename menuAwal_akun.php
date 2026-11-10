<?php
session_start();
include "koneksi.php";

if (isset($_POST['daftar'])) {

    $username   = trim($_POST['username']);
    $password   = $_POST['password'];
    $konfirmasi = $_POST['konfirmasi_password'];

    // Cek input kosong
    if ($username === '' || $password === '' || $konfirmasi === '') {
        echo "<script>
                alert('Semua field wajib diisi!');
                window.location='daftar_akun.html';
              </script>";
        exit;
    }

    // Cek konfirmasi password
    if ($password !== $konfirmasi) {
        echo "<script>
                alert('Password dan konfirmasi password berbeda!');
                window.location='daftar_akun.html';
              </script>";
        exit;
    }

    // Cek username
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT username FROM akun WHERE username = ?"
    );

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {

        mysqli_stmt_close($stmt);

        echo "<script>
                alert('Username sudah digunakan!');
                window.location='daftar_akun.html';
              </script>";
        exit;
    }

    mysqli_stmt_close($stmt);

    // Hash password
    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // Simpan akun
    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO akun (username, password)
         VALUES (?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $username,
        $hashedPassword
    );

    if (mysqli_stmt_execute($stmt)) {

        // AMBIL ID AKUN YANG BARU DIBUAT
        $akun_id = mysqli_insert_id($koneksi);

        // Simpan ID akun ke session
        $_SESSION['akun_id'] = $akun_id;
        $_SESSION['username'] = $username;

        mysqli_stmt_close($stmt);

        // Arahkan ke halaman data diri
        header("Location: datadiri.php");
        exit;

    } else {

        echo "Pendaftaran gagal: " . mysqli_stmt_error($stmt);
    }

    mysqli_stmt_close($stmt);
}
?>