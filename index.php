<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Selamat Datang</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
        <div>
        <ul>
            <li>HOME</li>
            <li>ABOUT</li>
            <li>OPTION</li>
        </ul>
    </div>

    <h1>Selamat Datang</h1>

    <p>Silakan pilih menu yang sesuai:</p>

    <br>

    <!-- Untuk pengguna yang sudah memiliki akun -->
    <button type="button" onclick="window.location.href='login_akun.php'">
        Login Akun
    </button>

    <br><br>

    <!-- Untuk pengguna yang belum memiliki akun -->
    <button type="button" onclick="window.location.href='daftar_akun.html'">
        Daftar Akun
    </button>

</body>

</html>