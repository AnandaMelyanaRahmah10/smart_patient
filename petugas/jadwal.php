
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
        // TAMBAH JADWAL
        if ($aksi === "tambah") {
            $dokterId = (int)($_POST['dokter_id'] ?? 0);
            $tanggal = $_POST['tanggal'] ?? '';
            $mulai = $_POST['jam_mulai'] ?? '';
            $selesai = $_POST['jam_selesai'] ?? '';
            $kuota = (int)($_POST['kuota'] ?? 0);
            $status = $_POST['status'] ?? 'aktif';

            $tanggalObj = DateTime::createFromFormat('Y-m-d', $tanggal);

            if (
                $dokterId < 1 ||
                !$tanggalObj ||
                $tanggalObj->format('Y-m-d') !== $tanggal ||
                !preg_match('/^\d{2}:\d{2}$/', $mulai) ||
                !preg_match('/^\d{2}:\d{2}$/', $selesai) ||
                $mulai >= $selesai ||
                $kuota < 1 ||
                !in_array($status, ['aktif', 'nonaktif'], true)
            ) {
                throw new Exception("Periksa kembali tanggal, jam praktik, kuota, dan status jadwal.");
            }

            $cekDokter = $conn->prepare(
                "SELECT id FROM dokter WHERE id = ?"
            );
            $cekDokter->bind_param("i", $dokterId);
            $cekDokter->execute();

            if ($cekDokter->get_result()->num_rows === 0) {
                throw new Exception("Dokter tidak ditemukan.");
            }

            // Cegah jadwal dengan dokter, tanggal, dan jam mulai yang sama
            $cekJadwal = $conn->prepare(
                "SELECT id FROM jadwal
                 WHERE dokter_id = ? AND tanggal = ? AND jam_mulai = ?"
            );
            $cekJadwal->bind_param("iss", $dokterId, $tanggal, $mulai);
            $cekJadwal->execute();

            if ($cekJadwal->get_result()->num_rows > 0) {
                throw new Exception("Dokter sudah memiliki jadwal dengan tanggal dan jam mulai tersebut.");
            }

            $stmt = $conn->prepare(
                "INSERT INTO jadwal
                 (dokter_id, tanggal, jam_mulai, jam_selesai, kuota, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "isssis",
                $dokterId, $tanggal, $mulai, $selesai, $kuota, $status
            );
            $stmt->execute();

            $pesan = "Jadwal dokter berhasil ditambahkan.";
        }

        // EDIT JADWAL
        elseif ($aksi === "edit") {
            $id = (int)($_POST['id'] ?? 0);
            $dokterId = (int)($_POST['dokter_id'] ?? 0);
            $tanggal = $_POST['tanggal'] ?? '';
            $mulai = $_POST['jam_mulai'] ?? '';
            $selesai = $_POST['jam_selesai'] ?? '';
            $kuota = (int)($_POST['kuota'] ?? 0);
            $status = $_POST['status'] ?? 'aktif';

            $tanggalObj = DateTime::createFromFormat('Y-m-d', $tanggal);

            if (
                $id < 1 || $dokterId < 1 ||
                !$tanggalObj ||
                $tanggalObj->format('Y-m-d') !== $tanggal ||
                !preg_match('/^\d{2}:\d{2}$/', $mulai) ||
                !preg_match('/^\d{2}:\d{2}$/', $selesai) ||
                $mulai >= $selesai ||
                $kuota < 1 ||
                !in_array($status, ['aktif', 'nonaktif'], true)
            ) {
                throw new Exception("Data jadwal tidak valid.");
            }

            $cekDokter = $conn->prepare(
                "SELECT id FROM dokter WHERE id = ?"
            );
            $cekDokter->bind_param("i", $dokterId);
            $cekDokter->execute();

            if ($cekDokter->get_result()->num_rows === 0) {
                throw new Exception("Dokter tidak ditemukan.");
            }

            $cekJadwal = $conn->prepare(
                "SELECT id FROM jadwal
                 WHERE dokter_id = ? AND tanggal = ?
                   AND jam_mulai = ? AND id <> ?"
            );
            $cekJadwal->bind_param(
                "issi", $dokterId, $tanggal, $mulai, $id
            );
            $cekJadwal->execute();

            if ($cekJadwal->get_result()->num_rows > 0) {
                throw new Exception("Jadwal tersebut sudah dimiliki dokter.");
            }

            $stmt = $conn->prepare(
                "UPDATE jadwal
                 SET dokter_id = ?, tanggal = ?, jam_mulai = ?,
                     jam_selesai = ?, kuota = ?, status = ?
                 WHERE id = ?"
            );
            $stmt->bind_param(
                "isssisi",
                $dokterId, $tanggal, $mulai, $selesai, $kuota, $status, $id
            );
            $stmt->execute();

            $pesan = "Jadwal dokter berhasil diperbarui.";
        }

        // HAPUS JADWAL
        elseif ($aksi === "hapus") {
            $id = (int)($_POST['id'] ?? 0);

            if ($id < 1) {
                throw new Exception("ID jadwal tidak valid.");
            }

            $stmt = $conn->prepare(
                "DELETE FROM jadwal WHERE id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("Jadwal tidak ditemukan.");
            }

            $pesan = "Jadwal berhasil dihapus.";
        }

        $_SESSION['flash_pesan'] = $pesan;
        header("Location: jadwal.php");
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

// DATA DOKTER UNTUK DROPDOWN
$daftarDokter = $conn->query(
    "SELECT d.id, u.nama, d.spesialisasi, p.nama_poli
     FROM dokter d
     JOIN users u ON d.user_id = u.id
     JOIN poliklinik p ON d.poliklinik_id = p.id
     ORDER BY u.nama ASC"
);

// DATA JADWAL UNTUK EDIT
$dataEdit = null;

if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];

    $stmtEdit = $conn->prepare(
        "SELECT * FROM jadwal WHERE id = ?"
    );
    $stmtEdit->bind_param("i", $idEdit);
    $stmtEdit->execute();
    $dataEdit = $stmtEdit->get_result()->fetch_assoc();
}

// FILTER DAN PENCARIAN JADWAL
$keyword = trim($_GET['cari'] ?? '');
$filterTanggal = $_GET['tanggal'] ?? '';

$sql = "
    SELECT j.*, u.nama AS nama_dokter,
           d.spesialisasi, p.nama_poli
    FROM jadwal j
    JOIN dokter d ON j.dokter_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN poliklinik p ON d.poliklinik_id = p.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($keyword !== '') {
    $sql .= " AND (u.nama LIKE ? OR d.spesialisasi LIKE ? OR p.nama_poli LIKE ?)";
    $cari = "%".$keyword."%";
    $params[] = $cari;
    $params[] = $cari;
    $params[] = $cari;
    $types .= "sss";
}

if ($filterTanggal !== '') {
    $tanggalFilterObj = DateTime::createFromFormat('Y-m-d', $filterTanggal);

    if ($tanggalFilterObj && $tanggalFilterObj->format('Y-m-d') === $filterTanggal) {
        $sql .= " AND j.tanggal = ?";
        $params[] = $filterTanggal;
        $types .= "s";
    } else {
        $filterTanggal = "";
    }
}

$sql .= " ORDER BY j.tanggal DESC, j.jam_mulai ASC";

$stmtData = $conn->prepare($sql);

if (!empty($params)) {
    $stmtData->bind_param($types, ...$params);
}

$stmtData->execute();
$resultJadwal = $stmtData->get_result();
$totalJadwal = $resultJadwal->num_rows;

// HITUNG TOTAL DOKTER
$totalDokter = $conn->query("SELECT COUNT(*) AS total FROM dokter")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Dokter | SmartCare</title>
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
        .form-group input,.form-group select,.search-input {
            width:100%; padding:12px 14px; border:1px solid #dce3ed;
            border-radius:9px; font:inherit; box-sizing:border-box; background:white;
        }
        .btn {
            border:0; border-radius:9px; padding:11px 16px;
            cursor:pointer; font-weight:600; text-decoration:none;
            display:inline-flex; align-items:center; gap:8px; font-size:14px;
        }
        .btn-primary { background:#2563eb; color:white; }
        .btn-secondary { background:#edf2f7; color:#34445c; }
        .btn-edit { background:#e8f1ff; color:#2563eb; padding:8px 10px; }
        .btn-delete { background:#fff0f0; color:#dc2626; padding:8px 10px; }
        .form-actions { display:flex; gap:10px; margin-top:18px; }
        .alert { padding:13px 16px; border-radius:10px; margin-bottom:18px; }
        .alert-success { background:#e9f9ef; color:#16743a; }
        .alert-error { background:#fff0f0; color:#b42318; }
        .search-row { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
        .search-row form { display:flex; gap:10px; width:100%; flex-wrap:wrap; }
        .search-input { max-width:320px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:950px; }
        th,td { text-align:left; padding:14px 12px; border-bottom:1px solid #edf0f5; font-size:13px; }
        th { color:#718096; background:#f8fafc; }
        td { color:#34445c; }
        .action-cell { display:flex; gap:7px; align-items:center; }
        .action-cell form { margin:0; }
        .count-label { color:#718096; font-size:13px; margin-bottom:12px; }
        .status {
            display:inline-block; padding:5px 10px; border-radius:20px;
            font-size:12px; font-weight:700;
        }
        .status-aktif { background:#e8f8ee; color:#16803d; }
        .status-nonaktif { background:#fff0f0; color:#c24141; }
        .summary {
            display:flex; align-items:center; gap:15px;
            background:#edf4ff; border-radius:12px; padding:16px; margin-bottom:20px;
        }
        .summary i { font-size:24px; color:#2563eb; }
        .summary strong { display:block; font-size:20px; color:#172b4d; }
        .summary span { font-size:13px; color:#718096; }
        @media(max-width:700px) {
            .main-content { padding:18px; }
            .form-grid { grid-template-columns:1fr; }
            .form-group.full { grid-column:auto; }
            .search-row form { flex-direction:column; }
            .search-input { max-width:none; }
        }
    </style>
</head>

<body>
<div class="main-content">

    <div class="page-heading">
        <div>
            <h1>Jadwal Dokter</h1>
            <p>Atur tanggal praktik, jam pelayanan, dan kuota pasien.</p>
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

    <div class="summary">
        <i class="fa-solid fa-user-doctor"></i>
        <div>
            <strong><?= (int)$totalDokter ?></strong>
            <span>Dokter terdaftar di SmartCare</span>
        </div>
    </div>

    <div class="panel" id="form-jadwal">
        <h2>
            <i class="fa-solid fa-calendar-plus"></i>
            <?= $dataEdit ? "Edit Jadwal Dokter" : "Tambah Jadwal Dokter" ?>
        </h2>

        <?php if ($totalDokter < 1): ?>
            <div class="alert alert-error">
                Belum ada dokter. Tambahkan dokter terlebih dahulu melalui menu Data Dokter.
            </div>
        <?php else: ?>

        <form method="POST" action="jadwal.php">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="aksi" value="<?= $dataEdit ? 'edit' : 'tambah' ?>">

            <?php if ($dataEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$dataEdit['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group full">
                    <label>Pilih Dokter</label>
                    <select name="dokter_id" required>
                        <option value="">Pilih dokter</option>
                        <?php while ($dokter = $daftarDokter->fetch_assoc()): ?>
                            <option value="<?= (int)$dokter['id'] ?>"
                                <?= ((int)($dataEdit['dokter_id'] ?? 0) === (int)$dokter['id']) ? 'selected' : '' ?>>
                                <?= e($dokter['nama']) ?> — <?= e($dokter['nama_poli']) ?>
                                (<?= e($dokter['spesialisasi']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tanggal Praktik</label>
                    <input type="date" name="tanggal" min="<?= date('Y-m-d') ?>" required
                        value="<?= e($dataEdit['tanggal'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Kuota Pasien</label>
                    <input type="number" name="kuota" min="1" max="500" required
                        value="<?= e($dataEdit['kuota'] ?? 10) ?>">
                </div>

                <div class="form-group">
                    <label>Jam Mulai</label>
                    <input type="time" name="jam_mulai" required
                        value="<?= e(isset($dataEdit['jam_mulai']) ? substr($dataEdit['jam_mulai'], 0, 5) : '') ?>">
                </div>

                <div class="form-group">
                    <label>Jam Selesai</label>
                    <input type="time" name="jam_selesai" required
                        value="<?= e(isset($dataEdit['jam_selesai']) ? substr($dataEdit['jam_selesai'], 0, 5) : '') ?>">
                </div>

                <div class="form-group">
                    <label>Status Jadwal</label>
                    <select name="status" required>
                        <option value="aktif" <?= (($dataEdit['status'] ?? 'aktif') === 'aktif') ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= (($dataEdit['status'] ?? '') === 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $dataEdit ? "Simpan Perubahan" : "Simpan Jadwal" ?>
                </button>

                <?php if ($dataEdit): ?>
                    <a href="jadwal.php" class="btn btn-secondary">Batal</a>
                <?php else: ?>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                <?php endif; ?>
            </div>
        </form>

        <?php endif; ?>
    </div>

    <div class="panel">
        <h2><i class="fa-solid fa-calendar-days"></i> Daftar Jadwal Praktik</h2>

        <div class="search-row">
            <form method="GET" action="jadwal.php">
                <input class="search-input" type="text" name="cari"
                    value="<?= e($keyword) ?>"
                    placeholder="Cari nama dokter, poli, spesialisasi...">

                <input class="search-input" type="date" name="tanggal"
                    value="<?= e($filterTanggal) ?>">

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Filter
                </button>

                <?php if ($keyword !== '' || $filterTanggal !== ''): ?>
                    <a href="jadwal.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="count-label">
            Menampilkan <?= $totalJadwal ?> jadwal praktik.
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Dokter</th>
                        <th>Poliklinik</th>
                        <th>Tanggal</th>
                        <th>Jam Praktik</th>
                        <th>Kuota</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($totalJadwal > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = $resultJadwal->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= e($row['nama_dokter']) ?></td>
                        <td><?= e($row['nama_poli']) ?></td>
                        <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                        <td>
                            <?= e(substr($row['jam_mulai'], 0, 5)) ?>
                            -
                            <?= e(substr($row['jam_selesai'], 0, 5)) ?>
                        </td>
                        <td><?= (int)$row['kuota'] ?> pasien</td>
                        <td>
                            <span class="status status-<?= e($row['status']) ?>">
                                <?= e(ucfirst($row['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-cell">
                                <a class="btn btn-edit"
                                    href="jadwal.php?edit=<?= (int)$row['id'] ?>#form-jadwal"
                                    title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <form method="POST" action="jadwal.php"
                                    onsubmit="return confirm('Yakin ingin menghapus jadwal ini?');">
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
                        <td colspan="8" style="text-align:center;padding:30px;color:#718096;">
                            Belum ada jadwal praktik yang ditemukan.
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
