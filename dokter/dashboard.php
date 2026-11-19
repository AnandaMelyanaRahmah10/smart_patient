
<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireRole("dokter");

$user_id = (int) $_SESSION['user_id'];

// Ambil profil dokter yang sedang login
$stmt = $conn->prepare("
    SELECT
        d.id AS dokter_id,
        u.nama,
        u.email,
        d.spesialisasi,
        d.no_str,
        d.no_hp,
        d.alamat,
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
    exit("Profil dokter tidak ditemukan. Silakan hubungi petugas.");
}

$dokter_id = (int) $dokter['dokter_id'];

// Statistik jadwal dokter
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_jadwal,
        COALESCE(SUM(status = 'aktif'), 0) AS jadwal_aktif,
        COALESCE(SUM(
            CASE WHEN status = 'aktif' THEN kuota ELSE 0 END
        ), 0) AS total_kuota
    FROM jadwal
    WHERE dokter_id = ?
");
$stmt->bind_param("i", $dokter_id);
$stmt->execute();
$statistik = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ambil seluruh jadwal milik dokter
$stmt = $conn->prepare("
    SELECT *
    FROM jadwal
    WHERE dokter_id = ?
    ORDER BY tanggal DESC, jam_mulai DESC
");
$stmt->bind_param("i", $dokter_id);
$stmt->execute();
$jadwal = $stmt->get_result();
$stmt->close();

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatTanggal($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'
    ];

    $tanggalObj = strtotime($tanggal);

    return date('j', $tanggalObj) . ' ' .
        $bulan[(int) date('n', $tanggalObj)] . ' ' .
        date('Y', $tanggalObj);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dokter | SmartCare</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            color: #202b3c;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: #123c69;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 35px;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            background: #ffffff;
            color: #123c69;
            display: grid;
            place-items: center;
            border-radius: 13px;
            font-size: 21px;
        }

        .brand h2 {
            font-size: 21px;
            font-weight: 800;
        }

        .brand small {
            display: block;
            font-size: 11px;
            color: #bdd3eb;
            margin-top: 3px;
        }

        .menu-label {
            color: #a9c1dd;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 10px 12px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            margin: 5px 0;
            color: #d8e5f4;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            transition: .2s;
        }

        .menu a:hover,
        .menu a.active {
            background: #245486;
            color: white;
        }

        .menu a.active {
            font-weight: 700;
        }

        .menu a i {
            width: 18px;
        }

        .sidebar-bottom {
            margin-top: 45px;
            border-top: 1px solid #41648a;
            padding-top: 18px;
        }

        .sidebar-bottom a {
            color: #ffd2d2;
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
            margin-bottom: 28px;
        }

        .topbar h1 {
            font-size: 25px;
            font-weight: 800;
        }

        .topbar p {
            font-size: 13px;
            color: #8793a5;
            margin-top: 6px;
        }

        .doctor-mini {
            display: flex;
            align-items: center;
            gap: 11px;
            background: white;
            padding: 10px 15px;
            border-radius: 13px;
            box-shadow: 0 3px 15px rgba(0,0,0,.04);
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #e2efff;
            color: #20578d;
            display: grid;
            place-items: center;
            font-size: 18px;
        }

        .doctor-mini strong {
            font-size: 13px;
            display: block;
        }

        .doctor-mini small {
            font-size: 11px;
            color: #8793a5;
        }

        .welcome {
            background: linear-gradient(120deg, #174d7d, #2878ad);
            color: white;
            padding: 27px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .welcome h2 {
            font-size: 22px;
            margin-bottom: 8px;
        }

        .welcome p {
            color: #d9eafa;
            font-size: 13px;
            line-height: 1.7;
        }

        .welcome-icon {
            font-size: 65px;
            opacity: .3;
            padding-right: 15px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 21px;
            border-radius: 15px;
            box-shadow: 0 4px 18px rgba(30,50,80,.04);
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #e8f2ff;
            color: #2874b5;
            font-size: 18px;
        }

        .stat-card h3 {
            font-size: 27px;
            margin-bottom: 4px;
        }

        .stat-card p {
            color: #8793a5;
            font-size: 12px;
        }

        .content-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 18px rgba(30,50,80,.04);
            margin-bottom: 22px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .section-heading h2 {
            font-size: 17px;
        }

        .section-heading p {
            color: #8793a5;
            font-size: 12px;
            margin-top: 5px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .profile-item {
            background: #f7f9fc;
            padding: 15px;
            border-radius: 11px;
        }

        .profile-item small {
            display: block;
            color: #8793a5;
            font-size: 11px;
            margin-bottom: 7px;
        }

        .profile-item strong {
            font-size: 13px;
            overflow-wrap: anywhere;
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
            color: #8793a5;
            font-size: 11px;
            background: #f7f9fc;
        }

        td {
            padding: 15px 13px;
            border-bottom: 1px solid #edf0f5;
            font-size: 12px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        .aktif {
            background: #e2f8ec;
            color: #18864b;
        }

        .nonaktif {
            background: #ffe9e9;
            color: #c13d3d;
        }

        .empty {
            text-align: center;
            padding: 35px 15px;
            color: #8793a5;
        }

        .empty i {
            font-size: 30px;
            margin-bottom: 12px;
            color: #aab9ca;
        }

        .empty p {
            font-size: 13px;
            line-height: 1.7;
        }

        .note {
            background: #fff8e8;
            border: 1px solid #f4e5bd;
            color: #80621d;
            padding: 15px;
            border-radius: 12px;
            font-size: 12px;
            line-height: 1.7;
        }

        @media(max-width: 900px) {
            .sidebar {
                width: 210px;
            }

            .main {
                padding: 22px;
            }
        }

        @media(max-width: 650px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 17px;
            }

            .brand {
                padding-bottom: 15px;
            }

            .menu {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .menu-label,
            .sidebar-bottom {
                display: none;
            }

            .menu a {
                margin: 0;
                padding: 10px;
                font-size: 12px;
            }

            .main {
                padding: 18px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .welcome-icon {
                display: none;
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
            <a href="dashboard.php" class="active">
                <i class="fa-solid fa-house"></i> Dashboard
            </a>

            <a href="#jadwal">
                <i class="fa-solid fa-calendar-days"></i> Jadwal Saya
            </a>

           <a href="antrean.php">
    <i class="fa-solid fa-users"></i>
    Antrean Pasien
</a>

            <a href="#profil">
                <i class="fa-solid fa-user-doctor"></i> Profil Dokter
            </a>
        </nav>

        <div class="sidebar-bottom">
            <nav class="menu">
                <a href="../logout.php">
                    <i class="fa-solid fa-right-from-bracket"></i> Keluar
                </a>
            </nav>
        </div>

    </aside>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Dashboard Dokter</h1>
                <p>Selamat datang di sistem Smart Patient Registration.</p>
            </div>

            <div class="doctor-mini">
                <div class="avatar">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
                <div>
                    <strong><?= e($dokter['nama']); ?></strong>
                    <small><?= e($dokter['spesialisasi']); ?></small>
                </div>
            </div>
        </header>

        <section class="welcome">
            <div>
                <h2>Halo, <?= e($dokter['nama']); ?> 👋</h2>
                <p>
                    Selamat bekerja di SmartCare.<br>
                    Berikut informasi jadwal dan profil praktik Anda.
                </p>
            </div>

            <div class="welcome-icon">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
        </section>

        <section class="stats">

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                </div>
                <h3><?= (int) $statistik['total_jadwal']; ?></h3>
                <p>Total Jadwal Praktik</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <h3><?= (int) $statistik['jadwal_aktif']; ?></h3>
                <p>Jadwal Aktif</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <h3><?= (int) $statistik['total_kuota']; ?></h3>
                <p>Total Kuota dari Jadwal Aktif</p>
            </div>

        </section>

        <section class="content-card" id="profil">

            <div class="section-heading">
                <div>
                    <h2>Profil Dokter</h2>
                    <p>Informasi akun dan data praktik Anda.</p>
                </div>
            </div>

            <div class="profile-grid">

                <div class="profile-item">
                    <small>Nama Dokter</small>
                    <strong><?= e($dokter['nama']); ?></strong>
                </div>

                <div class="profile-item">
                    <small>Email</small>
                    <strong><?= e($dokter['email']); ?></strong>
                </div>

                <div class="profile-item">
                    <small>Spesialisasi</small>
                    <strong><?= e($dokter['spesialisasi']); ?></strong>
                </div>

                <div class="profile-item">
                    <small>Poliklinik</small>
                    <strong><?= e($dokter['nama_poli']); ?></strong>
                </div>

                <div class="profile-item">
                    <small>Nomor STR</small>
                    <strong><?= e($dokter['no_str'] ?: '-'); ?></strong>
                </div>

                <div class="profile-item">
                    <small>Nomor HP</small>
                    <strong><?= e($dokter['no_hp'] ?: '-'); ?></strong>
                </div>

            </div>

        </section>

        <section class="content-card" id="jadwal">

            <div class="section-heading">
                <div>
                    <h2>Jadwal Praktik Saya</h2>
                    <p>Jadwal yang telah diatur oleh petugas.</p>
                </div>
            </div>

            <?php if ($jadwal->num_rows > 0): ?>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Tanggal</th>
                                <th>Jam Praktik</th>
                                <th>Kuota</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php $no = 1; ?>
                            <?php while ($row = $jadwal->fetch_assoc()): ?>

                                <tr>
                                    <td><?= $no++; ?></td>

                                    <td>
                                        <?= e(formatTanggal($row['tanggal'])); ?>
                                    </td>

                                    <td>
                                        <?= e(substr($row['jam_mulai'], 0, 5)); ?>
                                        -
                                        <?= e(substr($row['jam_selesai'], 0, 5)); ?>
                                    </td>

                                    <td><?= (int) $row['kuota']; ?> pasien</td>

                                    <td>
                                        <span class="status <?= $row['status'] === 'aktif' ? 'aktif' : 'nonaktif'; ?>">
                                            <?= e(ucfirst($row['status'])); ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="empty">
                    <i class="fa-solid fa-calendar-xmark"></i>
                    <p>
                        Belum ada jadwal praktik yang tersedia.<br>
                        Silakan hubungi petugas untuk mengatur jadwal.
                    </p>
                </div>

            <?php endif; ?>

        </section>

        <section class="content-card" id="antrean">

            <div class="section-heading">
                <div>
                    <h2>Antrean Pasien</h2>
                    <p>Informasi pendaftaran pasien yang ditujukan kepada Anda.</p>
                </div>
            </div>

            <div class="note">
                <i class="fa-solid fa-circle-info"></i>
                Fitur antrean pasien sedang dalam tahap pengembangan.
                Data antrean akan ditampilkan setelah modul pendaftaran pasien
                selesai dibuat.
            </div>

        </section>

    </main>

</div>

</body>
</html>
