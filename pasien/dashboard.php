
<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireRole("pasien");

$user_id = (int) $_SESSION['user_id'];

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tanggalID($tanggal) {
    if (!$tanggal) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus', 'September',
        'Oktober', 'November', 'Desember'
    ];

    $waktu = strtotime($tanggal);
    return date('j', $waktu) . ' ' .
           $bulan[(int)date('n', $waktu)] . ' ' .
           date('Y', $waktu);
}

function statusBadge($status) {
    $class = [
        'menunggu' => 'waiting',
        'dipanggil' => 'called',
        'selesai' => 'done',
        'dibatalkan' => 'cancelled'
    ];

    $label = [
        'menunggu' => 'Menunggu',
        'dipanggil' => 'Dipanggil',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan'
    ];

    $className = $class[$status] ?? 'waiting';
    $text = $label[$status] ?? ucfirst($status);

    return '<span class="status ' . $className . '">' .
           e($text) . '</span>';
}

// Ambil data profil pasien
$stmt = $conn->prepare("
    SELECT p.id, p.nik, p.tanggal_lahir, p.jenis_kelamin,
           p.no_hp, p.alamat, u.nama, u.email
    FROM pasien p
    JOIN users u ON u.id = p.user_id
    WHERE p.user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pasien = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pasien) {
    exit("Data profil pasien tidak ditemukan. Silakan hubungi petugas.");
}

$pasien_id = (int) $pasien['id'];

// Statistik pendaftaran pasien
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'menunggu') AS menunggu,
        SUM(status = 'dipanggil') AS dipanggil,
        SUM(status = 'selesai') AS selesai
    FROM pendaftaran
    WHERE pasien_id = ?
");
$stmt->bind_param("i", $pasien_id);
$stmt->execute();
$statistik = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalPendaftaran = (int)($statistik['total'] ?? 0);
$totalMenunggu = (int)($statistik['menunggu'] ?? 0);
$totalDipanggil = (int)($statistik['dipanggil'] ?? 0);
$totalSelesai = (int)($statistik['selesai'] ?? 0);

// Pendaftaran aktif pasien yang jadwalnya belum lewat
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.nomor_antrean,
        p.keluhan,
        p.status,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        u.nama AS nama_dokter,
        d.spesialisasi,
        po.nama_poli
    FROM pendaftaran p
    JOIN jadwal j ON j.id = p.jadwal_id
    JOIN dokter d ON d.id = j.dokter_id
    JOIN users u ON u.id = d.user_id
    JOIN poliklinik po ON po.id = d.poliklinik_id
    WHERE p.pasien_id = ?
      AND p.status IN ('menunggu', 'dipanggil')
      AND j.tanggal >= CURDATE()
    ORDER BY j.tanggal ASC, j.jam_mulai ASC
    LIMIT 1
");
$stmt->bind_param("i", $pasien_id);
$stmt->execute();
$pendaftaranAktif = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Lima riwayat pendaftaran terakhir
$stmt = $conn->prepare("
    SELECT
        p.nomor_antrean,
        p.status,
        p.created_at,
        j.tanggal,
        j.jam_mulai,
        u.nama AS nama_dokter,
        po.nama_poli
    FROM pendaftaran p
    JOIN jadwal j ON j.id = p.jadwal_id
    JOIN dokter d ON d.id = j.dokter_id
    JOIN users u ON u.id = d.user_id
    JOIN poliklinik po ON po.id = d.poliklinik_id
    WHERE p.pasien_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $pasien_id);
$stmt->execute();
$riwayat = $stmt->get_result();
$stmt->close();

// Jadwal dokter yang masih memiliki kuota
$jadwalTersedia = $conn->query("
    SELECT
        j.id,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        j.kuota,
        u.nama AS nama_dokter,
        d.spesialisasi,
        po.nama_poli,
        (
            SELECT COUNT(*)
            FROM pendaftaran p
            WHERE p.jadwal_id = j.id
              AND p.status <> 'dibatalkan'
        ) AS terisi
    FROM jadwal j
    JOIN dokter d ON d.id = j.dokter_id
    JOIN users u ON u.id = d.user_id
    JOIN poliklinik po ON po.id = d.poliklinik_id
    WHERE j.status = 'aktif'
      AND j.tanggal >= CURDATE()
    HAVING terisi < kuota
    ORDER BY j.tanggal ASC, j.jam_mulai ASC
    LIMIT 4
");

$namaPasien = $pasien['nama'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pasien | SmartCare</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f5f7fb;
            color: #172033;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: #ffffff;
            border-right: 1px solid #e9edf4;
            padding: 28px 18px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px;
            margin-bottom: 42px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            background: #e7f7f3;
            color: #0b9b80;
            border-radius: 13px;
            display: grid;
            place-items: center;
            font-size: 21px;
        }

        .brand h2 {
            font-size: 21px;
            font-weight: 800;
            color: #172033;
        }

        .brand span {
            color: #0b9b80;
        }

        .menu-label {
            font-size: 10px;
            font-weight: 800;
            color: #a1a9b8;
            letter-spacing: 1.5px;
            padding: 0 12px;
            margin-bottom: 14px;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .menu a {
            padding: 13px 14px;
            display: flex;
            align-items: center;
            gap: 13px;
            color: #788397;
            font-size: 13px;
            font-weight: 600;
            border-radius: 11px;
            transition: .2s;
        }

        .menu a:hover,
        .menu a.active {
            color: #0b9b80;
            background: #eaf8f5;
        }

        .menu a i {
            width: 19px;
            font-size: 16px;
        }

        .sidebar-bottom {
            position: absolute;
            bottom: 25px;
            left: 18px;
            right: 18px;
        }

        .sidebar-bottom a {
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 13px;
            color: #e05252;
            font-size: 13px;
            font-weight: 700;
            border-radius: 10px;
            background: #fff2f2;
        }

        .main {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 30px 38px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .topbar h1 {
            font-size: 23px;
            font-weight: 800;
        }

        .topbar p {
            color: #8993a5;
            font-size: 12px;
            margin-top: 6px;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .avatar {
            width: 42px;
            height: 42px;
            background: #dff4ef;
            color: #0b9b80;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 800;
        }

        .profile strong {
            display: block;
            font-size: 12px;
        }

        .profile small {
            color: #8993a5;
            font-size: 10px;
        }

        .welcome {
            background: linear-gradient(120deg, #087e70, #13b99a);
            color: white;
            border-radius: 19px;
            padding: 28px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            overflow: hidden;
        }

        .welcome h2 {
            font-size: 22px;
            margin-bottom: 9px;
        }

        .welcome p {
            font-size: 12px;
            opacity: .88;
            line-height: 1.7;
        }

        .welcome-icon {
            font-size: 75px;
            opacity: .22;
            padding-right: 20px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            background: white;
            color: #078b77;
            padding: 12px 17px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            margin-top: 17px;
            transition: .2s;
        }

        .button:hover {
            transform: translateY(-2px);
        }

        .section-title {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 27px;
        }

        .stat-card {
            background: white;
            border: 1px solid #edf0f5;
            padding: 19px;
            border-radius: 15px;
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 37px;
            height: 37px;
            border-radius: 11px;
            display: grid;
            place-items: center;
        }

        .stat-card h3 {
            font-size: 25px;
            font-weight: 800;
        }

        .stat-card p {
            font-size: 11px;
            color: #8993a5;
            margin-top: 5px;
        }

        .blue { background: #eaf2ff; color: #4383e7; }
        .orange { background: #fff3e4; color: #ed9b35; }
        .purple { background: #f1eaff; color: #9365dc; }
        .green { background: #e6f8f1; color: #16a47e; }

        .content-grid {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .panel {
            background: white;
            border: 1px solid #edf0f5;
            border-radius: 15px;
            padding: 21px;
            margin-bottom: 20px;
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .panel-heading h3 {
            font-size: 14px;
            font-weight: 800;
        }

        .panel-heading small {
            color: #0b9b80;
            font-size: 11px;
            font-weight: 700;
        }

        .queue-box {
            background: #f1fbf8;
            border: 1px dashed #9bd9c9;
            border-radius: 13px;
            padding: 20px;
            text-align: center;
        }

        .queue-box small {
            color: #6b9389;
            font-size: 11px;
            font-weight: 700;
        }

        .queue-number {
            color: #078b77;
            font-size: 42px;
            font-weight: 800;
            margin: 8px 0;
        }

        .queue-box p {
            color: #738b84;
            font-size: 11px;
        }

        .detail-list {
            margin-top: 18px;
            display: grid;
            gap: 13px;
        }

        .detail-item {
            display: flex;
            gap: 11px;
            align-items: flex-start;
            font-size: 11px;
        }

        .detail-item i {
            color: #0b9b80;
            width: 16px;
            margin-top: 2px;
        }

        .detail-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
        }

        .detail-item span {
            color: #8993a5;
        }

        .empty {
            text-align: center;
            padding: 25px 10px;
            color: #8993a5;
            font-size: 12px;
        }

        .empty i {
            font-size: 30px;
            color: #c4d5d1;
            margin-bottom: 12px;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .waiting { background: #fff4df; color: #c98720; }
        .called { background: #e8efff; color: #4775d6; }
        .done { background: #e6f8f1; color: #168b69; }
        .cancelled { background: #fff0f0; color: #d65d5d; }

        .schedule-list {
            display: grid;
            gap: 12px;
        }

        .schedule-item {
            border: 1px solid #edf0f5;
            border-radius: 12px;
            padding: 13px;
        }

        .schedule-item strong {
            font-size: 12px;
            display: block;
            margin-bottom: 5px;
        }

        .schedule-item p {
            color: #8993a5;
            font-size: 10px;
            line-height: 1.8;
        }

        .schedule-item .quota {
            display: inline-block;
            margin-top: 7px;
            color: #0b9b80;
            background: #eaf8f5;
            padding: 5px 8px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 700;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 570px;
        }

        th {
            text-align: left;
            font-size: 10px;
            color: #8993a5;
            background: #f8fafc;
            padding: 12px;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f2f6;
            font-size: 11px;
        }

        td strong {
            font-size: 11px;
        }

        .footer {
            text-align: center;
            color: #a0a8b6;
            font-size: 10px;
            padding: 20px 0 5px;
        }

        @media(max-width: 1050px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 700px) {
            .sidebar {
                width: 68px;
                padding: 20px 8px;
            }

            .brand {
                justify-content: center;
                padding: 0;
            }

            .brand h2,
            .menu-label,
            .menu a span,
            .sidebar-bottom span {
                display: none;
            }

            .menu a,
            .sidebar-bottom a {
                justify-content: center;
                padding: 14px 5px;
            }

            .main {
                width: calc(100% - 68px);
                margin-left: 68px;
                padding: 22px 14px;
            }

            .topbar {
                align-items: flex-start;
            }

            .topbar h1 {
                font-size: 18px;
            }

            .profile {
                display: none;
            }

            .welcome {
                padding: 22px;
            }

            .welcome h2 {
                font-size: 18px;
            }

            .welcome-icon {
                display: none;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 14px;
            }

            .stat-card h3 {
                font-size: 21px;
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
            <h2>Smart<span>Care</span></h2>
        </div>

        <div class="menu-label">MENU PASIEN</div>

        <nav class="menu">
            <a href="dashboard.php" class="active">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>

            <a href="pendaftaran.php">
                <i class="fa-solid fa-calendar-plus"></i>
                <span>Daftar Berobat</span>
            </a>

            <a href="#riwayat">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Riwayat Pendaftaran</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <a href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Keluar</span>
            </a>
        </div>
    </aside>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Dashboard Pasien</h1>
                <p>Selamat datang di layanan kesehatan SmartCare.</p>
            </div>

            <div class="profile">
                <div class="avatar">
                    <?= strtoupper(substr($namaPasien, 0, 1)); ?>
                </div>
                <div>
                    <strong><?= e($namaPasien); ?></strong>
                    <small>Pasien SmartCare</small>
                </div>
            </div>
        </header>

        <section class="welcome">
            <div>
                <h2>Halo, <?= e($namaPasien); ?>! 👋</h2>
                <p>
                    Selamat datang di SmartCare. Sekarang kamu bisa
                    melihat jadwal dokter dan mengelola pendaftaran
                    berobat dengan lebih mudah.
                </p>

                <a href="pendaftaran.php" class="button">
                    <i class="fa-solid fa-calendar-plus"></i>
                    Daftar Berobat Sekarang
                </a>
            </div>

            <div class="welcome-icon">
                <i class="fa-solid fa-hospital-user"></i>
            </div>
        </section>

        <h3 class="section-title">Ringkasan Pendaftaran</h3>

        <section class="stats">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                </div>
                <h3><?= $totalPendaftaran; ?></h3>
                <p>Total Pendaftaran</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon orange">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>
                <h3><?= $totalMenunggu; ?></h3>
                <p>Menunggu</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon purple">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                </div>
                <h3><?= $totalDipanggil; ?></h3>
                <p>Sedang Dipanggil</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <h3><?= $totalSelesai; ?></h3>
                <p>Pemeriksaan Selesai</p>
            </div>
        </section>

        <section class="content-grid">

            <div class="panel">
                <div class="panel-heading">
                    <h3><i class="fa-solid fa-ticket"></i> Antrean Aktif</h3>
                    <small>Informasi antrean</small>
                </div>

                <?php if ($pendaftaranAktif): ?>

                    <div class="queue-box">
                        <small>NOMOR ANTREAN KAMU</small>

                        <div class="queue-number">
                            <?= e($pendaftaranAktif['nomor_antrean']); ?>
                        </div>

                        <?= statusBadge($pendaftaranAktif['status']); ?>

                        <p style="margin-top:10px;">
                            Simpan nomor antrean ini untuk keperluan pemeriksaan.
                        </p>
                    </div>

                    <div class="detail-list">
                        <div class="detail-item">
                            <i class="fa-solid fa-user-doctor"></i>
                            <div>
                                <strong><?= e($pendaftaranAktif['nama_dokter']); ?></strong>
                                <span><?= e($pendaftaranAktif['spesialisasi']); ?></span>
                            </div>
                        </div>

                        <div class="detail-item">
                            <i class="fa-solid fa-hospital"></i>
                            <div>
                                <strong><?= e($pendaftaranAktif['nama_poli']); ?></strong>
                                <span>Poliklinik tujuan</span>
                            </div>
                        </div>

                        <div class="detail-item">
                            <i class="fa-solid fa-calendar"></i>
                            <div>
                                <strong><?= tanggalID($pendaftaranAktif['tanggal']); ?></strong>
                                <span>
                                    <?= date('H:i', strtotime($pendaftaranAktif['jam_mulai'])); ?>
                                    -
                                    <?= date('H:i', strtotime($pendaftaranAktif['jam_selesai'])); ?>
                                    WIB
                                </span>
                            </div>
                        </div>
                    </div>

                <?php else: ?>

                    <div class="empty">
                        <i class="fa-solid fa-calendar-xmark"></i>
                        <p>Belum ada antrean aktif.</p>
                        <a href="pendaftaran.php" class="button">
                            Buat Pendaftaran
                        </a>
                    </div>

                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-heading">
                    <h3><i class="fa-solid fa-calendar-days"></i> Jadwal Dokter</h3>
                    <a href="pendaftaran.php">
                        <small>Lihat semua</small>
                    </a>
                </div>

                <div class="schedule-list">
                    <?php if ($jadwalTersedia && $jadwalTersedia->num_rows > 0): ?>

                        <?php while ($j = $jadwalTersedia->fetch_assoc()): ?>

                            <div class="schedule-item">
                                <strong><?= e($j['nama_dokter']); ?></strong>

                                <p>
                                    <?= e($j['nama_poli']); ?> ·
                                    <?= e($j['spesialisasi']); ?><br>

                                    <?= tanggalID($j['tanggal']); ?><br>

                                    <?= date('H:i', strtotime($j['jam_mulai'])); ?>
                                    -
                                    <?= date('H:i', strtotime($j['jam_selesai'])); ?>
                                    WIB
                                </p>

                                <span class="quota">
                                    Sisa kuota:
                                    <?= max(0, (int)$j['kuota'] - (int)$j['terisi']); ?>
                                </span>
                            </div>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <div class="empty">
                            <i class="fa-solid fa-calendar-xmark"></i>
                            <p>Belum ada jadwal dokter tersedia.</p>
                        </div>

                    <?php endif; ?>
                </div>
            </div>

        </section>

        <section class="panel" id="riwayat">
            <div class="panel-heading">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Pendaftaran</h3>
                <small>5 data terakhir</small>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No. Antrean</th>
                            <th>Dokter</th>
                            <th>Poliklinik</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($riwayat->num_rows > 0): ?>

                        <?php while ($r = $riwayat->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    <strong>#<?= e($r['nomor_antrean']); ?></strong>
                                </td>

                                <td><?= e($r['nama_dokter']); ?></td>

                                <td><?= e($r['nama_poli']); ?></td>

                                <td>
                                    <?= tanggalID($r['tanggal']); ?>
                                    <br>
                                    <span style="color:#8993a5;">
                                        <?= date('H:i', strtotime($r['jam_mulai'])); ?> WIB
                                    </span>
                                </td>

                                <td><?= statusBadge($r['status']); ?></td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" style="text-align:center;padding:25px;color:#8993a5;">
                                Belum ada riwayat pendaftaran.
                            </td>
                        </tr>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="footer">
            © <?= date('Y'); ?> SmartCare — Smart Patient Registration
            <br>
            Rumah Sakit Politeknik LP3I Jakarta
        </footer>

    </main>
</div>
</body>
</html>
