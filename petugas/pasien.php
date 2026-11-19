
<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireRole("petugas");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = "";
$error = "";

// PROSES TAMBAH, EDIT, DAN HAPUS
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Permintaan tidak valid. Silakan muat ulang halaman.");
    }

    $aksi = $_POST['aksi'] ?? '';

    try {
        // TAMBAH PASIEN
        if ($aksi === "tambah") {
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $nik = trim($_POST['nik'] ?? '');
            $tanggal = $_POST['tanggal_lahir'] ?? '';
            $gender = $_POST['jenis_kelamin'] ?? '';
            $no_hp = trim($_POST['no_hp'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');

            if (
                $nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($password) < 8 || !preg_match('/^[0-9]{16}$/', $nik) ||
                !in_array($gender, ['Laki-laki', 'Perempuan'], true) ||
                $no_hp === '' || $alamat === '' || $tanggal === ''
            ) {
                throw new Exception("Periksa kembali data. NIK harus 16 digit dan password minimal 8 karakter.");
            }

            $tanggalObj = DateTime::createFromFormat('Y-m-d', $tanggal);
            if (!$tanggalObj || $tanggalObj->format('Y-m-d') !== $tanggal || $tanggal > date('Y-m-d')) {
                throw new Exception("Tanggal lahir tidak valid.");
            }

            $conn->begin_transaction();

            // Pastikan email belum digunakan
            $cekEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $cekEmail->bind_param("s", $email);
            $cekEmail->execute();
            if ($cekEmail->get_result()->num_rows > 0) {
                throw new Exception("Email sudah terdaftar.");
            }

            // Pastikan NIK belum digunakan
            $cekNik = $conn->prepare("SELECT id FROM pasien WHERE nik = ?");
            $cekNik->bind_param("s", $nik);
            $cekNik->execute();
            if ($cekNik->get_result()->num_rows > 0) {
                throw new Exception("NIK sudah terdaftar.");
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = "pasien";

            $stmtUser = $conn->prepare(
                "INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)"
            );
            $stmtUser->bind_param("ssss", $nama, $email, $hash, $role);
            $stmtUser->execute();

            $userId = $conn->insert_id;

            $stmtPasien = $conn->prepare(
                "INSERT INTO pasien (user_id, nik, tanggal_lahir, jenis_kelamin, no_hp, alamat)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmtPasien->bind_param(
                "isssss",
                $userId, $nik, $tanggal, $gender, $no_hp, $alamat
            );
            $stmtPasien->execute();

            $conn->commit();
            $pesan = "Pasien berhasil ditambahkan. Akun pasien sudah dibuat.";
        }

        // EDIT PASIEN
        elseif ($aksi === "edit") {
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $nik = trim($_POST['nik'] ?? '');
            $tanggal = $_POST['tanggal_lahir'] ?? '';
            $gender = $_POST['jenis_kelamin'] ?? '';
            $no_hp = trim($_POST['no_hp'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');

            if (
                $id < 1 || $nama === '' ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                !preg_match('/^[0-9]{16}$/', $nik) ||
                !in_array($gender, ['Laki-laki', 'Perempuan'], true) ||
                $no_hp === '' || $alamat === '' || $tanggal === ''
            ) {
                throw new Exception("Data pasien belum lengkap atau tidak valid.");
            }

            $tanggalObj = DateTime::createFromFormat('Y-m-d', $tanggal);
            if (!$tanggalObj || $tanggalObj->format('Y-m-d') !== $tanggal || $tanggal > date('Y-m-d')) {
                throw new Exception("Tanggal lahir tidak valid.");
            }

            $cek = $conn->prepare(
                "SELECT p.user_id
                 FROM pasien p
                 WHERE p.id = ?"
            );
            $cek->bind_param("i", $id);
            $cek->execute();
            $dataCek = $cek->get_result()->fetch_assoc();

            if (!$dataCek) {
                throw new Exception("Data pasien tidak ditemukan.");
            }

            $userId = (int)$dataCek['user_id'];

            $cekEmail = $conn->prepare(
                "SELECT id FROM users WHERE email = ? AND id <> ?"
            );
            $cekEmail->bind_param("si", $email, $userId);
            $cekEmail->execute();
            if ($cekEmail->get_result()->num_rows > 0) {
                throw new Exception("Email sudah digunakan akun lain.");
            }

            $cekNik = $conn->prepare(
                "SELECT id FROM pasien WHERE nik = ? AND id <> ?"
            );
            $cekNik->bind_param("si", $nik, $id);
            $cekNik->execute();
            if ($cekNik->get_result()->num_rows > 0) {
                throw new Exception("NIK sudah digunakan pasien lain.");
            }

            $conn->begin_transaction();

            $updateUser = $conn->prepare(
                "UPDATE users SET nama = ?, email = ? WHERE id = ?"
            );
            $updateUser->bind_param("ssi", $nama, $email, $userId);
            $updateUser->execute();

            $updatePasien = $conn->prepare(
                "UPDATE pasien
                 SET nik = ?, tanggal_lahir = ?, jenis_kelamin = ?, no_hp = ?, alamat = ?
                 WHERE id = ?"
            );
            $updatePasien->bind_param(
                "sssssi",
                $nik, $tanggal, $gender, $no_hp, $alamat, $id
            );
            $updatePasien->execute();

            $conn->commit();
            $pesan = "Data pasien berhasil diperbarui.";
        }

        // HAPUS PASIEN
        elseif ($aksi === "hapus") {
            $id = (int)($_POST['id'] ?? 0);

            $stmt = $conn->prepare(
                "SELECT user_id FROM pasien WHERE id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();

            if (!$data) {
                throw new Exception("Data pasien tidak ditemukan.");
            }

            // Relasi ON DELETE CASCADE akan menghapus profil pasien.
            // Akun login pasien juga ikut dihapus.
            $userId = (int)$data['user_id'];

            $hapus = $conn->prepare("DELETE FROM users WHERE id = ?");
            $hapus->bind_param("i", $userId);
            $hapus->execute();

            $pesan = "Data pasien dan akun loginnya berhasil dihapus.";
        }

        // Redirect agar form tidak terkirim ulang saat halaman di-refresh
        $_SESSION['flash_pesan'] = $pesan;
        header("Location: pasien.php");
        exit;

    } catch (Throwable $ex) {
        $conn->rollback();
        $error = $ex->getMessage();
    }
}

// PESAN SETELAH REDIRECT
if (!empty($_SESSION['flash_pesan'])) {
    $pesan = $_SESSION['flash_pesan'];
    unset($_SESSION['flash_pesan']);
}

// DATA UNTUK FORM EDIT
$dataEdit = null;
if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];

    $stmtEdit = $conn->prepare(
        "SELECT p.*, u.nama, u.email
         FROM pasien p
         JOIN users u ON p.user_id = u.id
         WHERE p.id = ?"
    );
    $stmtEdit->bind_param("i", $idEdit);
    $stmtEdit->execute();
    $dataEdit = $stmtEdit->get_result()->fetch_assoc();
}

// PENCARIAN PASIEN
$keyword = trim($_GET['cari'] ?? '');

if ($keyword !== '') {
    $cari = "%".$keyword."%";
    $stmtData = $conn->prepare(
        "SELECT p.*, u.nama, u.email
         FROM pasien p
         JOIN users u ON p.user_id = u.id
         WHERE u.nama LIKE ? OR p.nik LIKE ? OR u.email LIKE ?
         ORDER BY p.id DESC"
    );
    $stmtData->bind_param("sss", $cari, $cari, $cari);
    $stmtData->execute();
    $resultPasien = $stmtData->get_result();
} else {
    $resultPasien = $conn->query(
        "SELECT p.*, u.nama, u.email
         FROM pasien p
         JOIN users u ON p.user_id = u.id
         ORDER BY p.id DESC"
    );
}

$totalPasien = $resultPasien->num_rows;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pasien | SmartCare</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background:#f4f7fb; }
        .main-content { padding:30px; }
        .page-heading { display:flex; justify-content:space-between; align-items:center; gap:15px; margin-bottom:25px; flex-wrap:wrap; }
        .page-heading h1 { margin:0 0 6px; color:#172b4d; }
        .page-heading p { margin:0; color:#718096; }
        .panel { background:white; border-radius:16px; padding:24px; margin-bottom:22px; box-shadow:0 5px 20px rgba(20,40,80,.05); }
        .panel h2 { margin:0 0 20px; color:#172b4d; font-size:19px; }
        .form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .form-group { display:flex; flex-direction:column; gap:7px; }
        .form-group.full { grid-column:1/-1; }
        .form-group label { font-weight:600; color:#40516b; font-size:14px; }
        .form-group input,.form-group select,.form-group textarea,.search-input {
            width:100%; padding:12px 14px; border:1px solid #dce3ed; border-radius:9px;
            font:inherit; box-sizing:border-box; background:white;
        }
        .form-group textarea { min-height:85px; resize:vertical; }
        .btn { border:0; border-radius:9px; padding:11px 16px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-size:14px; }
        .btn-primary { background:#2563eb; color:white; }
        .btn-secondary { background:#edf2f7; color:#34445c; }
        .btn-edit { background:#e8f1ff; color:#2563eb; padding:8px 10px; }
        .btn-delete { background:#fff0f0; color:#dc2626; padding:8px 10px; }
        .form-actions { display:flex; gap:10px; margin-top:18px; }
        .alert { padding:13px 16px; border-radius:10px; margin-bottom:18px; }
        .alert-success { background:#e9f9ef; color:#16743a; }
        .alert-error { background:#fff0f0; color:#b42318; }
        .search-row { display:flex; gap:10px; margin-bottom:18px; }
        .search-row form { display:flex; gap:10px; width:100%; max-width:520px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:850px; }
        th,td { text-align:left; padding:14px 12px; border-bottom:1px solid #edf0f5; font-size:13px; }
        th { color:#718096; background:#f8fafc; }
        td { color:#34445c; }
        .action-cell { display:flex; gap:7px; align-items:center; }
        .action-cell form { margin:0; }
        .count-label { color:#718096; font-size:13px; margin-bottom:12px; }
        @media(max-width:700px) {
            .main-content { padding:18px; }
            .form-grid { grid-template-columns:1fr; }
            .form-group.full { grid-column:auto; }
            .search-row form { flex-direction:column; }
        }
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-heading">
        <div>
            <h1>Data Pasien</h1>
            <p>Kelola informasi dan akun pasien SmartCare.</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if ($pesan): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i> <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="panel" id="form-pasien">
        <h2>
            <i class="fa-solid fa-user-plus"></i>
            <?= $dataEdit ? "Edit Data Pasien" : "Tambah Pasien Baru" ?>
        </h2>

        <form method="POST" action="pasien.php">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="aksi" value="<?= $dataEdit ? 'edit' : 'tambah' ?>">

            <?php if ($dataEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$dataEdit['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" required value="<?= e($dataEdit['nama'] ?? '') ?>" placeholder="Masukkan nama pasien">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?= e($dataEdit['email'] ?? '') ?>" placeholder="nama@email.com">
                </div>

                <?php if (!$dataEdit): ?>
                <div class="form-group">
                    <label>Password Akun Pasien</label>
                    <input type="password" name="password" minlength="8" required placeholder="Minimal 8 karakter">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>NIK (16 Digit)</label>
                    <input type="text" name="nik" maxlength="16" minlength="16" pattern="[0-9]{16}" required value="<?= e($dataEdit['nik'] ?? '') ?>" placeholder="Masukkan 16 digit NIK">
                </div>

                <div class="form-group">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" max="<?= date('Y-m-d') ?>" required value="<?= e($dataEdit['tanggal_lahir'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <select name="jenis_kelamin" required>
                        <option value="">Pilih jenis kelamin</option>
                        <option value="Laki-laki" <?= (($dataEdit['jenis_kelamin'] ?? '') === 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="Perempuan" <?= (($dataEdit['jenis_kelamin'] ?? '') === 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nomor HP</label>
                    <input type="text" name="no_hp" required value="<?= e($dataEdit['no_hp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                </div>

                <div class="form-group full">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat" required placeholder="Masukkan alamat pasien"><?= e($dataEdit['alamat'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $dataEdit ? "Simpan Perubahan" : "Simpan Pasien" ?>
                </button>

                <?php if ($dataEdit): ?>
                    <a href="pasien.php" class="btn btn-secondary">Batal</a>
                <?php else: ?>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2><i class="fa-solid fa-users"></i> Daftar Pasien</h2>

        <div class="search-row">
            <form method="GET" action="pasien.php">
                <input class="search-input" type="text" name="cari" value="<?= e($keyword) ?>" placeholder="Cari nama, NIK, atau email...">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i> Cari
                </button>
                <?php if ($keyword !== ''): ?>
                    <a href="pasien.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="count-label">
            Menampilkan <?= $totalPasien ?> data pasien.
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Pasien</th>
                        <th>NIK</th>
                        <th>Email</th>
                        <th>No. HP</th>
                        <th>Jenis Kelamin</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($totalPasien > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = $resultPasien->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= e($row['nama']) ?></td>
                        <td><?= e($row['nik']) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td><?= e($row['no_hp']) ?></td>
                        <td><?= e($row['jenis_kelamin']) ?></td>
                        <td>
                            <div class="action-cell">
                                <a class="btn btn-edit" href="pasien.php?edit=<?= (int)$row['id'] ?>#form-pasien" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <form method="POST" action="pasien.php" onsubmit="return confirm('Yakin ingin menghapus pasien ini? Akun login pasien juga akan dihapus.');">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" class="btn btn-delete" title="Hapus">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:30px;color:#718096;">
                            Belum ada data pasien yang ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
