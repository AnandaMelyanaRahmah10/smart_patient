
<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireRole("dokter");

$user_id = (int)$_SESSION['user_id'];

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

// Ambil profil dokter
$stmt = $conn->prepare("
    SELECT
        d.id AS dokter_id,
        u.nama,
        d.spesialisasi,
        p.nama_poli
    FROM dokter d
    JOIN users u ON d.user_id = u.id
    JOIN poliklinik p ON d.poliklinik_id = p.id
    WHERE d.user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$dokter = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dokter) {
    exit("Profil dokter tidak ditemukan.");
}

$dokter_id = (int)$dokter['dokter_id'];

// Filter
$cari = trim($_GET['cari'] ?? '');
$status = $_GET['status'] ?? '';
$tanggal = $_GET['tanggal'] ?? '';

$status_valid = [
    'menunggu',
    'dipanggil',
    'selesai',
    'dibatalkan'
];

// Query statistik milik dokter yang login
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(pd.status = 'menunggu') AS menunggu,
        SUM(pd.status = 'dipanggil') AS dipanggil,
        SUM(pd.status = 'selesai') AS selesai
    FROM pendaftaran pd
    JOIN jadwal j ON pd.jadwal_id = j.id
    WHERE j.dokter_id = ?
");

$stmt->bind_param("i", $dokter_id);
$stmt->execute();
$statistik = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Query antrean
$sql = "
    SELECT
        pd.id,
        pd.nomor_antrean,
        pd.keluhan,
        pd.status,
        pd.created_at,
        ps.nik,
        ps.tanggal_lahir,
        ps.jenis_kelamin,
        ps.no_hp,
        u.nama AS nama_pasien,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        p.nama_poli
    FROM pendaftaran pd
    JOIN pasien ps ON pd.pasien_id = ps.id
    JOIN users u ON ps.user_id = u.id
    JOIN jadwal j ON pd.jadwal_id = j.id
    JOIN dokter d ON j.dokter_id = d.id
    JOIN poliklinik p ON d.poliklinik_id = p.id
    WHERE j.dokter_id = ?
";

$params = [$dokter_id];
$types = "i";

if ($cari !== '') {
    $sql .= "
        AND (
            u.nama LIKE ?
            OR ps.nik LIKE ?
        )
    ";

    $keyword = "%" . $cari . "%";
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "ss";
}

if (in_array($status, $status_valid, true)) {
    $sql .= " AND pd.status = ? ";
    $params[] = $status;
    $types .= "s";
}

if ($tanggal !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $tanggal);

    if ($dateObj && $dateObj->format('Y-m-d') === $tanggal) {
        $sql .= " AND j.tanggal = ? ";
        $params[] = $tanggal;
        $types .= "s";
    } else {
        $tanggal = '';
    }
}

$sql .= "
    ORDER BY
        j.tanggal ASC,
        j.jam_mulai ASC,
        pd.nomor_antrean ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$antrean = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Antrean Pasien | SmartCare</title>

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
    margin-bottom: 25px;
}

.topbar h1 {
    font-size: 25px;
}

.topbar p {
    font-size: 12px;
    color: #8390a2;
    margin-top: 6px;
}

.doctor-info {
    background: white;
    padding: 12px 16px;
    border-radius: 11px;
    font-size: 12px;
    font-weight: 700;
}

.welcome {
    background: linear-gradient(120deg, #174d7d, #2878ad);
    color: white;
    border-radius: 15px;
    padding: 23px;
    margin-bottom: 22px;
}

.welcome h2 {
    font-size: 19px;
    margin-bottom: 7px;
}

.welcome p {
    font-size: 12px;
    color: #d9eafa;
    line-height: 1.7;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 22px;
}

.stat {
    background: white;
    padding: 19px;
    border-radius: 13px;
    box-shadow: 0 3px 15px rgba(0,0,0,.03);
}

.stat p {
    font-size: 11px;
    color: #8491a3;
    margin-bottom: 9px;
}

.stat h2 {
    font-size: 26px;
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
    font-size: 12px;
    color: #8491a3;
    margin-top: 6px;
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
    min-width: 950px;
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
    font-size: 12px;
    font-weight: 700;
}

.sub {
    color: #8995a5;
    margin-top: 5px;
    display: block;
}

.queue-number {
    background: #eaf3ff;
    color: #2367a5;
    padding: 7px 12px;
    border-radius: 8px;
    font-weight: 800;
    font-size: 12px;
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

.empty {
    text-align: center;
    padding: 35px;
    color: #8491a3;
    font-size: 13px;
}

@media(max-width: 950px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
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

    .topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
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
            <small>Doctor Portal</small>
        </div>
    </div>

    <div class="menu-label">MENU UTAMA</div>

    <nav class="menu">

        <a href="dashboard.php">
            <i class="fa-solid fa-house"></i>
            Dashboard
        </a>

        <a href="dashboard.php#jadwal">
            <i class="fa-solid fa-calendar-days"></i>
            Jadwal Saya
        </a>

        <a href="antrean.php" class="active">
            <i class="fa-solid fa-users"></i>
            Antrean Pasien
        </a>

        <a href="dashboard.php#profil">
            <i class="fa-solid fa-user-doctor"></i>
            Profil Dokter
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
            <h1>Antrean Pasien</h1>
            <p>Daftar pasien yang terdaftar pada jadwal praktik Anda.</p>
        </div>

        <div class="doctor-info">
            <i class="fa-solid fa-user-doctor"></i>
            <?= e($dokter['nama']); ?>
        </div>

    </header>

    <section class="welcome">
        <h2>Halo, <?= e($dokter['nama']); ?> 👋</h2>
        <p>
            <?= e($dokter['spesialisasi']); ?> -
            <?= e($dokter['nama_poli']); ?><br>
            Berikut adalah informasi antrean pasien Anda.
        </p>
    </section>

    <section class="stats">

        <div class="stat">
            <p>Total Pasien</p>
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

    </section>

    <section class="card">

        <div class="card-heading">
            <h2>Daftar Antrean</h2>
            <p>Data pasien berdasarkan jadwal praktik dokter yang sedang login.</p>
        </div>

        <form method="GET" class="filters">

            <input
                type="text"
                name="cari"
                placeholder="Cari nama pasien atau NIK..."
                value="<?= e($cari); ?>"
            >

            <select name="status">
                <option value="">Semua Status</option>

                <?php foreach ($status_valid as $s): ?>
                    <option
                        value="<?= e($s); ?>"
                        <?= $status === $s ? 'selected' : ''; ?>
                    >
                        <?= e(ucfirst($s)); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input
                type="date"
                name="tanggal"
                value="<?= e($tanggal); ?>"
            >

            <button type="submit">
                <i class="fa-solid fa-search"></i>
                Cari
            </button>

            <a href="antrean.php" class="reset">Reset</a>

        </form>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nomor Antrean</th>
                        <th>Nama Pasien</th>
                        <th>NIK</th>
                        <th>Jenis Kelamin</th>
                        <th>No. HP</th>
                        <th>Tanggal Praktik</th>
                        <th>Jam Praktik</th>
                        <th>Keluhan</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($antrean->num_rows > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($row = $antrean->fetch_assoc()): ?>

                        <tr>

                            <td><?= $no++; ?></td>

                            <td>
                                <span class="queue-number">
                                    <?= (int)$row['nomor_antrean']; ?>
                                </span>
                            </td>

                            <td>
                                <span class="patient-name">
                                    <?= e($row['nama_pasien']); ?>
                                </span>
                            </td>

                            <td><?= e($row['nik']); ?></td>

                            <td><?= e($row['jenis_kelamin']); ?></td>

                            <td><?= e($row['no_hp']); ?></td>

                            <td><?= e(tanggalID($row['tanggal'])); ?></td>

                            <td>
                                <?= e(substr($row['jam_mulai'], 0, 5)); ?>
                                -
                                <?= e(substr($row['jam_selesai'], 0, 5)); ?>
                            </td>

                            <td><?= e($row['keluhan'] ?: '-'); ?></td>

                            <td>
                                <span class="status <?= e($row['status']); ?>">
                                    <?= e(ucfirst($row['status'])); ?>
                                </span>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10" class="empty">
                            Belum ada pasien yang terdaftar pada jadwal Anda.
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
