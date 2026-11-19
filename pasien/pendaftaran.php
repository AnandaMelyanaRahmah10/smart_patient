
<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireRole("pasien");

$user_id = (int) $_SESSION['user_id'];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_pendaftaran'])) {
    $_SESSION['csrf_pendaftaran'] = bin2hex(random_bytes(32));
}

$pesan = "";
$jenis_pesan = "";

// Ambil ID pasien berdasarkan akun yang login
$stmt = $conn->prepare("
    SELECT id, nik
    FROM pasien
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profil_pasien = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profil_pasien) {
    exit("Profil pasien tidak ditemukan. Hubungi petugas.");
}

$pasien_id = (int)$profil_pasien['id'];

// Proses pendaftaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $jadwal_id = filter_input(INPUT_POST, 'jadwal_id', FILTER_VALIDATE_INT);
    $keluhan = trim($_POST['keluhan'] ?? '');

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_pendaftaran'], $token)
    ) {
        $pesan = "Permintaan tidak valid. Silakan muat ulang halaman.";
        $jenis_pesan = "error";

    } elseif (!$jadwal_id || $jadwal_id < 1) {
        $pesan = "Silakan pilih jadwal praktik terlebih dahulu.";
        $jenis_pesan = "error";

    } elseif (strlen($keluhan) > 2000) {
        $pesan = "Keluhan maksimal 2.000 karakter.";
        $jenis_pesan = "error";

    } else {

        try {
            $conn->begin_transaction();

            // Kunci jadwal agar kuota tidak terisi berlebihan
            $stmt = $conn->prepare("
                SELECT id, tanggal, jam_mulai, jam_selesai,
                       kuota, status
                FROM jadwal
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->bind_param("i", $jadwal_id);
            $stmt->execute();
            $data_jadwal = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (
                !$data_jadwal ||
                $data_jadwal['status'] !== 'aktif' ||
                $data_jadwal['tanggal'] < date('Y-m-d')
            ) {
                throw new Exception("Jadwal tidak tersedia atau sudah lewat.");
            }

            // Periksa apakah pasien sudah mendaftar pada jadwal tersebut
            $stmt = $conn->prepare("
                SELECT id
                FROM pendaftaran
                WHERE pasien_id = ?
                  AND jadwal_id = ?
                  AND status <> 'dibatalkan'
                LIMIT 1
            ");
            $stmt->bind_param("ii", $pasien_id, $jadwal_id);
            $stmt->execute();
            $sudah_daftar = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($sudah_daftar) {
                throw new Exception("Anda sudah terdaftar pada jadwal tersebut.");
            }

            // Hitung jumlah pendaftar yang belum dibatalkan
            $stmt = $conn->prepare("
                SELECT
                    COUNT(*) AS jumlah,
                    COALESCE(MAX(nomor_antrean), 0) AS antrean_terakhir
                FROM pendaftaran
                WHERE jadwal_id = ?
                  AND status <> 'dibatalkan'
            ");
            $stmt->bind_param("i", $jadwal_id);
            $stmt->execute();
            $jumlah_pendaftar = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ((int)$jumlah_pendaftar['jumlah'] >= (int)$data_jadwal['kuota']) {
                throw new Exception("Maaf, kuota jadwal ini sudah penuh.");
            }

            $nomor_antrean = (int)$jumlah_pendaftar['antrean_terakhir'] + 1;

            // Simpan pendaftaran
            $stmt = $conn->prepare("
                INSERT INTO pendaftaran
                (pasien_id, jadwal_id, nomor_antrean, keluhan)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iiis",
                $pasien_id,
                $jadwal_id,
                $nomor_antrean,
                $keluhan
            );
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $pesan = "Pendaftaran berhasil! Nomor antrean Anda adalah " .
                     $nomor_antrean . ".";
            $jenis_pesan = "success";

        } catch (Throwable $error) {

            $conn->rollback();

            $pesan = $error instanceof Exception
                ? $error->getMessage()
                : "Terjadi kesalahan saat menyimpan pendaftaran.";

            $jenis_pesan = "error";
        }
    }
}

// Ambil jadwal aktif yang masih tersedia
$daftar_jadwal = $conn->query("
    SELECT
        j.id,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        j.kuota,
        u.nama AS nama_dokter,
        d.spesialisasi,
        p.nama_poli,
        (
            SELECT COUNT(*)
            FROM pendaftaran pd
            WHERE pd.jadwal_id = j.id
              AND pd.status <> 'dibatalkan'
        ) AS jumlah_pendaftar
    FROM jadwal j
    JOIN dokter d ON j.dokter_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN poliklinik p ON d.poliklinik_id = p.id
    WHERE j.status = 'aktif'
      AND j.tanggal >= CURDATE()
    ORDER BY j.tanggal ASC, j.jam_mulai ASC
");

// Ambil riwayat pendaftaran pasien
$stmt = $conn->prepare("
    SELECT
        pd.id,
        pd.nomor_antrean,
        pd.status,
        pd.created_at,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        u.nama AS nama_dokter,
        p.nama_poli
    FROM pendaftaran pd
    JOIN jadwal j ON pd.jadwal_id = j.id
    JOIN dokter d ON j.dokter_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN poliklinik p ON d.poliklinik_id = p.id
    WHERE pd.pasien_id = ?
    ORDER BY pd.created_at DESC
");
$stmt->bind_param("i", $pasien_id);
$stmt->execute();
$riwayat = $stmt->get_result();

function tanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'
    ];

    $waktu = strtotime($tanggal);

    return date('j', $waktu) . ' ' .
        $bulan[(int)date('n', $waktu)] . ' ' .
        date('Y', $waktu);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Pendaftaran Pasien | SmartCare</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: #f5f7fb;
    color: #243247;
}

.container {
    width: min(1100px, 92%);
    margin: auto;
    padding: 30px 0;
}

.header {
    background: #123c69;
    color: white;
    padding: 22px 0;
}

.header-content {
    width: min(1100px, 92%);
    margin: auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.brand {
    font-size: 22px;
    font-weight: 800;
}

.brand i {
    margin-right: 9px;
}

.header a {
    color: white;
    text-decoration: none;
    font-size: 13px;
}

.intro {
    margin-bottom: 25px;
}

.intro h1 {
    font-size: 27px;
    margin-bottom: 8px;
}

.intro p {
    color: #8390a2;
    font-size: 13px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    margin-bottom: 22px;
    box-shadow: 0 4px 20px rgba(0,0,0,.04);
}

.card h2 {
    font-size: 18px;
    margin-bottom: 8px;
}

.description {
    color: #8390a2;
    font-size: 12px;
    margin-bottom: 22px;
}

.message {
    padding: 14px 16px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 20px;
}

.success {
    background: #e5f8ed;
    color: #187a43;
}

.error {
    background: #ffebeb;
    color: #b52d2d;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 9px;
}

select, textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #dce3ed;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    outline: none;
    background: white;
}

select:focus, textarea:focus {
    border-color: #2878ad;
}

textarea {
    resize: vertical;
    min-height: 100px;
}

.field {
    margin-bottom: 20px;
}

button {
    background: #1765a1;
    color: white;
    border: none;
    padding: 13px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

button:hover {
    background: #104b7c;
}

.schedule-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.schedule {
    border: 1px solid #e7edf4;
    padding: 17px;
    border-radius: 12px;
}

.schedule strong {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
}

.schedule p {
    color: #718096;
    font-size: 12px;
    line-height: 1.8;
}

.quota {
    color: #187a43 !important;
    font-weight: 700;
    margin-top: 7px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th {
    text-align: left;
    padding: 13px;
    background: #f5f7fb;
    color: #758196;
    font-size: 11px;
}

td {
    padding: 14px 13px;
    border-bottom: 1px solid #edf0f5;
    font-size: 12px;
}

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    background: #e8f2ff;
    color: #2367a5;
}

.status.dibatalkan {
    background: #ffe9e9;
    color: #b52d2d;
}

.status.selesai {
    background: #e5f8ed;
    color: #187a43;
}

.empty {
    text-align: center;
    color: #8491a3;
    padding: 30px 10px;
    font-size: 13px;
}

@media(max-width: 650px) {
    .schedule-list {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 18px;
    }

    .intro h1 {
        font-size: 22px;
    }
}
</style>
</head>

<body>

<header class="header">
    <div class="header-content">
        <div class="brand">
            <i class="fa-solid fa-heart-pulse"></i> SmartCare
        </div>

        <a href="dashboard.php">
            <i class="fa-solid fa-house"></i> Dashboard
        </a>
    </div>
</header>

<main class="container">

    <section class="intro">
        <h1>Pendaftaran Pasien</h1>
        <p>Pilih jadwal dokter yang tersedia untuk melakukan pendaftaran.</p>
    </section>

    <?php if ($pesan): ?>
        <div class="message <?= e($jenis_pesan); ?>">
            <?= e($pesan); ?>
        </div>
    <?php endif; ?>

    <section class="card">

        <h2>Jadwal Dokter Tersedia</h2>
        <p class="description">
            Pilih jadwal praktik sesuai kebutuhan pemeriksaan Anda.
        </p>

        <?php if ($daftar_jadwal->num_rows > 0): ?>

            <div class="schedule-list">

                <?php while ($j = $daftar_jadwal->fetch_assoc()): ?>

                    <div class="schedule">
                        <strong><?= e($j['nama_dokter']); ?></strong>

                        <p>
                            <i class="fa-solid fa-stethoscope"></i>
                            <?= e($j['spesialisasi']); ?>
                        </p>

                        <p>
                            <i class="fa-solid fa-hospital"></i>
                            <?= e($j['nama_poli']); ?>
                        </p>

                        <p>
                            <i class="fa-solid fa-calendar"></i>
                            <?= e(tanggalIndonesia($j['tanggal'])); ?>
                        </p>

                        <p>
                            <i class="fa-solid fa-clock"></i>
                            <?= e(substr($j['jam_mulai'], 0, 5)); ?>
                            -
                            <?= e(substr($j['jam_selesai'], 0, 5)); ?>
                        </p>

                        <p class="quota">
                            Sisa kuota:
                            <?= max(0, (int)$j['kuota'] - (int)$j['jumlah_pendaftar']); ?>
                        </p>
                    </div>

                <?php endwhile; ?>

            </div>

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($_SESSION['csrf_pendaftaran']); ?>">

                <div class="field">
                    <label for="jadwal_id">Pilih Jadwal Praktik</label>

                    <select name="jadwal_id" id="jadwal_id" required>
                        <option value="">-- Pilih Dokter dan Jadwal --</option>

                        <?php
                        // Query ulang karena hasil daftar jadwal sudah ditampilkan
                        $opsi = $conn->query("
                            SELECT
                                j.id,
                                j.tanggal,
                                j.jam_mulai,
                                j.jam_selesai,
                                j.kuota,
                                u.nama AS nama_dokter,
                                p.nama_poli,
                                (
                                    SELECT COUNT(*)
                                    FROM pendaftaran pd
                                    WHERE pd.jadwal_id = j.id
                                      AND pd.status <> 'dibatalkan'
                                ) AS jumlah_pendaftar
                            FROM jadwal j
                            JOIN dokter d ON j.dokter_id = d.id
                            JOIN users u ON d.user_id = u.id
                            JOIN poliklinik p ON d.poliklinik_id = p.id
                            WHERE j.status = 'aktif'
                              AND j.tanggal >= CURDATE()
                            ORDER BY j.tanggal ASC, j.jam_mulai ASC
                        ");
                        ?>

                        <?php while ($o = $opsi->fetch_assoc()): ?>

                            <?php if ((int)$o['jumlah_pendaftar'] < (int)$o['kuota']): ?>

                                <option value="<?= (int)$o['id']; ?>">
                                    <?= e($o['nama_dokter']); ?> -
                                    <?= e($o['nama_poli']); ?> -
                                    <?= e(tanggalIndonesia($o['tanggal'])); ?> -
                                    <?= e(substr($o['jam_mulai'], 0, 5)); ?>
                                </option>

                            <?php endif; ?>

                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="field">
                    <label for="keluhan">Keluhan Pasien</label>

                    <textarea
                        name="keluhan"
                        id="keluhan"
                        maxlength="2000"
                        placeholder="Tuliskan keluhan yang ingin disampaikan kepada dokter..."
                    ></textarea>
                </div>

                <button type="submit">
                    <i class="fa-solid fa-calendar-check"></i>
                    Daftar Sekarang
                </button>

            </form>

        <?php else: ?>

            <div class="empty">
                Belum ada jadwal praktik yang tersedia saat ini.
            </div>

        <?php endif; ?>

    </section>

    <section class="card">

        <h2>Riwayat Pendaftaran Saya</h2>
        <p class="description">
            Daftar pendaftaran yang pernah Anda lakukan.
        </p>

        <?php if ($riwayat->num_rows > 0): ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No. Antrean</th>
                            <th>Dokter</th>
                            <th>Poliklinik</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($r = $riwayat->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    <strong><?= (int)$r['nomor_antrean']; ?></strong>
                                </td>

                                <td><?= e($r['nama_dokter']); ?></td>

                                <td><?= e($r['nama_poli']); ?></td>

                                <td><?= e(tanggalIndonesia($r['tanggal'])); ?></td>

                                <td>
                                    <?= e(substr($r['jam_mulai'], 0, 5)); ?>
                                    -
                                    <?= e(substr($r['jam_selesai'], 0, 5)); ?>
                                </td>

                                <td>
                                    <span class="status <?= e($r['status']); ?>">
                                        <?= e(ucfirst($r['status'])); ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <div class="empty">
                Anda belum memiliki riwayat pendaftaran.
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
