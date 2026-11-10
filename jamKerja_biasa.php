<?php
session_start();

require_once __DIR__ . "/koneksi.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =====================================================
// 1. CEK LOGIN
// =====================================================
if (!isset($_SESSION['akun_id'])) {
    header("Location: login.php");
    exit;
}

$akun_id = (int) $_SESSION['akun_id'];

// =====================================================
// 2. AMBIL DATA DIRI DAN GAJI POKOK
// Catatan: kode ini mengikuti struktur yang diberikan:
// datadiri.id adalah ID yang direlasikan ke gaji.id_datadiri,
// sedangkan datadiri.id_datadiri digunakan untuk mencari akun.
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
$hasilDataDiri = mysqli_stmt_get_result($stmt);
$dataDiri = mysqli_fetch_assoc($hasilDataDiri);
mysqli_stmt_close($stmt);

if (!$dataDiri) {
    die("Data diri tidak ditemukan. Silakan lengkapi data diri terlebih dahulu.");
}

$id_datadiri = (int) $dataDiri['id'];
$namaKaryawan = $dataDiri['nama'];
$gajiPokok = (float) ($dataDiri['gajiPokok'] ?? 0);

// =====================================================
// 3. FUNGSI BANTUAN
// =====================================================
function h($nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

function rupiah($nilai): string
{
    return 'Rp' . number_format((float) $nilai, 0, ',', '.');
}

function bulanKeAngka($bulan): int
{
    $daftarBulan = [
        'Januari' => 1, 'Februari' => 2, 'Maret' => 3,
        'April' => 4, 'Mei' => 5, 'Juni' => 6,
        'Juli' => 7, 'Agustus' => 8, 'September' => 9,
        'Oktober' => 10, 'November' => 11, 'Desember' => 12
    ];
    return $daftarBulan[$bulan] ?? 0;
}

function namaBulan($angka): string
{
    $daftarBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $daftarBulan[(int) $angka] ?? '';
}

function jumlahHariDalamBulan($periode): int
{
    $bagian = explode(' ', trim((string) $periode));
    if (count($bagian) !== 2) {
        return 31;
    }

    $bulan = bulanKeAngka($bagian[0]);
    $tahun = (int) $bagian[1];

    if ($bulan < 1 || $tahun < 1) {
        return 31;
    }

    return cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
}

// Aturan lembur mengikuti kode awal:
// 8 jam atau kurang = Rp0;
// 9 jam = 1,5 x upah per jam;
// 10 jam atau lebih = 1,5 x upah per jam + 2 x upah per jam
// untuk setiap jam setelah jam ke-9.
function hitungLemburHarian(float $jam, float $gajiPokok): float
{
    $upahPerJam = $gajiPokok / 173;

    if ($jam <= 8) {
        return 0;
    }

    if ($jam < 10) {
        return $upahPerJam * 1.5;
    }

    return ($upahPerJam * 1.5) + (($jam - 9) * 2 * $upahPerJam);
}

function ambilPeriodeAktif($koneksi, int $id_datadiri): ?array
{
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
    $hasil = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($hasil);
    mysqli_stmt_close($stmt);

    return $data ?: null;
}

// Ambil nilai akumulasi terakhir dari totalgaji.
// Nilai gajiN diasumsikan berisi akumulasi berjalan.
function ambilTotalAkumulasi($koneksi, int $idGAJIKU): float
{
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT *
         FROM totalgaji
         WHERE id_GAJIKU = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $idGAJIKU);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($hasil);
    mysqli_stmt_close($stmt);

    if (!$data) {
        return 0;
    }

    $total = 0;
    for ($i = 1; $i <= 31; $i++) {
        $kolom = 'gaji' . $i;
        if (isset($data[$kolom]) && $data[$kolom] !== null && $data[$kolom] !== '') {
            $total = (float) $data[$kolom];
        }
    }

    return $total;
}

// =====================================================
// 4. PESAN HALAMAN
// =====================================================
$pesan = '';
$jenisPesan = 'info';
$pengingatBerhasil = false;

if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'selesai':
            $pesan = 'Periode gaji berhasil diselesaikan. Riwayat periode tetap tersimpan.';
            $jenisPesan = 'success';
            break;
        case 'hapus_gaji':
            $pesan = 'Data jam kerja pada periode aktif berhasil dikosongkan.';
            $jenisPesan = 'success';
            break;
        case 'hapus_total':
            $pesan = 'Akumulasi total gaji pada periode aktif berhasil dikosongkan.';
            $jenisPesan = 'success';
            break;
    }
}

// =====================================================
// 5. BUAT PERIODE GAJI BARU
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_periode'])) {
    $bulan = trim($_POST['bulan'] ?? '');
    $tahun = (int) ($_POST['tahun'] ?? 0);
    $nomorBulan = bulanKeAngka($bulan);

    if ($nomorBulan < 1 || $tahun < 2000 || $tahun > 2100) {
        $pesan = 'Bulan atau tahun tidak valid.';
        $jenisPesan = 'error';
    } elseif (ambilPeriodeAktif($koneksi, $id_datadiri) !== null) {
        $pesan = 'Masih ada periode aktif. Selesaikan periode tersebut terlebih dahulu.';
        $jenisPesan = 'error';
    } else {
        $periodeBaru = $bulan . ' ' . $tahun;

        try {
            mysqli_begin_transaction($koneksi);

            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO gaji (id_datadiri, periode, status)
                 VALUES (?, ?, 'aktif')"
            );
            mysqli_stmt_bind_param($stmt, "is", $id_datadiri, $periodeBaru);
            mysqli_stmt_execute($stmt);
            $idGAJIKU = (int) mysqli_insert_id($koneksi);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO totalgaji (id_GAJIKU, periode, status)
                 VALUES (?, ?, 'aktif')"
            );
            mysqli_stmt_bind_param($stmt, "is", $idGAJIKU, $periodeBaru);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($koneksi);
            header("Location: jamKerja_biasa.php");
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = 'Gagal membuat periode. Pastikan kolom id_datadiri, id_GAJIKU, periode, dan status tersedia.';
            $jenisPesan = 'error';
        }
    }
}

// =====================================================
// 6. SIMPAN JAM KERJA HARIAN DAN TAMBAHKAN UANG LEMBUR
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_jam'])) {
    $periodeAktifPost = ambilPeriodeAktif($koneksi, $id_datadiri);

    if (!$periodeAktifPost) {
        $pesan = 'Belum ada periode aktif. Buat periode terlebih dahulu.';
        $jenisPesan = 'error';
    } else {
        $idGAJIKU = (int) $periodeAktifPost['id_GAJIKU'];
        $periodePost = $periodeAktifPost['periode'];
        $hari = (int) ($_POST['hari'] ?? 0);
        $jam = filter_var($_POST['jam'] ?? null, FILTER_VALIDATE_FLOAT);
        $jumlahHariPost = jumlahHariDalamBulan($periodePost);

        if ($hari < 1 || $hari > $jumlahHariPost) {
            $pesan = 'Tanggal tidak sesuai dengan bulan pada periode.';
            $jenisPesan = 'error';
        } elseif ($jam === false || $jam < 0 || $jam > 24) {
            $pesan = 'Jumlah jam harus berupa angka antara 0 dan 24.';
            $jenisPesan = 'error';
        } else {
            $kolomHari = 'hari' . $hari;

            try {
                mysqli_begin_transaction($koneksi);

                $stmt = mysqli_prepare(
                    $koneksi,
                    "SELECT `$kolomHari`
                     FROM gaji
                     WHERE id_GAJIKU = ?
                       AND id_datadiri = ?
                       AND status = 'aktif'
                     FOR UPDATE"
                );
                mysqli_stmt_bind_param($stmt, "ii", $idGAJIKU, $id_datadiri);
                mysqli_stmt_execute($stmt);
                $hasil = mysqli_stmt_get_result($stmt);
                $dataHari = mysqli_fetch_assoc($hasil);
                mysqli_stmt_close($stmt);

                if (!$dataHari) {
                    throw new Exception('Periode aktif tidak ditemukan.');
                }

                if ($dataHari[$kolomHari] !== null && $dataHari[$kolomHari] !== '') {
                    mysqli_rollback($koneksi);
                    $pesan = 'Jam kerja pada tanggal tersebut sudah tersimpan dan tidak dapat ditimpa.';
                    $jenisPesan = 'error';
                } else {
                    $stmt = mysqli_prepare(
                        $koneksi,
                        "UPDATE gaji
                         SET `$kolomHari` = ?
                         WHERE id_GAJIKU = ?
                           AND id_datadiri = ?
                           AND status = 'aktif'"
                    );
                    mysqli_stmt_bind_param($stmt, "dii", $jam, $idGAJIKU, $id_datadiri);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    $uangLemburHariIni = hitungLemburHarian((float) $jam, $gajiPokok);

                    // Tambahkan uang lembur ke totalgaji sebagai akumulasi berjalan.
                    if ($uangLemburHariIni > 0) {
                        $stmt = mysqli_prepare(
                            $koneksi,
                            "SELECT *
                             FROM totalgaji
                             WHERE id_GAJIKU = ?
                               AND status = 'aktif'
                             LIMIT 1
                             FOR UPDATE"
                        );
                        mysqli_stmt_bind_param($stmt, "i", $idGAJIKU);
                        mysqli_stmt_execute($stmt);
                        $hasilTotal = mysqli_stmt_get_result($stmt);
                        $dataTotal = mysqli_fetch_assoc($hasilTotal);
                        mysqli_stmt_close($stmt);

                        if (!$dataTotal) {
                            throw new Exception('Data totalgaji untuk periode ini tidak ditemukan.');
                        }

                        $nomorKolomTerakhir = 0;
                        $totalSebelumnya = 0;

                        for ($i = 1; $i <= 31; $i++) {
                            $namaKolom = 'gaji' . $i;
                            if (isset($dataTotal[$namaKolom])
                                && $dataTotal[$namaKolom] !== null
                                && $dataTotal[$namaKolom] !== '') {
                                $nomorKolomTerakhir = $i;
                                $totalSebelumnya = (float) $dataTotal[$namaKolom];
                            }
                        }

                        $nomorKolomBaru = $nomorKolomTerakhir + 1;
                        if ($nomorKolomBaru <= 31) {
                            $kolomTotal = 'gaji' . $nomorKolomBaru;
                            $totalBaru = $totalSebelumnya + $uangLemburHariIni;

                            $stmt = mysqli_prepare(
                                $koneksi,
                                "UPDATE totalgaji
                                 SET `$kolomTotal` = ?
                                 WHERE id_GAJIKU = ?
                                   AND status = 'aktif'"
                            );
                            mysqli_stmt_bind_param($stmt, "di", $totalBaru, $idGAJIKU);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                        } else {
                            throw new Exception('Kolom akumulasi gaji1 sampai gaji31 sudah penuh.');
                        }
                    }

                    
                    mysqli_commit($koneksi);

                    // Beri tahu aplikasi Android bahwa penyimpanan berhasil.
                    // Penjadwalan notifikasi 24 jam diatur oleh aplikasi Android.
                    $pengingatBerhasil = true;

                    $pesan = 'Jam kerja berhasil disimpan. Uang lembur hari ini: '
                        . rupiah($uangLemburHariIni) . '.';
                    $jenisPesan = 'success';

                }
            } catch (Throwable $e) {
                try {
                    mysqli_rollback($koneksi);
                } catch (Throwable $ignored) {
                }
                if ($pesan === '') {
                    $pesan = 'Gagal menyimpan jam kerja. Pastikan struktur tabel dan relasi database sesuai.';
                    $jenisPesan = 'error';
                }
            }
        }
    }
}

// =====================================================
// 7. TOMBOL HAPUS DATA GAJI
// Mengosongkan hari1-hari31 pada gaji periode aktif.
// Baris periode tidak dihapus.
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_data_gaji'])) {
    $periodeHapus = ambilPeriodeAktif($koneksi, $id_datadiri);

    if (!$periodeHapus) {
        $pesan = 'Tidak ada periode aktif untuk dihapus.';
        $jenisPesan = 'error';
    } else {
        $idHapus = (int) $periodeHapus['id_GAJIKU'];

        try {
            mysqli_begin_transaction($koneksi);

            $setHari = [];
            for ($i = 1; $i <= 31; $i++) {
                $setHari[] = "`hari{$i}` = NULL";
            }

            $sql = "UPDATE gaji SET " . implode(', ', $setHari) .
                   " WHERE id_GAJIKU = ? AND id_datadiri = ? AND status = 'aktif'";

            $stmt = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $idHapus, $id_datadiri);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($koneksi);
            header("Location: jamKerja_biasa.php?status=hapus_gaji");
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = 'Gagal menghapus data jam kerja.';
            $jenisPesan = 'error';
        }
    }
}

// =====================================================
// 8. TOMBOL HAPUS TOTAL GAJI
// Mengosongkan gaji1-gaji31 pada totalgaji periode aktif.
// Baris totalgaji dan periode tidak dihapus.
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_total_gaji'])) {
    $periodeHapus = ambilPeriodeAktif($koneksi, $id_datadiri);

    if (!$periodeHapus) {
        $pesan = 'Tidak ada periode aktif untuk dihapus.';
        $jenisPesan = 'error';
    } else {
        $idHapus = (int) $periodeHapus['id_GAJIKU'];

        try {
            mysqli_begin_transaction($koneksi);

            $setTotal = [];
            for ($i = 1; $i <= 31; $i++) {
                $setTotal[] = "`gaji{$i}` = NULL";
            }

            $sql = "UPDATE totalgaji SET " . implode(', ', $setTotal) .
                   " WHERE id_GAJIKU = ? AND status = 'aktif'";

            $stmt = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmt, "i", $idHapus);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($koneksi);
            header("Location: jamKerja_biasa.php?status=hapus_total");
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = 'Gagal menghapus akumulasi total gaji. Periksa relasi tabel totalgaji.';
            $jenisPesan = 'error';
        }
    }
}

// =====================================================
// 9. SELESAIKAN PERIODE AKTIF
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selesaikan_periode'])) {
    $periodeAktifPost = ambilPeriodeAktif($koneksi, $id_datadiri);

    if (!$periodeAktifPost) {
        $pesan = 'Tidak ada periode aktif untuk diselesaikan.';
        $jenisPesan = 'error';
    } else {
        $idGAJIKU = (int) $periodeAktifPost['id_GAJIKU'];

        try {
            mysqli_begin_transaction($koneksi);

            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE gaji
                 SET status = 'selesai'
                 WHERE id_GAJIKU = ?
                   AND id_datadiri = ?
                   AND status = 'aktif'"
            );
            mysqli_stmt_bind_param($stmt, "ii", $idGAJIKU, $id_datadiri);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE totalgaji
                 SET status = 'selesai'
                 WHERE id_GAJIKU = ?"
            );
            mysqli_stmt_bind_param($stmt, "i", $idGAJIKU);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($koneksi);
            header("Location: jamKerja_biasa.php?status=selesai");
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = 'Gagal menyelesaikan periode.';
            $jenisPesan = 'error';
        }
    }
}

// =====================================================
// 10. AMBIL DATA PERIODE AKTIF, TOTAL, DAN RIWAYAT HARIAN
// =====================================================
$periodeAktif = ambilPeriodeAktif($koneksi, $id_datadiri);
$idGAJIKU = null;
$periode = '';
$jamHariTersimpan = [];
$jumlahHari = 31;
$totalJamPeriodeAktif = 0;
$totalLemburPeriodeAktif = 0;
$riwayatJamKerja = [];
$totalAkumulasiPeriode = 0;
$totalPenghasilanPeriode = $gajiPokok;

if ($periodeAktif) {
    $idGAJIKU = (int) $periodeAktif['id_GAJIKU'];
    $periode = $periodeAktif['periode'];
    $jumlahHari = jumlahHariDalamBulan($periode);

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT *
         FROM gaji
         WHERE id_GAJIKU = ?
           AND id_datadiri = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "ii", $idGAJIKU, $id_datadiri);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    $jamHariTersimpan = mysqli_fetch_assoc($hasil) ?: [];
    mysqli_stmt_close($stmt);

    for ($i = 1; $i <= $jumlahHari; $i++) {
        $kolom = 'hari' . $i;
        $nilaiJam = $jamHariTersimpan[$kolom] ?? null;

        if ($nilaiJam !== null && $nilaiJam !== '') {
            $jam = (float) $nilaiJam;
            $uangLembur = hitungLemburHarian($jam, $gajiPokok);

            $riwayatJamKerja[] = [
                'tanggal' => $i,
                'jam' => $jam,
                'uang_lembur' => $uangLembur
            ];

            $totalJamPeriodeAktif += $jam;
            $totalLemburPeriodeAktif += $uangLembur;
        }
    }

    // Ambil akumulasi pada totalgaji. Ini dapat mencakup tambahan
    // dari halaman overtime jika halaman tersebut menggunakan tabel yang sama.
    $totalAkumulasiPeriode = ambilTotalAkumulasi($koneksi, $idGAJIKU);

    // Total penghasilan ditampilkan sebagai gaji pokok + akumulasi totalgaji.
    $totalPenghasilanPeriode = $gajiPokok + $totalAkumulasiPeriode;
}

// =====================================================
// 11. RIWAYAT PERIODE SELESAI
// =====================================================
$riwayatPeriodeSelesai = [];

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT *
     FROM gaji
     WHERE id_datadiri = ?
       AND status = 'selesai'
     ORDER BY id_GAJIKU DESC"
);
mysqli_stmt_bind_param($stmt, "i", $id_datadiri);
mysqli_stmt_execute($stmt);
$hasilRiwayat = mysqli_stmt_get_result($stmt);

while ($barisRiwayat = mysqli_fetch_assoc($hasilRiwayat)) {
    $idRiwayat = (int) $barisRiwayat['id_GAJIKU'];
    $periodeRiwayat = $barisRiwayat['periode'];
    $jumlahHariRiwayat = jumlahHariDalamBulan($periodeRiwayat);
    $dataHariRiwayat = [];
    $totalJamRiwayat = 0;
    $totalLemburRiwayat = 0;

    for ($i = 1; $i <= $jumlahHariRiwayat; $i++) {
        $kolom = 'hari' . $i;
        $nilaiJam = $barisRiwayat[$kolom] ?? null;

        if ($nilaiJam !== null && $nilaiJam !== '') {
            $jam = (float) $nilaiJam;
            $uangLembur = hitungLemburHarian($jam, $gajiPokok);

            $dataHariRiwayat[] = [
                'tanggal' => $i,
                'jam' => $jam,
                'uang_lembur' => $uangLembur
            ];

            $totalJamRiwayat += $jam;
            $totalLemburRiwayat += $uangLembur;
        }
    }

    $totalAkumulasiRiwayat = ambilTotalAkumulasi($koneksi, $idRiwayat);

    $riwayatPeriodeSelesai[] = [
        'id_GAJIKU' => $idRiwayat,
        'periode' => $periodeRiwayat,
        'hari' => $dataHariRiwayat,
        'total_hari' => count($dataHariRiwayat),
        'total_jam' => $totalJamRiwayat,
        'total_lembur' => $totalLemburRiwayat,
        'total_akumulasi' => $totalAkumulasiRiwayat,
        'total_penghasilan' => $gajiPokok + $totalAkumulasiRiwayat
    ];
}
mysqli_stmt_close($stmt);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jam Kerja Biasa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #080d18;
            color: #e8eef9;
            min-height: 100vh;
            padding: 24px;
        }
        .container { width: 100%; max-width: 1150px; margin: auto; }
        .header { margin-bottom: 26px; }
        .header h1 { font-size: 29px; margin-bottom: 10px; color: #fff; }
        .header p, .muted { color: #9baac4; line-height: 1.6; }
        .panel {
            background: #111a2b; border: 1px solid #25334d;
            border-radius: 16px; padding: 24px; margin-bottom: 22px;
            box-shadow: 0 10px 30px rgba(0,0,0,.18);
        }
        .panel h2 { font-size: 20px; margin-bottom: 18px; color: #f5f8ff; }
        .info-grid {
            display: grid; grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 15px; margin-bottom: 20px;
        }
        .info-card {
            background: #0b1323; border: 1px solid #25334d;
            border-radius: 12px; padding: 17px; min-width: 0;
        }
        .info-card span { display: block; font-size: 13px; color: #9baac4; margin-bottom: 9px; }
        .info-card strong { font-size: 19px; overflow-wrap: anywhere; color: #66b5ff; }
        .info-card.highlight { border-color: #1676cb; background: linear-gradient(135deg,#102844,#0b1323); }
        .info-card.highlight strong { color: #72e0aa; font-size: 22px; }
        label { display: block; margin-bottom: 8px; color: #c8d5eb; font-size: 14px; }
        input, select {
            width: 100%; padding: 12px 13px; background: #080f1e;
            color: #fff; border: 1px solid #34445f; border-radius: 9px;
            outline: none; font-size: 15px;
        }
        input:focus, select:focus { border-color: #3398ff; box-shadow: 0 0 0 3px rgba(51,152,255,.12); }
        .form-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 16px; }
        .form-group { margin-bottom: 16px; min-width: 0; }
        .button {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 12px 17px; border: none; border-radius: 9px;
            background: #1685f8; color: #fff; text-decoration: none;
            font-weight: bold; font-size: 14px; cursor: pointer; transition: .2s ease;
        }
        .button:hover { background: #0871dc; transform: translateY(-1px); }
        .button-danger { background: #d94358; }
        .button-danger:hover { background: #b92e43; }
        .button-secondary { background: #253650; }
        .button-secondary:hover { background: #314767; }
        .button-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        .button-row form { margin: 0; }
        .message { padding: 14px 16px; border-radius: 10px; margin-bottom: 20px; line-height: 1.5; }
        .message.success { background: #103426; border: 1px solid #226947; color: #8df0b9; }
        .message.error { background: #3a1821; border: 1px solid #743041; color: #ffabb8; }
        .message.info { background: #102943; border: 1px solid #24527a; color: #a9d7ff; }
        .table-wrap { width: 100%; overflow-x: auto; border: 1px solid #283750; border-radius: 11px; }
        table { width: 100%; border-collapse: collapse; min-width: 590px; }
        th, td { padding: 13px 15px; text-align: left; border-bottom: 1px solid #25334b; }
        th { background: #17253b; color: #b8d7ff; font-size: 13px; white-space: nowrap; }
        td { color: #e0e8f6; font-size: 14px; }
        tbody tr:hover { background: #162239; }
        tfoot td { background: #14223a; color: #fff; font-weight: bold; }
        .money { color: #65dba0; font-weight: bold; white-space: nowrap; }
        details { background: #0b1323; border: 1px solid #283750; border-radius: 12px; margin-top: 14px; overflow: hidden; }
        summary { padding: 17px; cursor: pointer; color: #e5efff; font-weight: bold; }
        details[open] summary { border-bottom: 1px solid #283750; background: #15233a; }
        .details-content { padding: 17px; }
        .empty { padding: 18px; text-align: center; color: #9baac4; background: #0b1323; border-radius: 10px; border: 1px dashed #34445f; }
        .footer { text-align: center; color: #72819a; font-size: 12px; margin: 28px 0 8px; }
        @media (max-width: 700px) {
            body { padding: 14px; }
            .panel { padding: 17px; }
            .header h1 { font-size: 24px; }
            .info-grid, .form-grid { grid-template-columns: 1fr; }
            .button-row { flex-direction: column; }
            .button-row .button, .button-row form, .button-row form .button { width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <header class="header">
        <h1>Jam Kerja Biasa</h1>
        <p>
            Halo, <?= h($namaKaryawan); ?>.
            Catat jam kerja harian dan lihat total penghasilan dari gaji pokok serta akumulasi uang lembur.
        </p>
    </header>

    <?php if ($pesan !== ''): ?>
        <div class="message <?= h($jenisPesan); ?>"><?= h($pesan); ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>Informasi Karyawan</h2>
        <div class="info-grid">
            <div class="info-card">
                <span>Nama Karyawan</span>
                <strong><?= h($namaKaryawan); ?></strong>
            </div>
            <div class="info-card">
                <span>Gaji Pokok</span>
                <strong><?= rupiah($gajiPokok); ?></strong>
            </div>
            <div class="info-card">
                <span>Upah Per Jam</span>
                <strong><?= rupiah($gajiPokok / 173); ?></strong>
            </div>
        </div>
    </section>

    <?php if (!$periodeAktif): ?>
        <section class="panel">
            <h2>Buat Periode Gaji</h2>
            <p class="muted" style="margin-bottom:18px;">
                Belum ada periode aktif. Pilih bulan dan tahun untuk memulai pencatatan jam kerja.
            </p>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="bulan">Bulan</label>
                        <select name="bulan" id="bulan" required>
                            <option value="">Pilih bulan</option>
                            <?php
                            $bulanSekarang = (int) date('n');
                            $tahunSekarang = (int) date('Y');
                            for ($i = 1; $i <= 12; $i++):
                                $nama = namaBulan($i);
                            ?>
                                <option value="<?= h($nama); ?>" <?= $i === $bulanSekarang ? 'selected' : ''; ?>>
                                    <?= h($nama); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tahun">Tahun</label>
                        <input type="number" name="tahun" id="tahun" min="2000" max="2100"
                               value="<?= $tahunSekarang; ?>" required>
                    </div>
                </div>
                <button type="submit" name="buat_periode" class="button">Buat Periode</button>
            </form>
        </section>
    <?php else: ?>
        <section class="panel">
            <h2>Ringkasan Periode Aktif: <?= h($periode); ?></h2>

            <div class="info-grid">
                <div class="info-card">
                    <span>Hari Tercatat</span>
                    <strong><?= count($riwayatJamKerja); ?> hari</strong>
                </div>
                <div class="info-card">
                    <span>Total Jam Kerja</span>
                    <strong><?= number_format($totalJamPeriodeAktif, 2, ',', '.'); ?> jam</strong>
                </div>
                <div class="info-card">
                    <span>Total Lembur Jam Kerja Biasa</span>
                    <strong><?= rupiah($totalLemburPeriodeAktif); ?></strong>
                </div>
                <div class="info-card">
                    <span>Akumulasi Total Gaji</span>
                    <strong><?= rupiah($totalAkumulasiPeriode); ?></strong>
                </div>
                <div class="info-card">
                    <span>Gaji Pokok</span>
                    <strong><?= rupiah($gajiPokok); ?></strong>
                </div>
                <div class="info-card highlight">
                    <span>Total Penghasilan (Gaji Pokok + Akumulasi)</span>
                    <strong><?= rupiah($totalPenghasilanPeriode); ?></strong>
                </div>
            </div>

            <h2>Input Jam Kerja Harian</h2>
            <p class="muted" style="margin-bottom:18px;">
                Tanggal yang sudah tersimpan tidak dapat ditimpa. Jam kerja 8 jam atau kurang tidak menghasilkan uang lembur sesuai aturan pada kode ini.
            </p>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="hari">Tanggal</label>
                        <select name="hari" id="hari" required>
                            <option value="">Pilih tanggal</option>
                            <?php for ($i = 1; $i <= $jumlahHari; $i++):
                                $kolomHari = 'hari' . $i;
                                $sudahDiisi = isset($jamHariTersimpan[$kolomHari])
                                    && $jamHariTersimpan[$kolomHari] !== null
                                    && $jamHariTersimpan[$kolomHari] !== '';
                            ?>
                                <option value="<?= $i; ?>" <?= $sudahDiisi ? 'disabled' : ''; ?>>
                                    Tanggal <?= $i; ?> <?= $sudahDiisi ? '(Sudah tersimpan)' : ''; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="jam">Jumlah Jam Kerja</label>
                        <input type="number" name="jam" id="jam" min="0" max="24" step="0.5"
                               placeholder="Contoh: 8 atau 10" required>
                    </div>
                </div>
                <button type="submit" name="simpan_jam" class="button">Simpan Jam Kerja</button>
            </form>

            <div class="button-row">
                <form method="POST"
                      onsubmit="return confirm('Yakin ingin menyelesaikan periode ini? Data tetap tersimpan sebagai riwayat.');">
                    <button type="submit" name="selesaikan_periode" class="button button-danger">
                        Selesaikan Periode
                    </button>
                </form>

                <form method="POST"
                      onsubmit="return confirm('PERINGATAN: semua jam kerja hari1 sampai hari31 pada periode aktif akan dikosongkan. Lanjutkan?');">
                    <button type="submit" name="hapus_data_gaji" class="button button-danger">
                        Hapus Data Gaji
                    </button>
                </form>

                <form method="POST"
                      onsubmit="return confirm('PERINGATAN: semua akumulasi gaji1 sampai gaji31 pada periode aktif akan dikosongkan. Lanjutkan?');">
                    <button type="submit" name="hapus_total_gaji" class="button button-danger">
                        Hapus Total Gaji
                    </button>
                </form>

                <a href="jamKerja_overtime.php" class="button button-secondary">Jam Kerja Overtime</a>
                <a href="homeAkun.php" class="button button-secondary">Kembali ke Beranda</a>
            </div>
        </section>

        <section class="panel">
            <h2>Rincian Jam Kerja Periode Aktif</h2>
            <?php if (count($riwayatJamKerja) > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Tanggal</th><th>Jam Kerja</th><th>Uang Lembur</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($riwayatJamKerja as $riwayat): ?>
                            <tr>
                                <td>Hari ke-<?= (int) $riwayat['tanggal']; ?></td>
                                <td><?= number_format($riwayat['jam'], 2, ',', '.'); ?> jam</td>
                                <td class="money"><?= rupiah($riwayat['uang_lembur']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td><?= number_format($totalJamPeriodeAktif, 2, ',', '.'); ?> jam</td>
                                <td class="money"><?= rupiah($totalLemburPeriodeAktif); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">Belum ada jam kerja yang disimpan pada periode ini.</div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2>Riwayat Periode Selesai</h2>
        <p class="muted" style="margin-bottom:16px;">
            Periode selesai tetap tersimpan. Total penghasilan pada riwayat dihitung dari gaji pokok saat ini ditambah akumulasi totalgaji periode tersebut.
        </p>

        <?php if (count($riwayatPeriodeSelesai) > 0): ?>
            <?php foreach ($riwayatPeriodeSelesai as $riwayatPeriode): ?>
                <details>
                    <summary>
                        <?= h($riwayatPeriode['periode']); ?>
                        — <?= (int) $riwayatPeriode['total_hari']; ?> hari tercatat
                        — Total penghasilan <?= rupiah($riwayatPeriode['total_penghasilan']); ?>
                    </summary>
                    <div class="details-content">
                        <div class="info-grid">
                            <div class="info-card">
                                <span>Total Hari Tercatat</span>
                                <strong><?= (int) $riwayatPeriode['total_hari']; ?> hari</strong>
                            </div>
                            <div class="info-card">
                                <span>Total Jam Kerja</span>
                                <strong><?= number_format($riwayatPeriode['total_jam'], 2, ',', '.'); ?> jam</strong>
                            </div>
                            <div class="info-card">
                                <span>Total Lembur Jam Kerja Biasa</span>
                                <strong><?= rupiah($riwayatPeriode['total_lembur']); ?></strong>
                            </div>
                            <div class="info-card">
                                <span>Akumulasi Total Gaji</span>
                                <strong><?= rupiah($riwayatPeriode['total_akumulasi']); ?></strong>
                            </div>
                            <div class="info-card">
                                <span>Gaji Pokok</span>
                                <strong><?= rupiah($gajiPokok); ?></strong>
                            </div>
                            <div class="info-card highlight">
                                <span>Total Penghasilan</span>
                                <strong><?= rupiah($riwayatPeriode['total_penghasilan']); ?></strong>
                            </div>
                        </div>

                        <?php if (count($riwayatPeriode['hari']) > 0): ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr><th>Tanggal</th><th>Jam Kerja</th><th>Uang Lembur</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($riwayatPeriode['hari'] as $hariRiwayat): ?>
                                        <tr>
                                            <td><?= (int) $hariRiwayat['tanggal']; ?> <?= h(explode(' ', $riwayatPeriode['periode'])[0]); ?></td>
                                            <td><?= number_format($hariRiwayat['jam'], 2, ',', '.'); ?> jam</td>
                                            <td class="money"><?= rupiah($hariRiwayat['uang_lembur']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td>Total</td>
                                            <td><?= number_format($riwayatPeriode['total_jam'], 2, ',', '.'); ?> jam</td>
                                            <td class="money"><?= rupiah($riwayatPeriode['total_lembur']); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty">Tidak ada data jam kerja pada periode ini.</div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty">Belum ada riwayat periode yang selesai.</div>
        <?php endif; ?>
    </section>

    <footer class="footer">Sistem Penggajian Karyawan</footer>
</div>
    
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
