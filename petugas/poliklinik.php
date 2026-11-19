
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
        die("Permintaan tidak valid. Silakan muat ulang halaman.");
    }

    $aksi = $_POST['aksi'] ?? '';

    try {
        // TAMBAH POLIKLINIK
        if ($aksi === "tambah") {
            $nama = trim($_POST['nama_poli'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');

            if ($nama === '') {
                throw new Exception("Nama poliklinik wajib diisi.");
            }

            $stmt = $conn->prepare(
                "INSERT INTO poliklinik (nama_poli, deskripsi) VALUES (?, ?)"
            );
            $stmt->bind_param("ss", $nama, $deskripsi);
            $stmt->execute();

            $pesan = "Poliklinik berhasil ditambahkan.";
        }

        // EDIT POLIKLINIK
        elseif ($aksi === "edit") {
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama_poli'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');

            if ($id < 1 || $nama === '') {
                throw new Exception("Data poliklinik tidak valid.");
            }

            $stmt = $conn->prepare(
                "UPDATE poliklinik SET nama_poli = ?, deskripsi = ? WHERE id = ?"
            );
            $stmt->bind_param("ssi", $nama, $deskripsi, $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                $cek = $conn->prepare("SELECT id FROM poliklinik WHERE id = ?");
                $cek->bind_param("i", $id);
                $cek->execute();

                if ($cek->get_result()->num_rows === 0) {
                    throw new Exception("Poliklinik tidak ditemukan.");
                }
            }

            $pesan = "Data poliklinik berhasil diperbarui.";
        }

        // HAPUS POLIKLINIK
        elseif ($aksi === "hapus") {
            $id = (int)($_POST['id'] ?? 0);

            $cek = $conn->prepare(
                "SELECT id FROM dokter WHERE poliklinik_id = ? LIMIT 1"
            );
            $cek->bind_param("i", $id);
            $cek->execute();

            if ($cek->get_result()->num_rows > 0) {
                throw new Exception(
                    "Poliklinik tidak bisa dihapus karena masih digunakan oleh dokter."
                );
            }

            $stmt = $conn->prepare("DELETE FROM poliklinik WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("Poliklinik tidak ditemukan.");
            }

            $pesan = "Poliklinik berhasil dihapus.";
        }

        $_SESSION['flash_pesan'] = $pesan;
        header("Location: poliklinik.php");
        exit;

    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

// PESAN SETELAH REDIRECT
if (!empty($_SESSION['flash_pesan'])) {
    $pesan = $_SESSION['flash_pesan'];
    unset($_SESSION['flash_pesan']);
}

// DATA UNTUK EDIT
$dataEdit = null;

if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT * FROM poliklinik WHERE id = ?"
    );
    $stmt->bind_param("i", $idEdit);
    $stmt->execute();
    $dataEdit = $stmt->get_result()->fetch_assoc();
}

// PENCARIAN
$keyword = trim($_GET['cari'] ?? '');

if ($keyword !== '') {
    $cari = "%".$keyword."%";

    $stmt = $conn->prepare(
        "SELECT p.*,
                (SELECT COUNT(*) FROM dokter d WHERE d.poliklinik_id = p.id) AS total_dokter
         FROM poliklinik p
         WHERE p.nama_poli LIKE ? OR p.deskripsi LIKE ?
         ORDER BY p.nama_poli ASC"
    );
    $stmt->bind_param("ss", $cari, $cari);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query(
        "SELECT p.*,
                (SELECT COUNT(*) FROM dokter d WHERE d.poliklinik_id = p.id) AS total_dokter
         FROM poliklinik p
         ORDER BY p.nama_poli ASC"
    );
}

$totalPoli = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Poliklinik | SmartCare</title>
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
        .form-group { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
        .form-group label { font-size:14px; font-weight:600; color:#40516b; }
        .form-group input,.form-group textarea,.search-input {
            width:100%; padding:12px 14px; border:1px solid #dce3ed;
            border-radius:9px; font:inherit; box-sizing:border-box;
        }
        .form-group textarea { min-height:90px; resize:vertical; }
        .btn {
            border:0; border-radius:9px; padding:11px 16px;
            cursor:pointer; font-weight:600; text-decoration:none;
            display:inline-flex; align-items:center; gap:8px; font-size:14px;
        }
        .btn-primary { background:#2563eb; color:white; }
        .btn-secondary { background:#edf2f7; color:#34445c; }
        .btn-edit { background:#e8f1ff; color:#2563eb; padding:8px 10px; }
        .btn-delete { background:#fff0f0; color:#dc2626; padding:8px 10px; }
        .alert { padding:13px 16px; border-radius:10px; margin-bottom:18px; }
        .alert-success { background:#e9f9ef; color:#16743a; }
        .alert-error { background:#fff0f0; color:#b42318; }
        .form-actions { display:flex; gap:10px; }
        .search-row { margin-bottom:18px; }
        .search-row form { display:flex; gap:10px; max-width:550px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:650px; }
        th,td { text-align:left; padding:14px 12px; border-bottom:1px solid #edf0f5; font-size:13px; }
        th { color:#718096; background:#f8fafc; }
        td { color:#34445c; }
        .action-cell { display:flex; gap:7px; align-items:center; }
        .action-cell form { margin:0; }
        .count-label { color:#718096; font-size:13px; margin-bottom:12px; }
        .poli-icon {
            display:inline-flex; width:36px; height:36px; align-items:center;
            justify-content:center; border-radius:10px; background:#eaf2ff;
            color:#2563eb; margin-right:8px;
        }
        @media(max-width:700px) {
            .main-content { padding:18px; }
            .search-row form { flex-direction:column; }
        }
    </style>
</head>

<body>
<div class="main-content">

    <div class="page-heading">
        <div>
            <h1>Data Poliklinik</h1>
            <p>Kelola daftar layanan poliklinik rumah sakit.</p>
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

    <div class="panel" id="form-poli">
        <h2>
            <i class="fa-solid fa-hospital"></i>
            <?= $dataEdit ? "Edit Poliklinik" : "Tambah Poliklinik" ?>
        </h2>

        <form method="POST" action="poliklinik.php">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="aksi" value="<?= $dataEdit ? 'edit' : 'tambah' ?>">

            <?php if ($dataEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$dataEdit['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Nama Poliklinik</label>
                <input type="text" name="nama_poli" required
                    value="<?= e($dataEdit['nama_poli'] ?? '') ?>"
                    placeholder="Contoh: Poli Jantung">
            </div>

            <div class="form-group">
                <label>Deskripsi Poliklinik</label>
                <textarea name="deskripsi"
                    placeholder="Jelaskan layanan poliklinik"><?= e($dataEdit['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $dataEdit ? "Simpan Perubahan" : "Tambah Poliklinik" ?>
                </button>

                <?php if ($dataEdit): ?>
                    <a href="poliklinik.php" class="btn btn-secondary">Batal</a>
                <?php else: ?>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2><i class="fa-solid fa-list"></i> Daftar Poliklinik</h2>

        <div class="search-row">
            <form method="GET" action="poliklinik.php">
                <input class="search-input" type="text" name="cari"
                    value="<?= e($keyword) ?>"
                    placeholder="Cari nama atau deskripsi poliklinik...">

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Cari
                </button>

                <?php if ($keyword !== ''): ?>
                    <a href="poliklinik.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="count-label">
            Menampilkan <?= $totalPoli ?> poliklinik.
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Poliklinik</th>
                        <th>Deskripsi</th>
                        <th>Jumlah Dokter</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($totalPoli > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <span class="poli-icon">
                                <i class="fa-solid fa-hospital"></i>
                            </span>
                            <?= e($row['nama_poli']) ?>
                        </td>
                        <td><?= e($row['deskripsi']) ?></td>
                        <td><?= (int)$row['total_dokter'] ?> dokter</td>
                        <td>
                            <div class="action-cell">
                                <a class="btn btn-edit"
                                    href="poliklinik.php?edit=<?= (int)$row['id'] ?>#form-poli"
                                    title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <form method="POST" action="poliklinik.php"
                                    onsubmit="return confirm('Yakin ingin menghapus poliklinik ini?');">
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
                        <td colspan="5" style="text-align:center;padding:30px;color:#718096;">
                            Belum ada data poliklinik.
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
