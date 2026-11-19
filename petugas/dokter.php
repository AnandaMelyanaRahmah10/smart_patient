
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

// PROSES FORM
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Permintaan tidak valid. Muat ulang halaman.");
    }

    $aksi = $_POST['aksi'] ?? '';

    try {
        // TAMBAH DOKTER
        if ($aksi === "tambah") {
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $poli = (int)($_POST['poliklinik_id'] ?? 0);
            $spesialisasi = trim($_POST['spesialisasi'] ?? '');
            $no_str = trim($_POST['no_str'] ?? '');
            $no_hp = trim($_POST['no_hp'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');

            if (
                $nama === '' ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($password) < 8 ||
                $poli < 1 ||
                $spesialisasi === ''
            ) {
                throw new Exception("Lengkapi data dokter dengan benar. Password minimal 8 karakter.");
            }

            $cekPoli = $conn->prepare("SELECT id FROM poliklinik WHERE id = ?");
            $cekPoli->bind_param("i", $poli);
            $cekPoli->execute();

            if ($cekPoli->get_result()->num_rows === 0) {
                throw new Exception("Poliklinik tidak ditemukan.");
            }

            $cekEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $cekEmail->bind_param("s", $email);
            $cekEmail->execute();

            if ($cekEmail->get_result()->num_rows > 0) {
                throw new Exception("Email sudah digunakan akun lain.");
            }

            $conn->begin_transaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = "dokter";

            $stmtUser = $conn->prepare(
                "INSERT INTO users (nama, email, password, role)
                 VALUES (?, ?, ?, ?)"
            );
            $stmtUser->bind_param("ssss", $nama, $email, $hash, $role);
            $stmtUser->execute();

            $userId = $conn->insert_id;

            $stmtDokter = $conn->prepare(
                "INSERT INTO dokter
                 (user_id, poliklinik_id, spesialisasi, no_str, no_hp, alamat)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmtDokter->bind_param(
                "iissss",
                $userId, $poli, $spesialisasi, $no_str, $no_hp, $alamat
            );
            $stmtDokter->execute();

            $conn->commit();
            $pesan = "Dokter berhasil ditambahkan dan akun login sudah dibuat.";
        }

        // EDIT DOKTER
        elseif ($aksi === "edit") {
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $poli = (int)($_POST['poliklinik_id'] ?? 0);
            $spesialisasi = trim($_POST['spesialisasi'] ?? '');
            $no_str = trim($_POST['no_str'] ?? '');
            $no_hp = trim($_POST['no_hp'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');

            if (
                $id < 1 || $nama === '' ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                $poli < 1 || $spesialisasi === ''
            ) {
                throw new Exception("Periksa kembali data dokter.");
            }

            if ($password !== '' && strlen($password) < 8) {
                throw new Exception("Password baru minimal 8 karakter.");
            }

            $cekPoli = $conn->prepare("SELECT id FROM poliklinik WHERE id = ?");
            $cekPoli->bind_param("i", $poli);
            $cekPoli->execute();

            if ($cekPoli->get_result()->num_rows === 0) {
                throw new Exception("Poliklinik tidak ditemukan.");
            }

            $stmt = $conn->prepare(
                "SELECT user_id FROM dokter WHERE id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $dokterLama = $stmt->get_result()->fetch_assoc();

            if (!$dokterLama) {
                throw new Exception("Data dokter tidak ditemukan.");
            }

            $userId = (int)$dokterLama['user_id'];

            $cekEmail = $conn->prepare(
                "SELECT id FROM users WHERE email = ? AND id <> ?"
            );
            $cekEmail->bind_param("si", $email, $userId);
            $cekEmail->execute();

            if ($cekEmail->get_result()->num_rows > 0) {
                throw new Exception("Email sudah digunakan akun lain.");
            }

            $conn->begin_transaction();

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $updateUser = $conn->prepare(
                    "UPDATE users SET nama = ?, email = ?, password = ? WHERE id = ?"
                );
                $updateUser->bind_param("sssi", $nama, $email, $hash, $userId);
            } else {
                $updateUser = $conn->prepare(
                    "UPDATE users SET nama = ?, email = ? WHERE id = ?"
                );
                $updateUser->bind_param("ssi", $nama, $email, $userId);
            }
            $updateUser->execute();

            $updateDokter = $conn->prepare(
                "UPDATE dokter
                 SET poliklinik_id = ?, spesialisasi = ?,
                     no_str = ?, no_hp = ?, alamat = ?
                 WHERE id = ?"
            );
            $updateDokter->bind_param(
                "issssi",
                $poli, $spesialisasi, $no_str, $no_hp, $alamat, $id
            );
            $updateDokter->execute();

            $conn->commit();
            $pesan = "Data dokter berhasil diperbarui.";
        }

        // HAPUS DOKTER
        elseif ($aksi === "hapus") {
            $id = (int)($_POST['id'] ?? 0);

            $stmt = $conn->prepare(
                "SELECT user_id FROM dokter WHERE id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();

            if (!$data) {
                throw new Exception("Data dokter tidak ditemukan.");
            }

            $userId = (int)$data['user_id'];

            // Menghapus akun akan menghapus profil dokter melalui CASCADE.
            $hapus = $conn->prepare("DELETE FROM users WHERE id = ?");
            $hapus->bind_param("i", $userId);
            $hapus->execute();

            $pesan = "Data dokter dan akun loginnya berhasil dihapus.";
        }

        $_SESSION['flash_pesan'] = $pesan;
        header("Location: dokter.php");
        exit;

    } catch (Throwable $ex) {
        if ($conn->errno || $conn->connect_errno === 0) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
                // Tidak ada transaksi aktif.
            }
        }
        $error = $ex->getMessage();
    }
}

// PESAN SETELAH REDIRECT
if (!empty($_SESSION['flash_pesan'])) {
    $pesan = $_SESSION['flash_pesan'];
    unset($_SESSION['flash_pesan']);
}

// DAFTAR POLIKLINIK UNTUK DROPDOWN
$daftarPoli = $conn->query(
    "SELECT id, nama_poli FROM poliklinik ORDER BY nama_poli ASC"
);

// DATA DOKTER YANG AKAN DIEDIT
$dataEdit = null;

if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];

    $stmtEdit = $conn->prepare(
        "SELECT d.*, u.nama, u.email
         FROM dokter d
         JOIN users u ON d.user_id = u.id
         WHERE d.id = ?"
    );
    $stmtEdit->bind_param("i", $idEdit);
    $stmtEdit->execute();
    $dataEdit = $stmtEdit->get_result()->fetch_assoc();
}

// PENCARIAN DOKTER
$keyword = trim($_GET['cari'] ?? '');

if ($keyword !== '') {
    $cari = "%".$keyword."%";

    $stmtData = $conn->prepare(
        "SELECT d.*, u.nama, u.email, p.nama_poli
         FROM dokter d
         JOIN users u ON d.user_id = u.id
         JOIN poliklinik p ON d.poliklinik_id = p.id
         WHERE u.nama LIKE ?
            OR u.email LIKE ?
            OR d.spesialisasi LIKE ?
            OR p.nama_poli LIKE ?
         ORDER BY d.id DESC"
    );
    $stmtData->bind_param("ssss", $cari, $cari, $cari, $cari);
    $stmtData->execute();
    $resultDokter = $stmtData->get_result();
} else {
    $resultDokter = $conn->query(
        "SELECT d.*, u.nama, u.email, p.nama_poli
         FROM dokter d
         JOIN users u ON d.user_id = u.id
         JOIN poliklinik p ON d.poliklinik_id = p.id
         ORDER BY d.id DESC"
    );
}

$totalDokter = $resultDokter->num_rows;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Dokter | SmartCare</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body { background:#f4f7fb; }
        .main-content { padding:30px; }
        .page-heading {
            display:flex; justify-content:space-between; align-items:center;
            gap:15px; margin-bottom:25px; flex-wrap:wrap;
        }
        .page-heading h1 { margin:0 0 6px; color:#172b4d; }
        .page-heading p { margin:0; color:#718096; }
        .panel {
            background:white; border-radius:16px; padding:24px;
            margin-bottom:22px; box-shadow:0 5px 20px rgba(20,40,80,.05);
        }
        .panel h2 { margin:0 0 20px; color:#172b4d; font-size:19px; }
        .form-grid {
            display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px;
        }
        .form-group { display:flex; flex-direction:column; gap:7px; }
        .form-group.full { grid-column:1/-1; }
        .form-group label { font-weight:600; color:#40516b; font-size:14px; }
        .form-group input,.form-group select,.form-group textarea,.search-input {
            width:100%; padding:12px 14px; border:1px solid #dce3ed;
            border-radius:9px; font:inherit; box-sizing:border-box; background:white;
        }
        .form-group textarea { min-height:85px; resize:vertical; }
        .btn {
            border:0; border-radius:9px; padding:11px 16px; cursor:pointer;
            font-weight:600; text-decoration:none; display:inline-flex;
            align-items:center; gap:8px; font-size:14px;
        }
        .btn-primary { background:#2563eb; color:white; }
        .btn-secondary { background:#edf2f7; color:#34445c; }
        .btn-edit { background:#e8f1ff; color:#2563eb; padding:8px 10px; }
        .btn-delete { background:#fff0f0; color:#dc2626; padding:8px 10px; }
        .form-actions { display:flex; gap:10px; margin-top:18px; }
        .alert { padding:13px 16px; border-radius:10px; margin-bottom:18px; }
        .alert-success { background:#e9f9ef; color:#16743a; }
        .alert-error { background:#fff0f0; color:#b42318; }
        .search-row { display:flex; gap:10px; margin-bottom:18px; }
        .search-row form {
            display:flex; gap:10px; width:100%; max-width:600px;
        }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:900px; }
        th,td {
            text-align:left; padding:14px 12px;
            border-bottom:1px solid #edf0f5; font-size:13px;
        }
        th { color:#718096; background:#f8fafc; }
        td { color:#34445c; }
        .action-cell { display:flex; gap:7px; align-items:center; }
        .action-cell form { margin:0; }
        .count-label { color:#718096; font-size:13px; margin-bottom:12px; }
        .hint { color:#718096; font-size:12px; }
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
            <h1>Data Dokter</h1>
            <p>Kelola profil, akun, dan poliklinik dokter SmartCare.</p>
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

    <div class="panel" id="form-dokter">
        <h2>
            <i class="fa-solid fa-user-doctor"></i>
            <?= $dataEdit ? "Edit Data Dokter" : "Tambah Dokter Baru" ?>
        </h2>

        <form method="POST" action="dokter.php">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="aksi" value="<?= $dataEdit ? 'edit' : 'tambah' ?>">

            <?php if ($dataEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$dataEdit['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group">
                    <label>Nama Lengkap Dokter</label>
                    <input type="text" name="nama" required
                        value="<?= e($dataEdit['nama'] ?? '') ?>"
                        placeholder="Contoh: dr. Andi Saputra">
                </div>

                <div class="form-group">
                    <label>Email untuk Login</label>
                    <input type="email" name="email" required
                        value="<?= e($dataEdit['email'] ?? '') ?>"
                        placeholder="dokter@email.com">
                </div>

                <?php if (!$dataEdit): ?>
                <div class="form-group">
                    <label>Password Akun Dokter</label>
                    <input type="password" name="password" minlength="8" required
                        placeholder="Minimal 8 karakter">
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label>Password Baru (Opsional)</label>
                    <input type="password" name="password" minlength="8"
                        placeholder="Kosongkan jika tidak diganti">
                    <span class="hint">Password lama tetap berlaku jika dikosongkan.</span>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Poliklinik</label>
                    <select name="poliklinik_id" required>
                        <option value="">Pilih poliklinik</option>
                        <?php while ($poli = $daftarPoli->fetch_assoc()): ?>
                            <option value="<?= (int)$poli['id'] ?>"
                                <?= ((int)($dataEdit['poliklinik_id'] ?? 0) === (int)$poli['id']) ? 'selected' : '' ?>>
                                <?= e($poli['nama_poli']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Spesialisasi</label>
                    <input type="text" name="spesialisasi" required
                        value="<?= e($dataEdit['spesialisasi'] ?? '') ?>"
                        placeholder="Contoh: Dokter Umum">
                </div>

                <div class="form-group">
                    <label>Nomor STR</label>
                    <input type="text" name="no_str"
                        value="<?= e($dataEdit['no_str'] ?? '') ?>"
                        placeholder="Nomor Surat Tanda Registrasi">
                </div>

                <div class="form-group">
                    <label>Nomor HP</label>
                    <input type="text" name="no_hp"
                        value="<?= e($dataEdit['no_hp'] ?? '') ?>"
                        placeholder="08xxxxxxxxxx">
                </div>

                <div class="form-group full">
                    <label>Alamat</label>
                    <textarea name="alamat"
                        placeholder="Alamat dokter"><?= e($dataEdit['alamat'] ?? '') ?></textarea>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $dataEdit ? "Simpan Perubahan" : "Simpan Dokter" ?>
                </button>

                <?php if ($dataEdit): ?>
                    <a href="dokter.php" class="btn btn-secondary">Batal</a>
                <?php else: ?>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2><i class="fa-solid fa-user-doctor"></i> Daftar Dokter</h2>

        <div class="search-row">
            <form method="GET" action="dokter.php">
                <input class="search-input" type="text" name="cari"
                    value="<?= e($keyword) ?>"
                    placeholder="Cari nama, email, spesialisasi, atau poli...">

                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i> Cari
                </button>

                <?php if ($keyword !== ''): ?>
                    <a href="dokter.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="count-label">
            Menampilkan <?= $totalDokter ?> data dokter.
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Dokter</th>
                        <th>Email</th>
                        <th>Poliklinik</th>
                        <th>Spesialisasi</th>
                        <th>Nomor HP</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($totalDokter > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = $resultDokter->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= e($row['nama']) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td><?= e($row['nama_poli']) ?></td>
                        <td><?= e($row['spesialisasi']) ?></td>
                        <td><?= e($row['no_hp']) ?></td>
                        <td>
                            <div class="action-cell">
                                <a class="btn btn-edit"
                                   href="dokter.php?edit=<?= (int)$row['id'] ?>#form-dokter"
                                   title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <form method="POST" action="dokter.php"
                                    onsubmit="return confirm('Yakin ingin menghapus dokter ini? Akun loginnya juga akan dihapus.');">
                                    <input type="hidden" name="csrf_token"
                                        value="<?= e($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id"
                                        value="<?= (int)$row['id'] ?>">

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
                            Belum ada data dokter yang ditemukan.
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
