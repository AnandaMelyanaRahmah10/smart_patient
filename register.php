
<?php
require_once "config/database.php";
require_once "config/auth.php";

if (isLogin()) {
    redirectByRole($_SESSION['role']);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    if (
        !$nama || !$email || !$nik || !$tanggal_lahir ||
        !$jenis_kelamin || !$no_hp || !$alamat || !$password
    ) {
        $error = "Semua kolom wajib diisi.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";

    } elseif (!preg_match('/^[0-9]{16}$/', $nik)) {
        $error = "NIK harus terdiri dari 16 angka.";

    } elseif (!in_array($jenis_kelamin, ['Laki-laki', 'Perempuan'], true)) {
        $error = "Jenis kelamin tidak valid.";

    } elseif ($tanggal_lahir > date('Y-m-d') || !strtotime($tanggal_lahir)) {
        $error = "Tanggal lahir tidak valid.";

    } elseif (!preg_match('/^[0-9+\-\s]{8,20}$/', $no_hp)) {
        $error = "Nomor HP tidak valid.";

    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";

    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi password tidak sama.";

    } else {

        $cek = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE email = ?
             UNION
             SELECT user_id FROM pasien WHERE nik = ?"
        );

        mysqli_stmt_bind_param($cek, "ss", $email, $nik);
        mysqli_stmt_execute($cek);
        $hasil = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($hasil) > 0) {
            $error = "Email atau NIK sudah terdaftar.";

        } else {

            mysqli_begin_transaction($conn);

            try {

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = "pasien";

                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO users (nama, email, password, role)
                     VALUES (?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssss",
                    $nama,
                    $email,
                    $hash,
                    $role
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Gagal membuat akun.");
                }

                $user_id = mysqli_insert_id($conn);

                $stmtPasien = mysqli_prepare(
                    $conn,
                    "INSERT INTO pasien
                    (user_id, nik, tanggal_lahir, jenis_kelamin, no_hp, alamat)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $stmtPasien,
                    "isssss",
                    $user_id,
                    $nik,
                    $tanggal_lahir,
                    $jenis_kelamin,
                    $no_hp,
                    $alamat
                );

                if (!mysqli_stmt_execute($stmtPasien)) {
                    throw new Exception("Gagal menyimpan data pasien.");
                }

                mysqli_commit($conn);

                header("Location: login.php?registered=1");
                exit;

            } catch (Throwable $e) {

                mysqli_rollback($conn);
                $error = "Registrasi gagal. Silakan coba kembali.";

            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registrasi Pasien | SmartCare</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body class="auth-body">

<div class="auth-wrapper">

    <div class="auth-side">

        <a href="index.php" class="auth-logo">
            <i class="fa-solid fa-heart-pulse"></i>
            SmartCare.
        </a>

        <div class="auth-side-content">
            <div class="auth-side-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>

            <h1>Mulai Perjalanan Kesehatan Anda.</h1>

            <p>
                Buat akun SmartCare dan nikmati kemudahan
                pendaftaran pemeriksaan secara online.
            </p>

            <div class="auth-benefit">
                <i class="fa-solid fa-circle-check"></i>
                Pendaftaran lebih praktis
            </div>

            <div class="auth-benefit">
                <i class="fa-solid fa-circle-check"></i>
                Informasi jadwal dokter
            </div>

            <div class="auth-benefit">
                <i class="fa-solid fa-circle-check"></i>
                Pantau nomor antrean
            </div>
        </div>

        <small class="auth-copyright">
            © 2026 SmartCare - Politeknik LP3I Jakarta
        </small>

    </div>


    <div class="auth-main">

        <div class="auth-form-container">

            <a href="index.php" class="back-home">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
            </a>

            <div class="auth-heading">
                <span class="section-label">REGISTRASI PASIEN</span>
                <h2>Buat Akun Baru</h2>
                <p>Lengkapi informasi berikut untuk membuat akun.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama"
                    placeholder="Masukkan nama lengkap"
                    value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                    maxlength="100" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email"
                    placeholder="nama@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    maxlength="100" required>
                </div>

                <div class="form-group">
                    <label>NIK</label>
                    <input type="text" name="nik"
                    placeholder="16 digit NIK"
                    maxlength="16" inputmode="numeric"
                    value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>"
                    required>
                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir"
                        max="<?= date('Y-m-d') ?>"
                        value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>"
                        required>
                    </div>

                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" required>
                            <option value="">Pilih</option>
                            <option value="Laki-laki"
                            <?= (($_POST['jenis_kelamin'] ?? '') === 'Laki-laki') ? 'selected' : '' ?>>
                                Laki-laki
                            </option>
                            <option value="Perempuan"
                            <?= (($_POST['jenis_kelamin'] ?? '') === 'Perempuan') ? 'selected' : '' ?>>
                                Perempuan
                            </option>
                        </select>
                    </div>

                </div>

                <div class="form-group">
                    <label>Nomor HP</label>
                    <input type="tel" name="no_hp"
                    placeholder="Contoh: 081234567890"
                    value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>"
                    maxlength="20" required>
                </div>

                <div class="form-group">
                    <label>Alamat</label>
                    <textarea name="alamat"
                    placeholder="Masukkan alamat lengkap"
                    rows="3" required><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password"
                    placeholder="Minimal 8 karakter"
                    minlength="8" required>
                </div>

                <div class="form-group">
                    <label>Konfirmasi Password</label>
                    <input type="password" name="konfirmasi"
                    placeholder="Ulangi password"
                    minlength="8" required>
                </div>

                <button type="submit" class="auth-submit">
                    Buat Akun Sekarang
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>

            <p class="auth-switch">
                Sudah memiliki akun?
                <a href="login.php">Masuk di sini</a>
            </p>

        </div>

    </div>

</div>

</body>
</html>
