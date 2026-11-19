
<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireRole("petugas");

$user_id = (int)$_SESSION['user_id'];

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_pengaturan'])) {
    $_SESSION['csrf_pengaturan'] = bin2hex(random_bytes(32));
}

$pesan = "";
$jenis_pesan = "";

// Ambil informasi akun
$stmt = $conn->prepare("
    SELECT id, nama, email, password, role
    FROM users
    WHERE id = ? AND role = 'petugas'
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$akun = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$akun) {
    exit("Akun petugas tidak ditemukan.");
}

// Proses perubahan pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $aksi = $_POST['aksi'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_pengaturan'], $token)
    ) {
        $pesan = "Permintaan tidak valid. Silakan muat ulang halaman.";
        $jenis_pesan = "error";

    } elseif ($aksi === 'profil') {

        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($nama === '' || strlen($nama) > 100) {
            $pesan = "Nama wajib diisi dan maksimal 100 karakter.";
            $jenis_pesan = "error";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pesan = "Format email tidak valid.";
            $jenis_pesan = "error";

        } else {

            // Periksa email digunakan akun lain atau tidak
            $cek = $conn->prepare("
                SELECT id FROM users
                WHERE email = ? AND id <> ?
                LIMIT 1
            ");

            $cek->bind_param("si", $email, $user_id);
            $cek->execute();

            $emailDipakai = $cek->get_result()->num_rows > 0;
            $cek->close();

            if ($emailDipakai) {
                $pesan = "Email sudah digunakan oleh akun lain.";
                $jenis_pesan = "error";

            } else {

                $update = $conn->prepare("
                    UPDATE users
                    SET nama = ?, email = ?
                    WHERE id = ? AND role = 'petugas'
                ");

                $update->bind_param("ssi", $nama, $email, $user_id);

                if ($update->execute()) {

                    $_SESSION['nama'] = $nama;
                    $_SESSION['email'] = $email;

                    $pesan = "Profil berhasil diperbarui.";
                    $jenis_pesan = "success";

                } else {
                    $pesan = "Gagal memperbarui profil.";
                    $jenis_pesan = "error";
                }

                $update->close();
            }
        }

    } elseif ($aksi === 'password') {

        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $konfirmasi = $_POST['konfirmasi_password'] ?? '';

        if (
            !password_verify($password_lama, $akun['password'])
        ) {
            $pesan = "Password lama tidak sesuai.";
            $jenis_pesan = "error";

        } elseif (strlen($password_baru) < 8) {
            $pesan = "Password baru minimal 8 karakter.";
            $jenis_pesan = "error";

        } elseif ($password_baru !== $konfirmasi) {
            $pesan = "Konfirmasi password baru tidak cocok.";
            $jenis_pesan = "error";

        } else {

            $hash = password_hash($password_baru, PASSWORD_DEFAULT);

            $update = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ? AND role = 'petugas'
            ");

            $update->bind_param("si", $hash, $user_id);

            if ($update->execute()) {
                $pesan = "Password berhasil diperbarui.";
                $jenis_pesan = "success";

                // Ambil ulang data akun agar hash terbaru digunakan
                $akun['password'] = $hash;

            } else {
                $pesan = "Gagal memperbarui password.";
                $jenis_pesan = "error";
            }

            $update->close();
        }

    } else {
        $pesan = "Jenis pengaturan tidak dikenal.";
        $jenis_pesan = "error";
    }

    // Refresh data profil setelah proses
    $stmt = $conn->prepare("
        SELECT id, nama, email, password, role
        FROM users
        WHERE id = ? AND role = 'petugas'
        LIMIT 1
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $akun = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengaturan | SmartCare</title>

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

        .profile-banner {
            background: linear-gradient(120deg, #174c80, #397fbd);
            padding: 25px;
            border-radius: 15px;
            color: white;
            display: flex;
            align-items: center;
            gap: 17px;
            margin-bottom: 23px;
        }

        .avatar {
            width: 65px;
            height: 65px;
            background: white;
            color: #1765a1;
            display: grid;
            place-items: center;
            border-radius: 50%;
            font-size: 25px;
            font-weight: 800;
        }

        .profile-banner h2 {
            font-size: 19px;
            margin-bottom: 6px;
        }

        .profile-banner p {
            font-size: 12px;
            opacity: .85;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            background: white;
            padding: 24px;
            border-radius: 15px;
            box-shadow: 0 3px 18px rgba(0,0,0,.035);
        }

        .card-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: #eaf2ff;
            color: #4383e7;
        }

        .card-heading h2 {
            font-size: 15px;
        }

        .card-heading p {
            font-size: 11px;
            color: #8993a5;
            margin-top: 4px;
        }

        .field {
            margin-bottom: 17px;
        }

        .field label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .field input {
            width: 100%;
            padding: 12px;
            border: 1px solid #dfe5ed;
            border-radius: 9px;
            font-family: inherit;
            font-size: 12px;
            outline: none;
        }

        .field input:focus {
            border-color: #4383e7;
        }

        .field small {
            display: block;
            color: #8993a5;
            font-size: 10px;
            margin-top: 6px;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #1765a1;
            color: white;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
        }

        .btn:hover {
            background: #125589;
        }

        .account-info {
            margin-top: 22px;
            background: white;
            border: 1px solid #edf0f5;
            border-radius: 15px;
            padding: 22px;
        }

        .account-info h3 {
            font-size: 14px;
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #edf0f5;
            font-size: 12px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row span {
            color: #8993a5;
        }

        .info-row strong {
            text-align: right;
        }

        @media(max-width: 900px) {
            .settings-grid {
                grid-template-columns: 1fr;
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

            <a href="laporan.php">
                <i class="fa-solid fa-chart-line"></i>
                Laporan
            </a>

            <a href="pengaturan.php" class="active">
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
            <h1>Pengaturan Akun</h1>
            <p>Kelola informasi profil dan keamanan akun petugas.</p>
        </header>

        <?php if ($pesan): ?>
            <div class="message <?= e($jenis_pesan); ?>">
                <?= e($pesan); ?>
            </div>
        <?php endif; ?>

        <section class="profile-banner">

            <div class="avatar">
                <?= strtoupper(substr($akun['nama'], 0, 1)); ?>
            </div>

            <div>
                <h2><?= e($akun['nama']); ?></h2>
                <p><?= e($akun['email']); ?></p>
                <p style="margin-top:5px;">
                    <i class="fa-solid fa-user-shield"></i>
                    Administrator / Petugas
                </p>
            </div>

        </section>

        <section class="settings-grid">

            <div class="card">

                <div class="card-heading">
                    <div class="card-icon">
                        <i class="fa-solid fa-user-pen"></i>
                    </div>

                    <div>
                        <h2>Informasi Profil</h2>
                        <p>Perbarui nama dan email akun.</p>
                    </div>
                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_pengaturan']); ?>"
                    >

                    <input type="hidden" name="aksi" value="profil">

                    <div class="field">
                        <label>Nama Lengkap</label>
                        <input
                            type="text"
                            name="nama"
                            value="<?= e($akun['nama']); ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="field">
                        <label>Email</label>
                        <input
                            type="email"
                            name="email"
                            value="<?= e($akun['email']); ?>"
                            required
                        >
                    </div>

                    <button type="submit" class="btn">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Simpan Perubahan Profil
                    </button>

                </form>

            </div>

            <div class="card">

                <div class="card-heading">
                    <div class="card-icon" style="background:#e6f8f1;color:#16a47e;">
                        <i class="fa-solid fa-lock"></i>
                    </div>

                    <div>
                        <h2>Keamanan Password</h2>
                        <p>Ganti password akun petugas.</p>
                    </div>
                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_pengaturan']); ?>"
                    >

                    <input type="hidden" name="aksi" value="password">

                    <div class="field">
                        <label>Password Lama</label>
                        <input
                            type="password"
                            name="password_lama"
                            placeholder="Masukkan password lama"
                            required
                        >
                    </div>

                    <div class="field">
                        <label>Password Baru</label>
                        <input
                            type="password"
                            name="password_baru"
                            placeholder="Minimal 8 karakter"
                            minlength="8"
                            required
                        >
                        <small>Password baru minimal 8 karakter.</small>
                    </div>

                    <div class="field">
                        <label>Konfirmasi Password Baru</label>
                        <input
                            type="password"
                            name="konfirmasi_password"
                            placeholder="Ulangi password baru"
                            minlength="8"
                            required
                        >
                    </div>

                    <button type="submit" class="btn">
                        <i class="fa-solid fa-key"></i>
                        Perbarui Password
                    </button>

                </form>

            </div>

        </section>

        <section class="account-info">

            <h3>
                <i class="fa-solid fa-circle-info"></i>
                Informasi Akun
            </h3>

            <div class="info-row">
                <span>ID Pengguna</span>
                <strong>#<?= (int)$akun['id']; ?></strong>
            </div>

            <div class="info-row">
                <span>Nama Pengguna</span>
                <strong><?= e($akun['nama']); ?></strong>
            </div>

            <div class="info-row">
                <span>Email Terdaftar</span>
                <strong><?= e($akun['email']); ?></strong>
            </div>

            <div class="info-row">
                <span>Hak Akses</span>
                <strong>Petugas</strong>
            </div>

        </section>

    </main>

</div>

</body>
</html>
