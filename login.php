
<?php
require_once "config/database.php";
require_once "config/auth.php";

if (isLogin()) {
    redirectByRole($_SESSION['role']);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = "Email dan password wajib diisi.";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, nama, email, password, role
             FROM users WHERE email = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            redirectByRole($user['role']);

        } else {
            $error = "Email atau password salah.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | SmartCare</title>

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
                <i class="fa-solid fa-hospital-user"></i>
            </div>

            <h1>Selamat Datang Kembali!</h1>

            <p>
                Masuk ke akun Anda untuk mengakses
                layanan SmartCare sesuai dengan hak akses.
            </p>

            <div class="auth-benefit">
                <i class="fa-solid fa-shield-heart"></i>
                Sistem terintegrasi
            </div>

            <div class="auth-benefit">
                <i class="fa-solid fa-user-shield"></i>
                Akses berdasarkan peran pengguna
            </div>

        </div>

        <small class="auth-copyright">
            © 2026 SmartCare - Politeknik LP3I Jakarta
        </small>

    </div>


    <div class="auth-main">

        <div class="auth-form-container login-container">

            <a href="index.php" class="back-home">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
            </a>

            <div class="auth-heading">
                <span class="section-label">LOGIN SMARTCARE</span>
                <h2>Masuk ke Akun</h2>
                <p>Masukkan email dan password Anda.</p>
            </div>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    Registrasi berhasil! Silakan login.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email"
                    placeholder="Masukkan email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password"
                    placeholder="Masukkan password"
                    required>
                </div>

                <button type="submit" class="auth-submit">
                    Masuk Sekarang
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>

            <p class="auth-switch">
                Belum memiliki akun?
                <a href="register.php">Daftar sebagai pasien</a>
            </p>

        </div>

    </div>

</div>

</body>
</html>
