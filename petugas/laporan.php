
<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireRole("petugas");

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Filter tanggal
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

function validTanggal($tanggal) {
    $obj = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $obj && $obj->format('Y-m-d') === $tanggal;
}

if (!validTanggal($tanggal_awal)) {
    $tanggal_awal = date('Y-m-01');
}

if (!validTanggal($tanggal_akhir)) {
    $tanggal_akhir = date('Y-m-d');
}

if ($tanggal_awal > $tanggal_akhir) {
    $temp = $tanggal_awal;
    $tanggal_awal = $tanggal_akhir;
    $tanggal_akhir = $temp;
}

$status_filter = $_GET['status'] ?? '';

$status_valid = ['menunggu', 'dipanggil', 'selesai', 'dibatalkan'];

if (!in_array($status_filter, $status_valid, true)) {
    $status_filter = '';
}

// Statistik laporan
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT pd.pasien_id) AS total_pasien,
        SUM(pd.status = 'menunggu') AS menunggu,
        SUM(pd.status = 'dipanggil') AS dipanggil,
        SUM(pd.status = 'selesai') AS selesai,
        SUM(pd.status = 'dibatalkan') AS dibatalkan
    FROM pendaftaran pd
    JOIN jadwal j ON j.id = pd.jadwal_id
    WHERE j.tanggal BETWEEN ? AND ?
");

$stmt->bind_param("ss", $tanggal_awal, $tanggal_akhir);
$stmt->execute();
$statistik = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Rekap pendaftaran per tanggal
$stmt = $conn->prepare("
    SELECT
        j.tanggal,
        COUNT(*) AS total,
        SUM(pd.status = 'menunggu') AS menunggu,
        SUM(pd.status = 'dipanggil') AS dipanggil,
        SUM(pd.status = 'selesai') AS selesai,
        SUM(pd.status = 'dibatalkan') AS dibatalkan
    FROM pendaftaran pd
    JOIN jadwal j ON j.id = pd.jadwal_id
    WHERE j.tanggal BETWEEN ? AND ?
    GROUP BY j.tanggal
    ORDER BY j.tanggal DESC
");

$stmt->bind_param("ss", $tanggal_awal, $tanggal_akhir);
$stmt->execute();
$rekap_harian = $stmt->get_result();
$stmt->close();

// Detail pendaftaran
$sql = "
    SELECT
        pd.id,
        pd.nomor_antrean,
        pd.status,
        pd.keluhan,
        j.tanggal,
        j.jam_mulai,
        u_pasien.nama AS nama_pasien,
        ps.nik,
        u_dokter.nama AS nama_dokter,
        p.nama_poli
    FROM pendaftaran pd
    JOIN pasien ps ON ps.id = pd.pasien_id
    JOIN users u_pasien ON u_pasien.id = ps.user_id
    JOIN jadwal j ON j.id = pd.jadwal_id
    JOIN dokter d ON d.id = j.dokter_id
    JOIN users u_dokter ON u_dokter.id = d.user_id
    JOIN poliklinik p ON p.id = d.poliklinik_id
    WHERE j.tanggal BETWEEN ? AND ?
";

if ($status_filter !== '') {
    $sql .= " AND pd.status = ? ";
}

$sql .= " ORDER BY j.tanggal DESC, j.jam_mulai ASC, pd.nomor_antrean ASC";

$stmt = $conn->prepare($sql);

if ($status_filter !== '') {
    $stmt->bind_param("sss", $tanggal_awal, $tanggal_akhir, $status_filter);
} else {
    $stmt->bind_param("ss", $tanggal_awal, $tanggal_akhir);
}

$stmt->execute();
$data_laporan = $stmt->get_result();
$stmt->close();

$total = (int)($statistik['total'] ?? 0);
$totalPasien = (int)($statistik['total_pasien'] ?? 0);
$totalMenunggu = (int)($statistik['menunggu'] ?? 0);
$totalDipanggil = (int)($statistik['dipanggil'] ?? 0);
$totalSelesai = (int)($statistik['selesai'] ?? 0);
$totalDibatalkan = (int)($statistik['dibatalkan'] ?? 0);

function statusLabel($status) {
    $labels = [
        'menunggu' => 'Menunggu',
        'dipanggil' => 'Dipanggil',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan'
    ];

    return $labels[$status] ?? ucfirst($status);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan | SmartCare</title>

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

        .admin-label {
            background: white;
            padding: 11px 15px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
        }

        .filter-card,
        .card {
            background: white;
            padding: 23px;
            border-radius: 15px;
            box-shadow: 0 3px 18px rgba(0,0,0,.035);
            margin-bottom: 22px;
        }

        .filter-card h2,
        .card h2 {
            font-size: 16px;
            margin-bottom: 7px;
        }

        .description {
            color: #8491a3;
            font-size: 12px;
            margin-bottom: 18px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field label {
            font-size: 11px;
            font-weight: 700;
            color: #68768a;
        }

        .field input,
        .field select {
            padding: 11px;
            border: 1px solid #e0e6ee;
            border-radius: 9px;
            font-family: inherit;
            font-size: 12px;
        }

        .btn {
            padding: 11px 16px;
            border: none;
            background: #1765a1;
            color: white;
            border-radius: 9px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }

        .btn-light {
            background: #edf1f6;
            color: #536174;
        }

        .btn-print {
            background: #168b69;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        .stat {
            background: white;
            border-radius: 13px;
            padding: 18px;
            box-shadow: 0 3px 15px rgba(0,0,0,.03);
        }

        .stat p {
            font-size: 11px;
            color: #8491a3;
            margin-bottom: 10px;
        }

        .stat h3 {
            font-size: 25px;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .blue { background: #eaf2ff; color: #4383e7; }
        .orange { background: #fff3e4; color: #ed9b35; }
        .purple { background: #f1eaff; color: #9365dc; }
        .green { background: #e6f8f1; color: #16a47e; }
        .red { background: #ffebeb; color: #d65d5d; }
        .teal { background: #e6f8f8; color: #15999c; }

        .report-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 17px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
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
            padding: 13px;
            border-bottom: 1px solid #edf0f5;
            font-size: 11px;
            vertical-align: middle;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status.menunggu { background: #fff3d9; color: #a76a00; }
        .status.dipanggil { background: #e4f1ff; color: #2367a5; }
        .status.selesai { background: #e5f8ed; color: #187a43; }
        .status.dibatalkan { background: #ffebeb; color: #b52d2d; }

        .empty {
            text-align: center;
            padding: 30px;
            color: #8491a3;
        }

        .report-footer {
            color: #8995a5;
            font-size: 10px;
            text-align: right;
            margin-top: 15px;
        }

        @media(max-width: 900px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 700px) {
            .layout { display: block; }

            .sidebar {
                width: 100%;
                padding: 15px;
            }

            .brand { padding-bottom: 15px; }

            .menu-label { display: none; }

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

            .main { padding: 17px; }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats { grid-template-columns: repeat(2, 1fr); }
        }

        @media print {
            body { background: white; }

            .sidebar,
            .filter-card,
            .admin-label,
            .no-print {
                display: none !important;
            }

            .main {
                width: 100%;
                padding: 0;
            }

            .card,
            .stat {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }

            .stats {
                grid-template-columns: repeat(3, 1fr);
            }

            table {
                min-width: 0;
            }

            th, td {
                font-size: 9px;
                padding: 7px;
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

            <a href="pendaftaran.php">
                <i class="fa-solid fa-clipboard-list"></i>
                Pendaftaran
            </a>

            <a href="antrean.php">
                <i class="fa-solid fa-list-ol"></i>
                Antrean Pasien
            </a>

            <a href="laporan.php" class="active">
                <i class="fa-solid fa-chart-line"></i>
                Laporan
            </a>

            <a href="pengaturan.php">
                <i class="fa-solid fa-gear"></i>
                Pengaturan
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
                <h1>Laporan SmartCare</h1>
                <p>Rekap dan statistik pendaftaran pasien.</p>
            </div>

            <div class="admin-label">
                <i class="fa-solid fa-user-shield"></i>
                Petugas
            </div>
        </header>

        <section class="filter-card no-print">

            <h2><i class="fa-solid fa-filter"></i> Filter Laporan</h2>

            <p class="description">
                Pilih periode tanggal untuk menampilkan laporan pendaftaran.
            </p>

            <form method="GET" class="filters">

                <div class="field">
                    <label>Tanggal Awal</label>
                    <input
                        type="date"
                        name="tanggal_awal"
                        value="<?= e($tanggal_awal); ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label>Tanggal Akhir</label>
                    <input
                        type="date"
                        name="tanggal_akhir"
                        value="<?= e($tanggal_akhir); ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label>Status Pendaftaran</label>
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
                </div>

                <button type="submit" class="btn">
                    <i class="fa-solid fa-search"></i>
                    Tampilkan
                </button>

                <a href="laporan.php" class="btn btn-light">
                    Reset
                </a>

            </form>

        </section>

        <section class="stats">

            <div class="stat">
                <div class="stat-icon blue">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <p>Total Pendaftaran</p>
                <h3><?= $total; ?></h3>
            </div>

            <div class="stat">
                <div class="stat-icon teal">
                    <i class="fa-solid fa-users"></i>
                </div>
                <p>Pasien Terdaftar</p>
                <h3><?= $totalPasien; ?></h3>
            </div>

            <div class="stat">
                <div class="stat-icon orange">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <p>Menunggu</p>
                <h3><?= $totalMenunggu; ?></h3>
            </div>

            <div class="stat">
                <div class="stat-icon purple">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <p>Dipanggil</p>
                <h3><?= $totalDipanggil; ?></h3>
            </div>

            <div class="stat">
                <div class="stat-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <p>Selesai</p>
                <h3><?= $totalSelesai; ?></h3>
            </div>

            <div class="stat">
                <div class="stat-icon red">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
                <p>Dibatalkan</p>
                <h3><?= $totalDibatalkan; ?></h3>
            </div>

        </section>

        <section class="card">

            <div class="report-heading">
                <div>
                    <h2>Rekap Pendaftaran Harian</h2>
                    <p class="description">
                        Periode <?= e(date('d/m/Y', strtotime($tanggal_awal))); ?>
                        sampai <?= e(date('d/m/Y', strtotime($tanggal_akhir))); ?>
                    </p>
                </div>

                <button class="btn btn-print no-print" onclick="window.print()">
                    <i class="fa-solid fa-print"></i>
                    Cetak Laporan
                </button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tanggal Praktik</th>
                            <th>Total</th>
                            <th>Menunggu</th>
                            <th>Dipanggil</th>
                            <th>Selesai</th>
                            <th>Dibatalkan</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($rekap_harian->num_rows > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($r = $rekap_harian->fetch_assoc()): ?>

                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= e(date('d/m/Y', strtotime($r['tanggal']))); ?></td>
                                <td><strong><?= (int)$r['total']; ?></strong></td>
                                <td><?= (int)$r['menunggu']; ?></td>
                                <td><?= (int)$r['dipanggil']; ?></td>
                                <td><?= (int)$r['selesai']; ?></td>
                                <td><?= (int)$r['dibatalkan']; ?></td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty">
                                Belum ada data laporan pada periode ini.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </section>

        <section class="card">

            <div class="report-heading">
                <div>
                    <h2>Detail Data Pendaftaran</h2>
                    <p class="description">
                        Daftar pendaftaran sesuai periode dan filter status.
                    </p>
                </div>
            </div>

            <div class="table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Pasien</th>
                            <th>Dokter</th>
                            <th>Poliklinik</th>
                            <th>Tanggal</th>
                            <th>Antrean</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($data_laporan->num_rows > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($r = $data_laporan->fetch_assoc()): ?>

                            <tr>
                                <td><?= $no++; ?></td>

                                <td>
                                    <strong><?= e($r['nama_pasien']); ?></strong>
                                    <span style="display:block;color:#8995a5;margin-top:4px;">
                                        NIK: <?= e($r['nik']); ?>
                                    </span>
                                </td>

                                <td><?= e($r['nama_dokter']); ?></td>

                                <td><?= e($r['nama_poli']); ?></td>

                                <td><?= e(date('d/m/Y', strtotime($r['tanggal']))); ?></td>

                                <td>#<?= (int)$r['nomor_antrean']; ?></td>

                                <td>
                                    <span class="status <?= e($r['status']); ?>">
                                        <?= e(statusLabel($r['status'])); ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty">
                                Tidak ada detail pendaftaran yang ditemukan.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>

            <div class="report-footer">
                Dicetak pada <?= date('d/m/Y H:i'); ?>
            </div>

        </section>

    </main>

</div>

</body>
</html>
