
<?php
session_start();

require_once __DIR__ . "/koneksi.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =====================================================
// 1. CEK LOGIN
// =====================================================

if (!isset($_SESSION['akun_id'])) {
    die("Anda belum login. Silakan login terlebih dahulu.");
}

$akun_id = (int) $_SESSION['akun_id'];

$pesan = "";
$jenisPesan = "info";
$hasilTampil = null;
$pengingatBerhasil = false;


// =====================================================
// 2. FUNGSI BANTUAN
// =====================================================

function h($nilai): string
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Mengubah nama bulan Indonesia menjadi angka bulan.
function bulanKeAngka(string $bulan): int
{
    $daftarBulan = [
        'januari'  => 1,
        'februari' => 2,
        'maret'    => 3,
        'april'    => 4,
        'mei'      => 5,
        'juni'     => 6,
        'juli'     => 7,
        'agustus'  => 8,
        'september'=> 9,
        'oktober'  => 10,
        'november' => 11,
        'desember' => 12
    ];

    $bulan = strtolower(trim($bulan));

    return $daftarBulan[$bulan] ?? 0;
}


// =====================================================
// 3. AMBIL DATA DIRI KARYAWAN
// =====================================================

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id, id_datadiri, nama, gajiPokok
     FROM datadiri
     WHERE id_datadiri = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $akun_id);
mysqli_stmt_execute($stmt);

$dataDiri = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

mysqli_stmt_close($stmt);

if (!$dataDiri) {
    die("Data diri karyawan tidak ditemukan.");
}

// Mengikuti pola relasi data diri pada halaman jam kerja biasa.
$id_datadiri = (int) $dataDiri['id'];
$namaKaryawan = $dataDiri['nama'];
$gajiPokok = (float) $dataDiri['gajiPokok'];

// =====================================================
// 4. AMBIL PERIODE GAJI AKTIF
// =====================================================

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_GAJIKU, periode
     FROM gaji
     WHERE id_datadiri = ?
       AND status = 'aktif'
     ORDER BY id_GAJIKU DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id_datadiri);
mysqli_stmt_execute($stmt);

$dataGaji = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

mysqli_stmt_close($stmt);

if (!$dataGaji) {
    die(
        "Tidak ada periode gaji aktif. " .
        "Buat periode terlebih dahulu di halaman jam kerja biasa."
    );
}

$idGAJIKU = (int) $dataGaji['id_GAJIKU'];
$periodeAktif = $dataGaji['periode'];

// =====================================================
// 5. PERHITUNGAN OVERTIME MENGGUNAKAN PHP
// Gaji pokok dinamis, tanpa tunjangan.
// =====================================================

function hitungDenganPHP(array $data): array
{
    $nomor_overtime = (int) ($data["overtime"] ?? 0);
    $jumlah_jam = (float) ($data["jam"] ?? 0);

    // Mengambil gaji pokok dari data diri karyawan.
    $gaji_pokok = (float) ($data["gaji_pokok"] ?? 0);

    // Upah per jam hanya berdasarkan gaji pokok.
    $upah_per_jam = $gaji_pokok / 173;

    if (!is_finite($jumlah_jam) || $jumlah_jam < 0) {
        throw new InvalidArgumentException(
            "Jumlah jam lembur tidak valid."
        );
    }

    $total_lembur = 0.0;

    // Rumus tetap mengikuti kode awal.
    if ($jumlah_jam == 8) {
        $total_lembur += 8 * 2 * $upah_per_jam;

    } elseif ($jumlah_jam == 9) {
        $total_lembur += 8 * 2 * $upah_per_jam;
        $total_lembur += 1 * 3 * $upah_per_jam;

    } elseif ($jumlah_jam == 10) {
        $total_lembur += 8 * 2 * $upah_per_jam;
        $total_lembur += 1 * 3 * $upah_per_jam;

        $jam_x3 = $jumlah_jam - 9;
        $total_lembur += $jam_x3 * 4 * $upah_per_jam;

    } else {
        $total_lembur = 0.0;
    }

    return [
        "nomor_overtime" => $nomor_overtime,
        "jumlah_jam" => $jumlah_jam,
        "gaji_pokok" => $gaji_pokok,
        "upah_per_jam" => $upah_per_jam,
        "total_lembur" => $total_lembur,
        "total" => $total_lembur
    ];
}

// =====================================================
// 6. HITUNG NILAI SATU OVERTIME
// =====================================================

function hitungNilaiOvertime(
    int $nomor,
    float $jam,
    float $gajiPokok
): float {
    $hasil = hitungDenganPHP([
        "overtime" => $nomor,
        "jam" => $jam,
        "gaji_pokok" => $gajiPokok
    ]);

    return (float) $hasil['total'];
}

// =====================================================
// 7. SIMPAN OVERTIME
// SETIAP KOLOM HANYA BOLEH DIISI SATU KALI PER PERIODE
// =====================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['simpan_overtime'])
) {
    $overtime = filter_var(
        $_POST['overtime'] ?? null,
        FILTER_VALIDATE_INT
    );

    $jam = filter_var(
        $_POST['jam'] ?? null,
        FILTER_VALIDATE_FLOAT
    );

    if (
        $overtime === false ||
        $overtime < 1 ||
        $overtime > 10
    ) {
        $pesan = "Nomor overtime hanya boleh 1 sampai 10.";
        $jenisPesan = "error";

    } elseif (
        $jam === false ||
        !is_finite((float) $jam) ||
        $jam <= 0
    ) {
        $pesan = "Jumlah jam overtime harus lebih dari 0.";
        $jenisPesan = "error";

    } elseif ($gajiPokok <= 0) {
        $pesan = "Gaji pokok belum diisi atau tidak valid pada data diri.";
        $jenisPesan = "error";

    } else {
        $jam = (float) $jam;
        $kolomOvertime = "overtime" . $overtime;

        mysqli_begin_transaction($koneksi);

        try {
            // -----------------------------------------
            // A. PASTIKAN PERIODE MASIH AKTIF
            // -----------------------------------------

            $stmtAktif = mysqli_prepare(
                $koneksi,
                "SELECT id_GAJIKU, periode
                 FROM gaji
                 WHERE id_GAJIKU = ?
                   AND id_datadiri = ?
                   AND status = 'aktif'
                 LIMIT 1
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmtAktif,
                "ii",
                $idGAJIKU,
                $id_datadiri
            );

            mysqli_stmt_execute($stmtAktif);

            $gajiAktif = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmtAktif)
            );

            mysqli_stmt_close($stmtAktif);

            if (!$gajiAktif) {
                throw new RuntimeException(
                    "Periode gaji sudah tidak aktif."
                );
            }

            // Gunakan periode terbaru dari baris yang terkunci.
            $periodeAktif = $gajiAktif['periode'];

            // -----------------------------------------
            // B. AMBIL DATA OVERTIME KARYAWAN
            // gajiovertime tetap terpisah dari tabel gaji.
            // -----------------------------------------

            $stmtCek = mysqli_prepare(
                $koneksi,
                "SELECT *
                 FROM gajiovertime
                 WHERE id_datadiri = ?
                   AND periode = ?
                   AND status = 'aktif'
                 LIMIT 1
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmtCek,
                "is",
                $id_datadiri,
                $periodeAktif
            );

            mysqli_stmt_execute($stmtCek);

            $dataOvertime = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmtCek)
            );

            mysqli_stmt_close($stmtCek);

            // -----------------------------------------
            // C. CEK APAKAH KOLOM SUDAH PERNAH DIISI
            // -----------------------------------------

            if ($dataOvertime !== null) {
                $nilaiLama = $dataOvertime[$kolomOvertime] ?? null;

                if ($nilaiLama !== null && $nilaiLama !== '') {
                    throw new RuntimeException(
                        "Overtime " . $overtime .
                        " sudah diisi untuk periode " .
                        $periodeAktif .
                        ". Nilainya tetap " . $nilaiLama .
                        " jam dan tidak dapat diubah."
                    );
                }
            }

            // -----------------------------------------
            // D. AMBIL TOTALGAJI PERIODE AKTIF
            // -----------------------------------------

            $stmtTotal = mysqli_prepare(
                $koneksi,
                "SELECT *
                 FROM totalgaji
                 WHERE id_GAJIKU = ?
                   AND periode = ?
                   AND status = 'aktif'
                 LIMIT 1
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmtTotal,
                "is",
                $idGAJIKU,
                $periodeAktif
            );

            mysqli_stmt_execute($stmtTotal);

            $dataTotal = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmtTotal)
            );

            mysqli_stmt_close($stmtTotal);

            if (!$dataTotal) {
                throw new RuntimeException(
                    "Data totalgaji aktif tidak ditemukan."
                );
            }

            // -----------------------------------------
            // E. CARI KOLOM GAJI TERAKHIR YANG TERISI
            // -----------------------------------------

            $nomorTerakhir = 0;
            $nilaiTerakhir = 0.0;

            for ($i = 1; $i <= 31; $i++) {
                $kolomGaji = "gaji" . $i;
                $nilaiKolom = $dataTotal[$kolomGaji] ?? null;

                if (
                    $nilaiKolom !== null &&
                    $nilaiKolom !== '' &&
                    (float) $nilaiKolom != 0
                ) {
                    $nomorTerakhir = $i;
                    $nilaiTerakhir = (float) $nilaiKolom;
                }
            }

            if ($nomorTerakhir === 0) {
                throw new RuntimeException(
                    "Belum ada kolom gaji yang terisi. " .
                    "Input jam kerja biasa terlebih dahulu."
                );
            }

            $kolomTerakhir = "gaji" . $nomorTerakhir;

            // -----------------------------------------
            // F. HITUNG NILAI OVERTIME MENGGUNAKAN PHP
            // Gaji pokok dikirim ke fungsi perhitungan.
            // -----------------------------------------

            $hasilBaru = hitungNilaiOvertime(
                (int) $overtime,
                $jam,
                $gajiPokok
            );

            if (!is_finite($hasilBaru) || $hasilBaru < 0) {
                throw new RuntimeException(
                    "Hasil perhitungan overtime tidak valid."
                );
            }

            // Tambahkan overtime ke total terakhir.
            $nilaiBaru = $nilaiTerakhir + $hasilBaru;

            // -----------------------------------------
            // G. SIMPAN OVERTIME
            // -----------------------------------------

            if ($dataOvertime === null) {
                $stmtInsert = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO gajiovertime
                        (
                            id_datadiri,
                            periode,
                            status,
                            `$kolomOvertime`,
                            totalOvertime
                        )
                     VALUES (?, ?, 'aktif', ?, ?)"
                );

                $totalJamOvertime = $jam;

                mysqli_stmt_bind_param(
                    $stmtInsert,
                    "isdd",
                    $id_datadiri,
                    $periodeAktif,
                    $jam,
                    $totalJamOvertime
                );

                mysqli_stmt_execute($stmtInsert);
                mysqli_stmt_close($stmtInsert);

            } else {
                $totalJamOvertime = 0.0;

                for ($i = 1; $i <= 10; $i++) {
                    $namaKolom = "overtime" . $i;

                    if ($namaKolom === $kolomOvertime) {
                        $totalJamOvertime += $jam;

                    } else {
                        $nilaiKolom = $dataOvertime[$namaKolom] ?? null;

                        if (
                            $nilaiKolom !== null &&
                            $nilaiKolom !== ''
                        ) {
                            $totalJamOvertime += (float) $nilaiKolom;
                        }
                    }
                }

                $idOvertime = (int) $dataOvertime['id_gajiOvertime_ku'];

                $stmtUpdate = mysqli_prepare(
                    $koneksi,
                    "UPDATE gajiovertime
                     SET `$kolomOvertime` = ?,
                         totalOvertime = ?
                     WHERE id_gajiOvertime_ku = ?
                       AND id_datadiri = ?
                       AND periode = ?
                       AND status = 'aktif'"
                );

                mysqli_stmt_bind_param(
                    $stmtUpdate,
                    "ddiis",
                    $jam,
                    $totalJamOvertime,
                    $idOvertime,
                    $id_datadiri,
                    $periodeAktif
                );

                mysqli_stmt_execute($stmtUpdate);
                mysqli_stmt_close($stmtUpdate);
            }

            // -----------------------------------------
            // H. TAMBAHKAN NILAI KE KOLOM GAJI TERAKHIR
            // -----------------------------------------

            $stmtSimpan = mysqli_prepare(
                $koneksi,
                "UPDATE totalgaji
                 SET `$kolomTerakhir` = ?
                 WHERE id_totalGaji = ?
                   AND id_GAJIKU = ?
                   AND periode = ?
                   AND status = 'aktif'"
            );

            $idTotalGaji = (int) $dataTotal['id_totalGaji'];

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "diis",
                $nilaiBaru,
                $idTotalGaji,
                $idGAJIKU,
                $periodeAktif
            );

            mysqli_stmt_execute($stmtSimpan);

            if (mysqli_stmt_affected_rows($stmtSimpan) < 0) {
                throw new RuntimeException(
                    "Gagal menyimpan tambahan overtime ke " .
                    $kolomTerakhir . "."
                );
            }

            mysqli_stmt_close($stmtSimpan);

            // -----------------------------------------
            // I. SIMPAN TRANSAKSI
            // -----------------------------------------

            // Transaksi database berhasil disimpan.
            mysqli_commit($koneksi);

            // Tandai penyimpanan berhasil tanpa membandingkan periode.
            $pengingatBerhasil = true;

            $hasilTampil = [
                "nomor" => $overtime,
                "jam" => $jam,
                "hasil" => $hasilBaru,
                "kolom" => $kolomTerakhir,
                "total_lama" => $nilaiTerakhir,
                "total_baru" => $nilaiBaru
            ];

            
            $pesan = "Overtime berhasil disimpan dan ditambahkan ke total gaji.";
            $jenisPesan = "success";

        } catch (Throwable $e) {

            // Batalkan perubahan database jika terjadi kesalahan.
            mysqli_rollback($koneksi);

            // Simpan rincian kesalahan ke log PHP untuk diperiksa.
            error_log(
                "Error simpan overtime: " . $e->getMessage()
            );

            // Tampilkan pesan yang mudah dipahami pengguna.
            $pesan = "Overtime gagal disimpan. Periksa konfigurasi database atau log error PHP.";
            $jenisPesan = "error";
        }
    }
}


// =====================================================
// 8. AMBIL DATA OVERTIME UNTUK DITAMPILKAN
// =====================================================

$stmtTampil = mysqli_prepare(
    $koneksi,
    "SELECT *
     FROM gajiovertime
     WHERE id_datadiri = ?
       AND periode = ?
       AND status = 'aktif'
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmtTampil,
    "is",
    $id_datadiri,
    $periodeAktif
);

mysqli_stmt_execute($stmtTampil);

$dataTersimpan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmtTampil)
);

mysqli_stmt_close($stmtTampil);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengisian Overtime</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 760px;
            margin: 30px auto;
            padding: 0 16px;
            line-height: 1.5;
            color: #222;
        }

        .panel {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 18px;
            margin: 16px 0;
        }

        label {
            display: block;
            margin-top: 12px;
        }

        select,
        input,
        button {
            box-sizing: border-box;
            max-width: 100%;
            padding: 10px;
            margin-top: 5px;
        }

        select,
        input {
            width: 100%;
        }

        button {
            cursor: pointer;
        }

        .pesan {
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
            overflow-wrap: anywhere;
            background: #eef2f7;
        }

        .success {
            background: #e7f7e7;
            color: #155724;
        }

        .error {
            background: #ffe8e8;
            color: #8b0000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        a {
            margin-right: 12px;
        }
    </style>

    <link rel="stylesheet" href="style.css">
</head>

<body>
    <h2>Pengisian Overtime</h2>

    <p>Nama karyawan: <strong><?= h($namaKaryawan) ?></strong></p>

    <p>Periode aktif: <strong><?= h($periodeAktif) ?></strong></p>

    <p>
        Gaji pokok:
        <strong>
            Rp <?= number_format($gajiPokok, 0, ',', '.') ?>
        </strong>
    </p>

    <?php if ($pesan !== ''): ?>
        <div class="pesan <?= h($jenisPesan) ?>">
            <?= h($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($hasilTampil !== null): ?>
        <section class="panel">
            <h3>Hasil Perhitungan</h3>

            <p>
                Overtime:
                <strong><?= h($hasilTampil['nomor']) ?></strong>
            </p>

            <p>
                Jumlah jam:
                <strong><?= h($hasilTampil['jam']) ?> jam</strong>
            </p>

            <p>
                Nilai overtime:
                <strong>
                    Rp <?= number_format($hasilTampil['hasil'], 0, ',', '.') ?>
                </strong>
            </p>

            <p>
                Kolom totalgaji yang diperbarui:
                <strong><?= h($hasilTampil['kolom']) ?></strong>
            </p>

            <p>
                Total sebelum:
                <strong>
                    Rp <?= number_format($hasilTampil['total_lama'], 0, ',', '.') ?>
                </strong>
            </p>

            <p>
                Total sesudah:
                <strong>
                    Rp <?= number_format($hasilTampil['total_baru'], 0, ',', '.') ?>
                </strong>
            </p>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h3>Input Jam Overtime</h3>

        <form method="post">
            <label for="overtime">Pilih overtime</label>

            <select name="overtime" id="overtime" required>
                <option value="">-- Pilih Overtime --</option>

                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <?php
                    $namaKolom = "overtime" . $i;

                    $sudahDiisi = (
                        $dataTersimpan !== null &&
                        ($dataTersimpan[$namaKolom] ?? null) !== null &&
                        ($dataTersimpan[$namaKolom] ?? '') !== ''
                    );
                    ?>

                    <option
                        value="<?= $i ?>"
                        <?= $sudahDiisi ? 'disabled' : '' ?>
                    >
                        Overtime <?= $i ?><?= $sudahDiisi ? ' — sudah diisi' : '' ?>
                    </option>
                <?php endfor; ?>
            </select>

            <label for="jam">Jumlah jam overtime</label>

            <input
                type="number"
                name="jam"
                id="jam"
                min="0.01"
                step="0.01"
                required
            >

            <p>
                <button type="submit" name="simpan_overtime">
                    Simpan Overtime
                </button>
            </p>
        </form>
    </section>

    <section class="panel">
        <h3>Data Overtime Tersimpan</h3>

        <?php if ($dataTersimpan): ?>
            <table>
                <thead>
                    <tr>
                        <th>Overtime</th>
                        <th>Jumlah Jam</th>
                    </tr>
                </thead>

                <tbody>
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <?php
                        $kolom = "overtime" . $i;
                        $nilai = $dataTersimpan[$kolom] ?? null;
                        ?>

                        <?php if ($nilai !== null && $nilai !== ''): ?>
                            <tr>
                                <td>Overtime <?= $i ?></td>
                                <td><?= h($nilai) ?> jam</td>
                            </tr>
                        <?php endif; ?>
                    <?php endfor; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Belum ada data overtime untuk periode ini.</p>
        <?php endif; ?>
    </section>

    <p>
        <a href="jamKerja_biasa.php">Kembali ke Jam Kerja Biasa</a>
        <a href="homeAkun.php">Kembali ke Home</a>
    </p>

<?php if ($pengingatBerhasil): ?>
<script>
    if (
        window.Android &&
        typeof window.Android.dataGajiBerhasilDisimpan === 'function'
    ) {
        window.Android.dataGajiBerhasilDisimpan();
    }
</script>
<?php endif; ?>

</body>
</html>
