
<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireRole("petugas");

// Fungsi mengambil jumlah data
function hitungData($conn, $query) {
    $result = mysqli_query($conn, $query);

    if (!$result) {
        return 0;
    }

    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

// Statistik dashboard
$totalPasien = hitungData(
    $conn,
    "SELECT COUNT(*) AS total FROM pasien"
);

$totalDokter = hitungData(
    $conn,
    "SELECT COUNT(*) AS total FROM dokter"
);

$totalPoli = hitungData(
    $conn,
    "SELECT COUNT(*) AS total FROM poliklinik"
);

$totalAkun = hitungData(
    $conn,
    "SELECT COUNT(*) AS total FROM users"
);

// Data pasien terbaru
$pasienTerbaru = mysqli_query(
    $conn,
    "SELECT u.nama, u.email, p.nik, p.no_hp
     FROM pasien p
     JOIN users u ON p.user_id = u.id
     ORDER BY p.id DESC
     LIMIT 5"
);

$namaPetugas = htmlspecialchars($_SESSION['nama']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Petugas | SmartCare</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body class="dashboard-body">

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-brand">
            <div class="sidebar-logo">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>

            <div>
                <h2>SmartCare<span>.</span></h2>
                <small>Hospital Management</small>
            </div>
        </div>

        <div class="sidebar-profile">
            <div class="profile-avatar">
                <i class="fa-solid fa-user-tie"></i>
            </div>

            <div>
                <strong><?= $namaPetugas ?></strong>
                <small>Administrator</small>
            </div>

            <span class="online-dot"></span>
        </div>

        <div class="sidebar-label">MENU UTAMA</div>

        <nav class="sidebar-menu">

            <a href="dashboard.php" class="active">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>

            <a href="pasien.php">
                <i class="fa-solid fa-users"></i>
                <span>Data Pasien</span>
            </a>

           <a href="dokter.php">
    <i class="fa-solid fa-user-doctor"></i>
    <span>Data Dokter</span>
</a>

            <a href="poliklinik.php">
    <i class="fa-solid fa-hospital"></i>
    <span>Poliklinik</span>
</a>
            <a href="jadwal.php">
    <i class="fa-solid fa-calendar-days"></i>
    <span>Jadwal Dokter</span>
</a>

            <div class="sidebar-label">PELAYANAN</div>

         <a href="pendaftaran.php">
    <i class="fa-solid fa-clipboard-list"></i>
    Pendaftaran
</a>

            <a href="antrean.php">
                <i class="fa-solid fa-ticket"></i>
                <span>Antrean Pasien</span>
            </a>

            <a href="laporan.php">
                <i class="fa-solid fa-chart-line"></i>
                <span>Laporan</span>
            </a>

            <div class="sidebar-label">LAINNYA</div>

            <a href="pengaturan.php">
                <i class="fa-solid fa-gear"></i>
                <span>Pengaturan</span>
            </a>

        </nav>

        <div class="sidebar-bottom">
            <a href="../logout.php" class="logout-link"
            onclick="return confirm('Yakin ingin keluar dari akun?')">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Keluar</span>
            </a>
        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">

            <div class="topbar-left">

                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <h2>Dashboard</h2>
                    <p>Ringkasan informasi SmartCare</p>
                </div>

            </div>

            <div class="topbar-right">

                <div class="topbar-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?= date('d M Y') ?>
                </div>

                <div class="topbar-avatar">
                    <i class="fa-solid fa-user-tie"></i>
                </div>

            </div>

        </header>


        <!-- CONTENT -->
        <section class="dashboard-content">

            <div class="welcome-section">

                <div>
                    <span class="welcome-label">ADMINISTRATOR PANEL</span>

                    <h1>Halo, <?= $namaPetugas ?>! 👋</h1>

                    <p>
                        Selamat datang kembali. Berikut ringkasan
                        data dan aktivitas sistem SmartCare.
                    </p>
                </div>

                <div class="welcome-icon">
                    <i class="fa-solid fa-hospital-user"></i>
                </div>

            </div>


            <!-- STATISTICS -->
            <div class="statistics-grid">

                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon stat-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>

                        <span class="stat-tag">Pasien</span>
                    </div>

                    <h2><?= number_format($totalPasien) ?></h2>

                    <p>Total pasien terdaftar</p>

                </div>


                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon stat-green">
                            <i class="fa-solid fa-user-doctor"></i>
                        </div>

                        <span class="stat-tag">Dokter</span>
                    </div>

                    <h2><?= number_format($totalDokter) ?></h2>

                    <p>Total dokter terdaftar</p>

                </div>


                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon stat-purple">
                            <i class="fa-solid fa-hospital"></i>
                        </div>

                        <span class="stat-tag">Poliklinik</span>
                    </div>

                    <h2><?= number_format($totalPoli) ?></h2>

                    <p>Total poliklinik</p>

                </div>


                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon stat-orange">
                            <i class="fa-solid fa-user-group"></i>
                        </div>

                        <span class="stat-tag">Pengguna</span>
                    </div>

                    <h2><?= number_format($totalAkun) ?></h2>

                    <p>Total akun sistem</p>

                </div>

            </div>


            <!-- QUICK ACTION -->
            <div class="content-card quick-card">

                <div class="card-heading">
                    <div>
                        <h3>Akses Cepat</h3>
                        <p>Kelola informasi rumah sakit dengan mudah.</p>
                    </div>
                </div>

                <div class="quick-grid">

                    <a href="pasien.php" class="quick-item">
                        <div class="quick-icon stat-blue">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <span>Tambah Pasien</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    <a href="dokter.php" class="quick-item">
                        <div class="quick-icon stat-green">
                            <i class="fa-solid fa-user-doctor"></i>
                        </div>
                        <span>Tambah Dokter</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    <a href="poliklinik.php" class="quick-item">
                        <div class="quick-icon stat-purple">
                            <i class="fa-solid fa-hospital"></i>
                        </div>
                        <span>Kelola Poliklinik</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    <a href="jadwal.php" class="quick-item">
                        <div class="quick-icon stat-orange">
                            <i class="fa-solid fa-calendar-plus"></i>
                        </div>
                        <span>Atur Jadwal</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </div>

            </div>


            <!-- PATIENT TABLE -->
            <div class="content-card">

                <div class="card-heading">

                    <div>
                        <h3>Pasien Terbaru</h3>
                        <p>Daftar pasien yang baru terdaftar di SmartCare.</p>
                    </div>

                    <a href="pasien.php">
    <i class="fa-solid fa-users"></i>
    <span>Data Pasien</span>
</a>

                </div>

                <div class="table-wrapper">

                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>Nama Pasien</th>
                                <th>NIK</th>
                                <th>Email</th>
                                <th>Nomor HP</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if ($pasienTerbaru && mysqli_num_rows($pasienTerbaru) > 0): ?>

                            <?php while ($row = mysqli_fetch_assoc($pasienTerbaru)): ?>

                                <tr>
                                    <td>
                                        <div class="patient-name">
                                            <div class="patient-avatar">
                                                <i class="fa-solid fa-user"></i>
                                            </div>

                                            <strong>
                                                <?= htmlspecialchars($row['nama']) ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td><?= htmlspecialchars($row['nik']) ?></td>

                                    <td><?= htmlspecialchars($row['email']) ?></td>

                                    <td><?= htmlspecialchars($row['no_hp']) ?></td>

                                    <td>
                                        <span class="status-active">
                                            Terdaftar
                                        </span>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="empty-data">
                                    Belum ada pasien yang terdaftar.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- SYSTEM INFO -->
            <div class="system-info">

                <div class="system-info-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div>
                    <strong>Sistem SmartCare Aktif</strong>

                    <p>
                        Data pasien, dokter, dan poliklinik
                        dikelola melalui database terintegrasi.
                    </p>
                </div>

                <span class="system-status">
                    <i class="fa-solid fa-circle"></i> Online
                </span>

            </div>

        </section>

    </main>

</div>

<script src="../assets/js/dashboard.js"></script>

</body>
</html>
