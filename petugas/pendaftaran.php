
<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireRole("petugas");

if (empty($_SESSION['csrf_pendaftaran_petugas'])) {
    $_SESSION['csrf_pendaftaran_petugas'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tanggalID($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'
    ];

    $time = strtotime($tanggal);

    return date('j', $time) . ' ' .
        $bulan[(int)date('n', $time)] . ' ' .
        date('Y', $time);
}

$pesan = "";
$jenis_pesan = "";

// Proses perubahan status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $pendaftaran_id = filter_input(
        INPUT_POST,
        'pendaftaran_id',
        FILTER_VALIDATE_INT
    );

    $status_baru = $_POST['status'] ?? '';

    $status_valid = [
        'menunggu',
        'dipanggil',
        'selesai',
        'dibatalkan'
    ];

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_pendaftaran_petugas'], $token)
    ) {
        $pesan = "Permintaan tidak valid. Muat ulang halaman.";
        $jenis_pesan = "error";

    } elseif (
        !$pendaftaran_id ||
        !in_array($status_baru, $status_valid, true)
    ) {
        $pesan = "Data perubahan status tidak valid.";
        $jenis_pesan = "error";

    } else {

        $stmt = $conn->prepare("
            UPDATE pendaftaran
            SET status = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $status_baru,
            $pendaftaran_id
        );

        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            $pesan = "Status pendaftaran berhasil diperbarui.";
            $jenis_pesan = "success";
        } else {
            $pesan = "Gagal memperbarui status pendaftaran.";
            $jenis_pesan = "error";
        }

        $stmt->close();
    }
}

// Ambil filter pencarian
$cari = trim($_GET['cari'] ?? '');
$status_filter = $_GET['status'] ?? '';
$tanggal_filter = $_GET['tanggal'] ?? '';

$status_valid = [
    'menunggu',
    'dipanggil',
    'selesai',
    'dibatalkan'
];

$sql = "
    SELECT
        pd.id,
        pd.nomor_antrean,
        pd.keluhan,
        pd.status,
        pd.created_at,
        ps.nik,
        u_pasien.nama AS nama_pasien,
        u_pasien.email,
        ps.no_hp,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        u_dokter.nama AS nama_dokter,
        p.nama_poli
    FROM pendaftaran pd
    JOIN pasien ps ON pd.pasien_id = ps.id
    JOIN users u_pasien ON ps.user_id = u_pasien.id
    JOIN jadwal j ON pd.jadwal_id = j.id
    JOIN dokter d ON j.dokter_id = d.id
    JOIN users u_dokter ON d.user_id = u_dokter.id
    JOIN poliklinik p ON d.poliklinik_id = p.id
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($cari !== '') {
    $sql .= "
        AND (
            u_pasien.nama LIKE ?
            OR ps.nik LIKE ?
            OR u_dokter.nama LIKE ?
        )
    ";

    $keyword = "%" . $cari . "%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types .= "sss";
}

if (in_array($status_filter, $status_valid, true)) {
    $sql .= " AND pd.status = ? ";
    $params[] = $status_filter;
    $types .= "s";
}

if ($tanggal_filter !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $tanggal_filter);

    if ($dateObj && $dateObj->format('Y-m-d') === $tanggal_filter) {
        $sql .= " AND j.tanggal = ? ";
        $params[] = $tanggal_filter;
        $types .= "s";
    } else {
        $tanggal_filter = '';
    }
}

$sql .= "
    ORDER BY
        j.tanggal DESC,
        j.jam_mulai ASC,
        pd.nomor_antrean ASC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$data_pendaftaran = $stmt->get_result();

// Statistik seluruh pendaftaran
$statistik = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'menunggu') AS menunggu,
        SUM(status = 'dipanggil') AS dipanggil,
        SUM(status = 'selesai') AS selesai,
        SUM(status = 'dibatalkan') AS dibatalkan
    FROM pendaftaran
")->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manajemen Pendaftaran | SmartCare</title>

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
    color: #263449;
}

.layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 245px;
    flex-shrink: 0;
    background: #123c69;
    color: white;
    padding: 25px 17px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 0 10px 35px;
}

.brand-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    background: white;
    color: #123c69;
    border-radius: 12px;
    font-size: 20px;
}

.brand h2 {
    font-size: 21px;
}

.brand small {
    display: block;
    color: #bdd3eb;
    font-size: 11px;
    margin-top: 3px;
}

.menu-label {
    color: #a9c1dd;
    font-size: 10px;
    font-weight: 700;
    padding: 10px 12px;
    letter-spacing: 1px;
}

.menu a {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 13px;
    border-radius: 9px;
    color: #d8e5f4;
    text-decoration: none;
    font-size: 13px;
    margin-bottom: 5px;
}

.menu a:hover,
.menu a.active {
    background: #245486;
    color: white;
}

.menu a i {
    width: 18px;
}

.main {
    flex: 1;
    min-width: 0;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 27px;
}

.topbar h1 {
    font-size: 25px;
}

.topbar p {
    font-size: 12px;
    color: #8390a2;
    margin-top: 6px;
}

.admin-label {
    background: white;
    padding: 11px 15px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
}

.stats {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 13px;
    margin-bottom: 23px;
}

.stat {
    background: white;
    border-radius: 13px;
    padding: 17px;
    box-shadow: 0 3px 15px rgba(0,0,0,.03);
}

.stat p {
    font-size: 11px;
    color: #8491a3;
    margin-bottom: 10px;
}

.stat h2 {
    font-size: 25px;
}

.card {
    background: white;
    padding: 23px;
    border-radius: 15px;
    box-shadow: 0 3px 18px rgba(0,0,0,.035);
}

.card-heading {
    margin-bottom: 20px;
}

.card-heading h2 {
    font-size: 17px;
}

.card-heading p {
    color: #8491a3;
    font-size: 12px;
    margin-top: 6px;
}

.message {
    padding: 13px 15px;
    border-radius: 9px;
    font-size: 12px;
    margin-bottom: 17px;
}

.success {
    color: #187a43;
    background: #e5f8ed;
}

.error {
    color: #b52d2d;
    background: #ffebeb;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.filters input,
.filters select {
    padding: 11px 12px;
    border: 1px solid #e0e6ee;
    border-radius: 9px;
    font-family: inherit;
    font-size: 12px;
}

.filters input[type="text"] {
    flex: 1;
    min-width: 180px;
}

.filters button,
.reset {
    padding: 11px 16px;
    background: #1765a1;
    color: white;
    border: none;
    border-radius: 9px;
    cursor: pointer;
    text-decoration: none;
    font-size: 12px;
}

.reset {
    background: #edf1f6;
    color: #536174;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

th {
    text-align: left;
    background: #f6f8fb;
    padding: 13px;
    color: #8390a2;
    font-size: 10px;
    white-space: nowrap;
}

td {
    padding: 14px 13px;
    border-bottom: 1px solid #edf0f5;
    font-size: 11px;
    vertical-align: middle;
}

.patient-name {
    font-weight: 700;
    font-size: 12px;
}

.sub {
    color: #8995a5;
    margin-top: 5px;
    display: block;
}

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}

.status.menunggu {
    background: #fff3d9;
    color: #a76a00;
}

.status.dipanggil {
    background: #e4f1ff;
    color: #2367a5;
}

.status.selesai {
    background: #e5f8ed;
    color: #187a43;
}

.status.dibatalkan {
    background: #ffebeb;
    color: #b52d2d;
}

.update-form {
    display: flex;
    gap: 6px;
}

.update-form select {
    padding: 7px;
    border: 1px solid #dce3ed;
    border-radius: 7px;
    font-size: 10px;
}

.update-form button {
    padding: 7px 10px;
    background: #1765a1;
    color: white;
    border: none;
    border-radius: 7px;
    cursor: pointer;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #8491a3;
    font-size: 13px;
}

@media(max-width: 1100px) {
    .stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media(max-width: 700px) {
    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
        padding: 15px;
    }

    .brand {
        padding-bottom: 15px;
    }

    .menu-label {
        display: none;
    }

    .menu {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .menu a {
        margin: 0;
        padding: 9px;
        font-size: 11px;
    }

    .main {
        padding: 17px;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

</head>

<body>

<div class="layout">

<aside class="sidebar">

    <div class="brand">
        <div class="brand-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>

        <div>
            <h2>SmartCare</h2>
            <small>Staff Portal</small>
        </div>
    </div>

    <div class="menu-label">MENU UTAMA</div>

    <nav class="menu">

        <a href="dashboard.php">
            <i class="fa-solid fa-house"></i>
            Dashboard
        </a>

        <a href="pasien.php">
            <i class="fa-solid fa-users"></i>
            Data Pasien
        </a>

        <a href="dokter.php">
            <i class="fa-solid fa-user-doctor"></i>
            Data Dokter
        </a>

        <a href="poliklinik.php">
            <i class="fa-solid fa-hospital"></i>
            Poliklinik
        </a>

        <a href="jadwal.php">
            <i class="fa-solid fa-calendar-days"></i>
            Jadwal Dokter
        </a>

        <a href="pendaftaran.php" class="active">
            <i class="fa-solid fa-clipboard-list"></i>
            Pendaftaran
        </a>

        <a href="antrean.php">
            <i class="fa-solid fa-list-ol"></i>
            Antrean Pasien
        </a>

        <a href="../logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            Keluar
        </a>

    </nav>

</aside>

<main class="main">

    <header class="topbar">

        <div>
            <h1>Manajemen Pendaftaran</h1>
            <p>Kelola pendaftaran dan status antrean pasien SmartCare.</p>
        </div>

        <div class="admin-label">
            <i class="fa-solid fa-user-shield"></i>
            Petugas
        </div>

    </header>

    <?php if ($pesan): ?>
        <div class="message <?= e($jenis_pesan); ?>">
            <?= e($pesan); ?>
        </div>
    <?php endif; ?>

    <section class="stats">

        <div class="stat">
            <p>Total Pendaftaran</p>
            <h2><?= (int)($statistik['total'] ?? 0); ?></h2>
        </div>

        <div class="stat">
            <p>Menunggu</p>
            <h2><?= (int)($statistik['menunggu'] ?? 0); ?></h2>
        </div>

        <div class="stat">
            <p>Dipanggil</p>
            <h2><?= (int)($statistik['dipanggil'] ?? 0); ?></h2>
        </div>

        <div class="stat">
            <p>Selesai</p>
            <h2><?= (int)($statistik['selesai'] ?? 0); ?></h2>
        </div>

        <div class="stat">
            <p>Dibatalkan</p>
            <h2><?= (int)($statistik['dibatalkan'] ?? 0); ?></h2>
        </div>

    </section>

    <section class="card">

        <div class="card-heading">
            <h2>Daftar Pendaftaran Pasien</h2>
            <p>Seluruh pendaftaran yang masuk melalui sistem pasien.</p>
        </div>

        <form method="GET" class="filters">

            <input
                type="text"
                name="cari"
                placeholder="Cari nama pasien, NIK, dokter..."
                value="<?= e($cari); ?>"
            >

            <select name="status">
                <option value="">Semua Status</option>

                <?php foreach ($status_valid as $s): ?>
                    <option
                        value="<?= e($s); ?>"
                        <?= $status_filter === $s ? 'selected' : ''; ?>
                    >
                        <?= e(ucfirst($s)); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input
                type="date"
                name="tanggal"
                value="<?= e($tanggal_filter); ?>"
            >

            <button type="submit">
                <i class="fa-solid fa-search"></i>
                Cari
            </button>

            <a href="pendaftaran.php" class="reset">
                Reset
            </a>

        </form>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Pasien</th>
                        <th>Dokter</th>
                        <th>Poliklinik</th>
                        <th>Tanggal Praktik</th>
                        <th>Jam</th>
                        <th>Antrean</th>
                        <th>Keluhan</th>
                        <th>Status</th>
                        <th>Ubah Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($data_pendaftaran->num_rows > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($row = $data_pendaftaran->fetch_assoc()): ?>

                        <tr>

                            <td><?= $no++; ?></td>

                            <td>
                                <span class="patient-name">
                                    <?= e($row['nama_pasien']); ?>
                                </span>

                                <span class="sub">
                                    NIK: <?= e($row['nik']); ?>
                                </span>

                                <span class="sub">
                                    HP: <?= e($row['no_hp']); ?>
                                </span>
                            </td>

                            <td><?= e($row['nama_dokter']); ?></td>

                            <td><?= e($row['nama_poli']); ?></td>

                            <td><?= e(tanggalID($row['tanggal'])); ?></td>

                            <td>
                                <?= e(substr($row['jam_mulai'], 0, 5)); ?>
                                -
                                <?= e(substr($row['jam_selesai'], 0, 5)); ?>
                            </td>

                            <td>
                                <strong>
                                    <?= (int)$row['nomor_antrean']; ?>
                                </strong>
                            </td>

                            <td>
                                <?= e($row['keluhan'] ?: '-'); ?>
                            </td>

                            <td>
                                <span class="status <?= e($row['status']); ?>">
                                    <?= e(ucfirst($row['status'])); ?>
                                </span>
                            </td>

                            <td>

                                <form method="POST" class="update-form">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['csrf_pendaftaran_petugas']); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="pendaftaran_id"
                                        value="<?= (int)$row['id']; ?>"
                                    >

                                    <select name="status" required>

                                        <?php foreach ($status_valid as $s): ?>

                                            <option
                                                value="<?= e($s); ?>"
                                                <?= $row['status'] === $s ? 'selected' : ''; ?>
                                            >
                                                <?= e(ucfirst($s)); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                    <button type="submit" title="Simpan status">
                                        <i class="fa-solid fa-check"></i>
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10" class="empty">
                            <i class="fa-solid fa-folder-open"></i>
                            <br>
                            Belum ada data pendaftaran yang sesuai.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</div>

</body>
</html>
