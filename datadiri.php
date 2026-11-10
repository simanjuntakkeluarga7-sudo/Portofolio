
<?php
session_start();
include "koneksi.php";

// =====================================================
// 1. CEK LOGIN
// =====================================================

if (!isset($_SESSION['akun_id'])) {
    echo "<script>
            alert('Silakan login terlebih dahulu!');
            window.location='login.php';
          </script>";
    exit;
}

$akun_id = (int) $_SESSION['akun_id'];

// Tambahan: mode edit ketika halaman dibuka melalui ?edit=1
$modeEdit = isset($_GET['edit']) && $_GET['edit'] === '1';


// =====================================================
// 2. AMBIL DATA DIRI BERDASARKAN ID AKUN
// =====================================================

$queryCek = mysqli_prepare(
    $koneksi,
    "SELECT id_datadiri, nama, tempat_kerja,
            no_idcard, gajiPokok
     FROM datadiri
     WHERE id_datadiri = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($queryCek, "i", $akun_id);
mysqli_stmt_execute($queryCek);

$hasilCek = mysqli_stmt_get_result($queryCek);
$data = mysqli_fetch_assoc($hasilCek);

mysqli_stmt_close($queryCek);


// =====================================================
// 3. PERIKSA KELENGKAPAN DATA DIRI
// =====================================================

$data_tersedia = false;

if ($data !== null) {
    $data_tersedia =
        trim((string) $data['nama']) !== '' &&
        trim((string) $data['tempat_kerja']) !== '' &&
        trim((string) $data['no_idcard']) !== '' &&
        is_numeric($data['gajiPokok']) &&
        (float) $data['gajiPokok'] > 0;
}


// =====================================================
// 4. JIKA DATA SUDAH LENGKAP
// =====================================================

// Logic awal tetap digunakan.
// Pengecualian hanya ketika pengguna memilih mode edit.
if ($data_tersedia && !$modeEdit) {
    header("Location: homeAkun.php");
    exit;
}


// =====================================================
// 5. PROSES SIMPAN DATA DIRI
// =====================================================

if (isset($_POST['simpan'])) {

    $nama = trim($_POST['nama'] ?? '');
    $tempat_kerja = trim($_POST['tempat_kerja'] ?? '');
    $no_idcard = trim($_POST['no_idcard'] ?? '');

    $gajiPokok = filter_var(
        $_POST['gajiPokok'] ?? '',
        FILTER_VALIDATE_INT
    );

    // Validasi input tetap sama.
    if (
        $nama === '' ||
        $tempat_kerja === '' ||
        $no_idcard === '' ||
        $gajiPokok === false ||
        $gajiPokok === null ||
        $gajiPokok < 1
    ) {
        echo "<script>
                alert('Semua data wajib diisi dengan benar!');
              </script>";

        // Pertahankan input jika validasi gagal.
        $data = [
            'nama' => $nama,
            'tempat_kerja' => $tempat_kerja,
            'no_idcard' => $no_idcard,
            'gajiPokok' => $_POST['gajiPokok'] ?? ''
        ];

    } else {

        // Jika data diri sudah ada, perbarui data.
        if ($data !== null) {

            $stmtSimpan = mysqli_prepare(
                $koneksi,
                "UPDATE datadiri
                 SET nama = ?,
                     tempat_kerja = ?,
                     no_idcard = ?,
                     gajiPokok = ?
                 WHERE id_datadiri = ?"
            );

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "sssii",
                $nama,
                $tempat_kerja,
                $no_idcard,
                $gajiPokok,
                $akun_id
            );

        } else {

            // Jika belum ada data, buat data baru.
            // id_datadiri tetap menggunakan akun_id.
            $stmtSimpan = mysqli_prepare(
                $koneksi,
                "INSERT INTO datadiri
                 (id_datadiri, nama, tempat_kerja,
                  no_idcard, gajiPokok)
                 VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "isssi",
                $akun_id,
                $nama,
                $tempat_kerja,
                $no_idcard,
                $gajiPokok
            );
        }

        if (mysqli_stmt_execute($stmtSimpan)) {

            mysqli_stmt_close($stmtSimpan);

            // Setelah menyimpan, kembali ke halaman home.
            header("Location: homeAkun.php");
            exit;

        } else {

            echo "Data gagal disimpan: " .
                 htmlspecialchars(
                     mysqli_stmt_error($stmtSimpan),
                     ENT_QUOTES,
                     "UTF-8"
                 );

            mysqli_stmt_close($stmtSimpan);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= $modeEdit ? 'Ubah Data Diri' : 'Data Diri' ?>
    </title>

    <link rel="stylesheet" href="style.css">

    <style>
        .data-diri-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 24px;
            border-radius: 14px;
        }

        .data-diri-container label {
            display: block;
            margin-top: 16px;
            margin-bottom: 6px;
        }

        .data-diri-container input {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
        }

        .data-diri-container button,
        .data-diri-container .tombol-kembali {
            display: inline-block;
            margin-top: 18px;
            padding: 11px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }

        .aksi-data-diri {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
    </style>
</head>

<body>

<div class="data-diri-container">

    <h2>
        <?= $modeEdit ? 'Ubah Data Diri' : 'Data Diri' ?>
    </h2>

    <?php if (!$modeEdit): ?>
        <p>
            <strong>Silakan isi data diri terlebih dahulu.</strong>
        </p>
    <?php else: ?>
        <p>Perbarui data diri kamu melalui formulir berikut.</p>
    <?php endif; ?>

    <form method="POST">

        <label for="nama">Nama</label>
        <input
            type="text"
            id="nama"
            name="nama"
            placeholder="Masukkan nama"
            value="<?= htmlspecialchars(
                (string) ($data['nama'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            required
        >

        <label for="tempat_kerja">Tempat Kerja</label>
        <input
            type="text"
            id="tempat_kerja"
            name="tempat_kerja"
            placeholder="Masukkan tempat kerja"
            value="<?= htmlspecialchars(
                (string) ($data['tempat_kerja'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            required
        >

        <label for="no_idcard">No. ID Card</label>
        <input
            type="text"
            id="no_idcard"
            name="no_idcard"
            placeholder="Masukkan nomor ID Card"
            value="<?= htmlspecialchars(
                (string) ($data['no_idcard'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            required
        >

        <label for="gajiPokok">Gaji Pokok</label>
        <input
            type="number"
            id="gajiPokok"
            name="gajiPokok"
            placeholder="Masukkan gaji pokok"
            value="<?= htmlspecialchars(
                (string) ($data['gajiPokok'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            min="1"
            step="1"
            required
        >

        <div class="aksi-data-diri">

            <button type="submit" name="simpan">
                <?= $modeEdit ? 'Simpan Perubahan' : 'Simpan Data' ?>
            </button>

            <?php if ($modeEdit): ?>

                <a
                    class="tombol-kembali"
                    href="homeAkun.php">
                    Batal
                </a>

            <?php else: ?>

                <button
                    type="button"
                    onclick="window.location.href='logout.php'">
                    Logout
                </button>

            <?php endif; ?>

        </div>

    </form>

</div>

</body>
</html>
