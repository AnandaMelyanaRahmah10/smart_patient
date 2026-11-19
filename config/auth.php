
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLogin() {
    return isset($_SESSION['user_id']);
}

function redirectByRole($role) {

    switch ($role) {
        case 'pasien':
            header("Location: pasien/dashboard.php");
            exit;

        case 'dokter':
            header("Location: dokter/dashboard.php");
            exit;

        case 'petugas':
            header("Location: petugas/dashboard.php");
            exit;

        default:
            header("Location: login.php");
            exit;
    }
}

function requireRole($role) {

    if (!isLogin()) {
        header("Location: ../login.php");
        exit;
    }

    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        exit("Akses ditolak. Anda tidak memiliki izin.");
    }
}

?>
