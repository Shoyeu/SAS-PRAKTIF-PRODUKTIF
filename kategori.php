<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$role_login = $_SESSION['role'];
$nama_login = $_SESSION['nama'];
$is_admin   = ($role_login === 'admin');

$error     = "";
$edit_data = null;

// Hanya admin yang boleh mengubah data tarif
if ($is_admin) {

    // 1. TAMBAH TARIF
    if (isset($_POST['tambah'])) {
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $harga         = (float) ($_POST['harga'] ?? 0);

        if ($nama_kategori === '' || $harga <= 0) {
            $error = "Nama layanan wajib diisi dan harga harus lebih dari 0.";
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id_kategori FROM tb_kategori WHERE nama_kategori = ?");
            mysqli_stmt_bind_param($stmt, "s", $nama_kategori);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $ada = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            if ($ada) {
                $error = "Nama layanan sudah ada.";
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO tb_kategori (nama_kategori, harga) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "sd", $nama_kategori, $harga);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: kategori.php?pesan=tambah_ok");
                exit;
            }
        }
    }

    // 2. UPDATE TARIF
    if (isset($_POST['update'])) {
        $id            = (int) ($_POST['id_kategori'] ?? 0);
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $harga         = (float) ($_POST['harga'] ?? 0);

        if ($id <= 0) {
            $error = "ID layanan tidak valid (id_kategori bernilai 0 atau kosong). Periksa AUTO_INCREMENT/primary key tabel tb_kategori.";
        } elseif ($nama_kategori === '' || $harga <= 0) {
            $error = "Nama layanan wajib diisi dan harga harus lebih dari 0.";
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id_kategori FROM tb_kategori WHERE nama_kategori = ? AND id_kategori <> ?");
            mysqli_stmt_bind_param($stmt, "si", $nama_kategori, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $ada = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            if ($ada) {
                $error = "Nama layanan sudah dipakai layanan lain.";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE tb_kategori SET nama_kategori = ?, harga = ? WHERE id_kategori = ?");
                mysqli_stmt_bind_param($stmt, "sdi", $nama_kategori, $harga, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: kategori.php?pesan=update_ok");
                exit;
            }
        }
        // Jika gagal, tampilkan kembali form edit dengan data yang diketik
        $edit_data = array('id_kategori' => $id, 'nama_kategori' => $nama_kategori, 'harga' => $harga);
    }

    // 3. HAPUS TARIF (hanya jika belum dipakai di barang/transaksi)
    if (isset($_POST['hapus'])) {
        $id = (int) ($_POST['id_kategori'] ?? 0);

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM tb_barang WHERE id_kategori = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $dipakai);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if ($dipakai > 0) {
            $error = "Layanan tidak bisa dihapus karena sudah dipakai di $dipakai order.";
        } else {
            $stmt = mysqli_prepare($conn, "DELETE FROM tb_kategori WHERE id_kategori = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: kategori.php?pesan=hapus_ok");
            exit;
        }
    }

    // 4. MODE EDIT (?edit=ID)
    if ($edit_data === null && isset($_GET['edit'])) {
        $id = (int) $_GET['edit'];
        $stmt = mysqli_prepare($conn, "SELECT id_kategori, nama_kategori, harga FROM tb_kategori WHERE id_kategori = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $edit_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
}

// DAFTAR TARIF (+ jumlah pemakaian)
$kategori = mysqli_query($conn, "
    SELECT k.id_kategori, k.nama_kategori, k.harga, COUNT(b.id_barang) AS dipakai
    FROM tb_kategori k
    LEFT JOIN tb_barang b ON b.id_kategori = k.id_kategori
    GROUP BY k.id_kategori, k.nama_kategori, k.harga
    ORDER BY k.id_kategori ASC
");

$pesan_map = array(
    'tambah_ok' => 'Tarif baru berhasil ditambahkan!',
    'update_ok' => 'Tarif berhasil diperbarui!',
    'hapus_ok'  => 'Tarif berhasil dihapus!'
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Tarif - Kiloan.id</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand">Kiloan.id <span class="badge badge-teal"><?= htmlspecialchars(strtoupper($role_login)); ?></span></a>
        <nav class="nav-links">
            <a href="index.php"><?= $is_admin ? 'Transaksi & Cucian' : 'Order & Pelacakan'; ?></a>
            <?php if ($is_admin): ?>
                <a href="customers.php">Data Customer</a>
            <?php endif; ?>
            <a href="kategori.php" class="active"><?= $is_admin ? 'Tarif Layanan' : 'Daftar Tarif'; ?></a>
            <a href="logout.php" style="color: #dc2626;">Logout (<?= htmlspecialchars($nama_login); ?>)</a>
        </nav>
    </div>
</header>

<main class="container">
    <?php if (isset($_GET['pesan']) && isset($pesan_map[$_GET['pesan']])): ?>
        <div class="notice"><?= $pesan_map[$_GET['pesan']]; ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="notice" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;">
            <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="<?= $is_admin ? 'grid' : ''; ?>">

        <?php if ($is_admin): ?>
        <!-- FORM TAMBAH / EDIT -->
        <section class="panel">
            <h2 class="panel-title"><?= $edit_data ? 'Edit Tarif' : 'Tambah Tarif Baru'; ?></h2>
            <form method="POST">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id_kategori" value="<?= (int) $edit_data['id_kategori']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Nama Layanan</label>
                    <input type="text" name="nama_kategori" class="form-control" placeholder="Misal: Cuci Kering" required
                           value="<?= htmlspecialchars($edit_data['nama_kategori'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Harga per Kg (Rp)</label>
                    <input type="number" name="harga" min="1" step="any" class="form-control" placeholder="6000" required
                           value="<?= isset($edit_data['harga']) ? htmlspecialchars((string) ((float) $edit_data['harga'])) : ''; ?>">
                </div>

                <?php if ($edit_data): ?>
                    <button type="submit" name="update" class="btn">Simpan Perubahan</button>
                    <a href="kategori.php" class="btn btn-sm" style="display:inline-block;margin-top:8px;background:#e7e5e4;color:#44403c;text-decoration:none;">Batal</a>
                <?php else: ?>
                    <button type="submit" name="tambah" class="btn">Tambah Layanan</button>
                <?php endif; ?>
            </form>
        </section>
        <?php endif; ?>

        <!-- DAFTAR TARIF -->
        <section class="panel">
            <h2 class="panel-title">Tarif Layanan Aktif</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Layanan</th>
                            <th>Tarif / Kg</th>
                            <?php if ($is_admin): ?>
                                <th>Dipakai</th>
                                <th>Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($kategori) === 0) {
                            echo '<tr><td colspan="' . ($is_admin ? 5 : 3) . '" style="text-align:center;color:#78716c;">Belum ada tarif layanan.</td></tr>';
                        }
                        $no = 1;
                        while ($k = mysqli_fetch_assoc($kategori)):
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($k['nama_kategori']); ?></td>
                                <td><strong style="color:#0f766e;">Rp <?= number_format($k['harga'], 0, ',', '.'); ?></strong></td>
                                <?php if ($is_admin): ?>
                                    <td><span class="badge badge-blue"><?= (int) $k['dipakai']; ?> order</span></td>
                                    <td>
                                        <a href="kategori.php?edit=<?= (int) $k['id_kategori']; ?>" class="btn btn-sm btn-print">Edit</a>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Hapus layanan <?= htmlspecialchars(addslashes($k['nama_kategori']), ENT_QUOTES); ?>?');">
                                            <input type="hidden" name="id_kategori" value="<?= (int) $k['id_kategori']; ?>">
                                            <button type="submit" name="hapus" class="btn btn-sm btn-del">Hapus</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

</body>
</html>