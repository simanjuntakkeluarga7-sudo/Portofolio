<?php

session_start();
include "koneksi.php";

if (isset($_POST['login'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    // Cari username di database
    $query = mysqli_query(
        $koneksi,
        "SELECT * FROM akun WHERE username='$username'"
    );

    // Cek apakah username ditemukan
    if (mysqli_num_rows($query) == 1) {

        $data = mysqli_fetch_assoc($query);

        // Cek password dengan password_verify()
        if (password_verify($password, $data['password'])) {

            
            // Simpan ID akun ke session
            $_SESSION['akun_id'] = $data['akun_id'];
            $_SESSION['username'] = $data['username'];

            $akun_id = $data['akun_id'];

            // Cek apakah sudah mengisi data diri
            $cek_data_diri = mysqli_query(
                $koneksi,
                "SELECT * FROM datadiri WHERE id_datadiri ='$akun_id'"
            );

            if (mysqli_num_rows($cek_data_diri) == 1) {

                // Sudah mengisi data diri
                header("Location: homeAkun.php");
                exit;

            } else {

                // Belum mengisi data diri
                header("Location: datadiri.php");
                exit;
            }

        } else {

            // Password salah
            echo "<script>
                    alert('Password salah!');
                    window.location='login_akun.php';
                  </script>";
            exit;
        }

    } else {

        // Username tidak ditemukan
        echo "<script>
                alert('Username tidak ditemukan!');
                window.location='login_akun.php';
              </script>";
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>login</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <form action="login_akun.php" method="POST">

        <label>Username</label><br>
        <input type="text" name="username" required>

        <br><br>

        <label>Password</label><br>
        <input type="password" name="password" required>

        <br><br>

        <button type="submit" name="login">Login</button>

    </form><br>
    <h4>belum punya akun? silahkan daftar disini!!</h4>
    <button onclick="window.location.href='daftar_akun.html'">
    Daftar
</body>
</html>