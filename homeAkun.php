
<?php
session_start();
include "koneksi.php";

// =====================================================
// 1. CEK LOGIN
// =====================================================

if (!isset($_SESSION['akun_id'])) {
    echo "<script>
            alert('Anda belum login!');
            window.location='login.php';
          </script>";
    exit;
}

$akun_id = (int) $_SESSION['akun_id'];


// =====================================================
// 2. AMBIL DATA DIRI BERDASARKAN ID AKUN
// =====================================================

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT nama, tempat_kerja, no_idcard, gajiPokok
     FROM datadiri
     WHERE id_datadiri = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $akun_id);
mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($hasil);


// =====================================================
// 3. CEK APAKAH DATA DIRI SUDAH LENGKAP
// =====================================================

$data_lengkap = (
    $data !== null &&
    trim((string) ($data['nama'] ?? '')) !== '' &&
    trim((string) ($data['tempat_kerja'] ?? '')) !== '' &&
    trim((string) ($data['no_idcard'] ?? '')) !== '' &&
    is_numeric($data['gajiPokok'] ?? null) &&
    (float) $data['gajiPokok'] > 0
);


// =====================================================
// 4. JIKA DATA BELUM LENGKAP, ARAHKAN KE DATA DIRI
// =====================================================

if (!$data_lengkap) {
    echo "<script>
            alert('Silakan isi data diri terlebih dahulu!');
            window.location='datadiri.php';
          </script>";
    exit;
}


// =====================================================
// 5. AMBIL NAMA PENGGUNA
// =====================================================

$nama = $data['nama'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, viewport-fit=cover"
    >

    <meta name="theme-color" content="#080d17">

    <title>Akun</title>

    <link rel="stylesheet" href="style.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            width: 100%;
            min-height: 100%;
            -webkit-text-size-adjust: 100%;
        }

        body {
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;

            padding: 24px;

            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;

            font-family: Arial, Helvetica, sans-serif;
            color: #f1f5f9;

            background:
                radial-gradient(
                    circle at top right,
                    rgba(0, 119, 255, 0.18),
                    transparent 35%
                ),
                linear-gradient(
                    135deg,
                    #070b14,
                    #101827 55%,
                    #080d17
                );

            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;

            width: 280px;
            height: 280px;

            left: -100px;
            bottom: -100px;

            border-radius: 50%;

            background: rgba(0, 102, 255, 0.12);
            filter: blur(70px);

            pointer-events: none;
        }

        /* PEMBUNGKUS KONTEN */

        .account-container {
            position: relative;
            z-index: 1;

            width: 100%;
            max-width: 560px;
            margin: auto;
        }

        /* KARTU SAPAAN */

        h1 {
            width: 100%;

            padding: 60px 32px 45px;

            text-align: center;
            font-size: clamp(28px, 5vw, 42px);
            font-weight: 700;
            line-height: 1.4;

            overflow-wrap: anywhere;

            background: linear-gradient(
                145deg,
                #151f30,
                #0c1320
            );

            border: 1px solid rgba(96, 165, 250, 0.2);
            border-radius: 24px;

            box-shadow:
                0 25px 70px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.04);

            position: relative;
        }

        h1::after {
            content: "";

            position: absolute;
            left: 50%;
            bottom: 22px;

            transform: translateX(-50%);

            width: 64px;
            height: 4px;

            border-radius: 10px;

            background: linear-gradient(
                90deg,
                #1687ff,
                #55c5ff
            );

            box-shadow: 0 0 18px rgba(22, 135, 255, 0.5);
        }

        /* AREA TOMBOL */

        .account-actions {
            width: 100%;

            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));

            gap: 14px;
            margin-top: 22px;
        }

        button,
        .menu-ubah-data {
            width: 100%;
            min-width: 0;
            min-height: 50px;

            padding: 14px 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 1px solid transparent;
            border-radius: 12px;

            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.4;

            text-align: center;
            text-decoration: none;
            overflow-wrap: anywhere;

            cursor: pointer;

            transition:
                transform 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        /* LOGOUT */

        button:nth-of-type(1) {
            color: #dbeafe;
            background: #172338;
            border-color: #334866;
        }

        button:nth-of-type(1):hover {
            background: #223550;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        }

        /* UBAH DATA DIRI */

        .menu-ubah-data {
            color: #dbeafe;
            background: #172338;
            border-color: #334866;
        }

        .menu-ubah-data:hover {
            background: #223550;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        }

        /* HITUNG */

        button:nth-of-type(2) {
            grid-column: 1 / -1;

            color: #ffffff;

            background: linear-gradient(
                135deg,
                #0878f9,
                #0756c9
            );

            box-shadow: 0 8px 24px rgba(0, 102, 255, 0.22);
        }

        button:nth-of-type(2):hover {
            background: linear-gradient(
                135deg,
                #258cff,
                #0968e8
            );

            box-shadow: 0 12px 28px rgba(0, 102, 255, 0.32);
        }

        button:focus-visible,
        .menu-ubah-data:focus-visible {
            outline: 3px solid #93c5fd;
            outline-offset: 4px;
        }

        /* TABLET DAN HP */

        @media (max-width: 520px) {
            body {
                padding: 20px 16px;
            }

            .account-container {
                max-width: 100%;
            }

            h1 {
                padding: 44px 20px 38px;
                border-radius: 20px;
                font-size: clamp(25px, 7vw, 34px);
            }

            h1::after {
                bottom: 18px;
            }

            .account-actions {
                gap: 10px;
                margin-top: 18px;
            }

            button,
            .menu-ubah-data {
                min-height: 48px;
                padding: 12px 8px;
                font-size: 14px;
            }
        }

        /* HP DENGAN LAYAR KECIL */

        @media (max-width: 360px) {
            body {
                padding: 16px 12px;
            }

            h1 {
                padding: 34px 14px 32px;
                font-size: 25px;
            }

            h1::after {
                bottom: 15px;
                width: 54px;
            }

            .account-actions {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            button:nth-of-type(2) {
                grid-column: auto;
            }

            button,
            .menu-ubah-data {
                width: 100%;
                min-height: 46px;
                font-size: 14px;
            }
        }

        /* LAYAR PENDEK ATAU HP LANDSCAPE */

        @media (max-height: 500px) and (min-width: 521px) {
            body {
                justify-content: flex-start;
                padding-top: 20px;
                padding-bottom: 20px;
            }

            .account-container {
                margin-top: 0;
                margin-bottom: 0;
            }

            h1 {
                padding-top: 28px;
                padding-bottom: 28px;
            }

            h1::after {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            button,
            .menu-ubah-data {
                transition: none;
            }
        }
    </style>
</head>

<body>

    <main class="account-container">

        <h1>
            Hai, <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>!
        </h1>

        <nav class="account-actions">

            <button
                type="button"
                onclick="window.location.href='logout.php'">
                Logout
            </button>

            <a href="datadiri.php?edit=1" class="menu-ubah-data">
                Ubah Data Diri
            </a>

            <button
                type="button"
                onclick="window.location.href='jamKerja_biasa.php'">
                Hitung
            </button>

        </nav>

    </main>

</body>
</html>