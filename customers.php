<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

// Halaman ini khusus admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$nama_login = $_SESSION['nama'];
$error      = "";
$edit_data  = null;

// 1. TAMBAH CUSTOMER
if (isset($_POST['tambah'])) {
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['pass'] ?? '';
    $no_hp    = trim($_POST['no_hp'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');

    if ($nama === '' || $username === '' || $pass === '') {
        $error = "Nama, username, dan password wajib diisi.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id_user FROM tb_user WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $ada = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        if ($ada) {
            $error = "Username sudah digunakan, pilih username lain.";
        } else {
            // Password disimpan apa adanya agar kompatibel dengan login.php saat ini
            $stmt = mysqli_prepare($conn, "INSERT INTO tb_user (username, pass, role, nama, no_hp, alamat) VALUES (?, ?, 'customer', ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssss", $username, $pass, $nama, $no_hp, $alamat);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: customers.php?pesan=tambah_ok");
            exit;
        }
    }
}

// 2. UPDATE CUSTOMER
if (isset($_POST['update'])) {
    $id       = (int) ($_POST['id_user'] ?? 0);
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['pass'] ?? '';
    $no_hp    = trim($_POST['no_hp'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');

    if ($id <= 0 || $nama === '' || $username === '') {
        $error = "Nama dan username wajib diisi.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id_user FROM tb_user WHERE username = ? AND id_user <> ?");
        mysqli_stmt_bind_param($stmt, "si", $username, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $ada = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        if ($ada) {
            $error = "Username sudah digunakan, pilih username lain.";
        } else {
            if ($pass !== '') {
                $stmt = mysqli_prepare($conn, "UPDATE tb_user SET nama = ?, username = ?, pass = ?, no_hp = ?, alamat = ? WHERE id_user = ? AND role = 'customer'");
                mysqli_stmt_bind_param($stmt, "sssssi", $nama, $username, $pass, $no_hp, $alamat, $id);
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE tb_user SET nama = ?, username = ?, no_hp = ?, alamat = ? WHERE id_user = ? AND role = 'customer'");
                mysqli_stmt_bind_param($stmt, "ssssi", $nama, $username, $no_hp, $alamat, $id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: customers.php?pesan=update_ok");
            exit;
        }
    }
    // Jika gagal, tampilkan kembali form edit dengan data yang diketik
    $edit_data = array('id_user' => $id, 'nama' => $nama, 'username' => $username, 'no_hp' => $no_hp, 'alamat' => $alamat);
}

// 3. HAPUS CUSTOMER (hanya jika belum punya transaksi)
if (isset($_POST['hapus'])) {
    $id = (int) ($_POST['id_user'] ?? 0);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM tb_transaksi WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $jumlah_transaksi);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($jumlah_transaksi > 0) {
        $error = "Customer tidak bisa dihapus karena masih memiliki $jumlah_transaksi transaksi.";
    } else {
        $stmt = mysqli_prepare($conn, "DELETE FROM tb_user WHERE id_user = ? AND role = 'customer'");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: customers.php?pesan=hapus_ok");
        exit;
    }
}

// 4. MODE EDIT (?edit=ID)
if ($edit_data === null && isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT id_user, nama, username, no_hp, alamat FROM tb_user WHERE id_user = ? AND role = 'customer'");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $edit_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// 5. DAFTAR CUSTOMER (+ pencarian)
$cari = trim($_GET['cari'] ?? '');
$sql  = "SELECT u.id_user, u.nama, u.username, u.no_hp, u.alamat,
                COUNT(t.id_transaksi) AS jumlah_transaksi
         FROM tb_user u
         LEFT JOIN tb_transaksi t ON t.id_user = u.id_user
         WHERE u.role = 'customer'";
if ($cari !== '') {
    $like = "%" . $cari . "%";
    $stmt = mysqli_prepare($conn, $sql . " AND (u.nama LIKE ? OR u.username LIKE ? OR u.no_hp LIKE ?) GROUP BY u.id_user ORDER BY u.nama ASC");
    mysqli_stmt_bind_param($stmt, "sss", $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $customers = mysqli_stmt_get_result($stmt);
} else {
    $customers = mysqli_query($conn, $sql . " GROUP BY u.id_user ORDER BY u.nama ASC");
}

$pesan_map = array(
    'tambah_ok' => 'Customer baru berhasil ditambahkan!',
    'update_ok' => 'Data customer berhasil diperbarui!',
    'hapus_ok'  => 'Customer berhasil dihapus!'
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Customer - Kiloan.id</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand">Kiloan.id <span class="badge badge-teal">ADMIN</span></a>
        <nav class="nav-links">
            <a href="index.php">Transaksi & Cucian</a>
            <a href="customers.php" class="active">Data Customer</a>
            <a href="kategori.php">Tarif Layanan</a>
            <a href="logout.php" style="color: #dc2626;">Logout (<?php echo htmlspecialchars($nama_login); ?>)</a>
        </nav>
    </div>
</header>

<main class="container">
    <?php if (isset($_GET['pesan']) && isset($pesan_map[$_GET['pesan']])) { ?>
        <div class="notice"><?php echo $pesan_map[$_GET['pesan']]; ?></div>
    <?php } ?>

    <?php if ($error !== "") { ?>
        <div class="notice" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php } ?>

    <div class="grid">
        <!-- KOLOM KIRI: FORM TAMBAH / EDIT -->
        <section class="panel">
            <h2 class="panel-title"><?php echo $edit_data ? 'Edit Customer' : 'Tambah Customer Baru'; ?></h2>
            <form method="POST">
                <?php if ($edit_data) { ?>
                    <input type="hidden" name="id_user" value="<?php echo (int) $edit_data['id_user']; ?>">
                <?php } ?>

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" placeholder="Nama pelanggan" required
                           value="<?php echo htmlspecialchars($edit_data['nama'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Username untuk login" required
                           value="<?php echo htmlspecialchars($edit_data['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Password<?php echo $edit_data ? ' (kosongkan jika tidak diubah)' : ''; ?></label>
                    <input type="password" name="pass" class="form-control" placeholder="••••••••" <?php echo $edit_data ? '' : 'required'; ?>>
                </div>

                <div class="form-group">
                    <label>No. HP</label>
                    <input type="text" name="no_hp" class="form-control" placeholder="Contoh: 08123456789"
                           value="<?php echo htmlspecialchars($edit_data['no_hp'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap..."><?php echo htmlspecialchars($edit_data['alamat'] ?? ''); ?></textarea>
                </div>

                <?php if ($edit_data) { ?>
                    <button type="submit" name="update" class="btn">Simpan Perubahan</button>
                    <a href="customers.php" class="btn btn-sm" style="display:inline-block;margin-top:8px;background:#e7e5e4;color:#44403c;text-decoration:none;">Batal</a>
                <?php } else { ?>
                    <button type="submit" name="tambah" class="btn">Tambah Customer</button>
                <?php } ?>
            </form>
        </section>

        <!-- KOLOM KANAN: DAFTAR CUSTOMER -->
        <section class="panel">
            <h2 class="panel-title">Daftar Customer</h2>

            <form method="GET" style="margin-bottom:14px;display:flex;gap:8px;">
                <input type="text" name="cari" class="form-control" placeholder="Cari nama, username, atau no. HP..."
                       value="<?php echo htmlspecialchars($cari); ?>">
                <button type="submit" class="btn" style="width:auto;">Cari</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Customer</th>
                            <th>Kontak & Alamat</th>
                            <th>Transaksi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($customers) === 0) {
                            echo '<tr><td colspan="5" style="text-align:center;color:#78716c;">Belum ada data customer.</td></tr>';
                        }
                        $no = 1;
                        while ($c = mysqli_fetch_assoc($customers)) {
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($c['nama']); ?></strong><br>
                                    <small style="color:#78716c;">@<?php echo htmlspecialchars($c['username']); ?></small>
                                </td>
                                <td>
                                    <small><strong>HP:</strong> <?php echo htmlspecialchars($c['no_hp'] ?: '-'); ?></small><br>
                                    <small><strong>Alamat:</strong> <?php echo htmlspecialchars($c['alamat'] ?: '-'); ?></small>
                                </td>
                                <td><span class="badge badge-blue"><?php echo (int) $c['jumlah_transaksi']; ?> order</span></td>
                                <td>
                                    <a href="customers.php?edit=<?php echo (int) $c['id_user']; ?>" class="btn btn-sm btn-print">Edit</a>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Hapus customer <?php echo htmlspecialchars(addslashes($c['nama']), ENT_QUOTES); ?>?');">
                                        <input type="hidden" name="id_user" value="<?php echo (int) $c['id_user']; ?>">
                                        <button type="submit" name="hapus" class="btn btn-sm btn-del">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

</body>
</html>
