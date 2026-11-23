<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_user_login = (int) $_SESSION['id_user'];
$role_login    = $_SESSION['role'];
$nama_login    = $_SESSION['nama'];

$list_status_cucian = array('Menunggu', 'Dicuci', 'Disetrika', 'Selesai', 'Diambil');
$list_status_jemput = array('Menunggu Penjemputan', 'Dijemput Kurir', 'Selesai / Di Outlet');
$list_metode        = array('Antar Jemput', 'Datang Sendiri');

$error = "";

// 1. PROSES ORDER BARU
if (isset($_POST['simpan_order'])) {
    $id_user        = ($role_login === 'admin') ? (int) ($_POST['id_user'] ?? 0) : $id_user_login;
    $id_kategori    = (int) ($_POST['id_kategori'] ?? 0);
    $berat_barang   = (float) ($_POST['berat_barang'] ?? 0);
    $metode_layanan = $_POST['metode_layanan'] ?? '';
    $jadwal_jemput  = trim($_POST['jadwal_jemput'] ?? '');
    $alamat_jemput  = trim($_POST['alamat_jemput'] ?? '');

    if ($id_user <= 0 || $id_kategori <= 0 || $berat_barang < 0.5 || $jadwal_jemput === '' || $alamat_jemput === '' || !in_array($metode_layanan, $list_metode, true)) {
        $error = "Data order tidak valid. Periksa kembali isian form (berat minimal 0.5 kg).";
    } else {
        $status_jemput = ($metode_layanan === 'Antar Jemput') ? 'Menunggu Penjemputan' : 'Selesai / Di Outlet';

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "INSERT INTO tb_barang (id_kategori, berat_barang) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "id", $id_kategori, $berat_barang);
            mysqli_stmt_execute($stmt);
            $id_barang = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "INSERT INTO tb_transaksi
                (id_user, id_barang, berat_barang, metode_layanan, jadwal_jemput, alamat_jemput, status_jemput, status_cucian)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Menunggu')");
            mysqli_stmt_bind_param($stmt, "iidssss", $id_user, $id_barang, $berat_barang, $metode_layanan, $jadwal_jemput, $alamat_jemput, $status_jemput);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            header("Location: index.php?pesan=order_ok");
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = "Gagal menyimpan order, silakan coba lagi.";
        }
    }
}

// 2. PROSES UPDATE STATUS CUCIAN, BERAT & ANTAR JEMPUT (ADMIN)
if (isset($_POST['update_status']) && $role_login === 'admin') {
    $id_transaksi  = (int) ($_POST['id_transaksi'] ?? 0);
    $status_cucian = $_POST['status_cucian'] ?? '';
    $status_jemput = $_POST['status_jemput'] ?? '';
    $berat_baru    = (float) ($_POST['berat_barang'] ?? 0);

    if ($id_transaksi <= 0 || $berat_baru <= 0 || !in_array($status_cucian, $list_status_cucian, true) || !in_array($status_jemput, $list_status_jemput, true)) {
        $error = "Data update tidak valid.";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE tb_transaksi SET status_cucian = ?, status_jemput = ?, berat_barang = ? WHERE id_transaksi = ?");
        mysqli_stmt_bind_param($stmt, "ssdi", $status_cucian, $status_jemput, $berat_baru, $id_transaksi);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header("Location: index.php?pesan=update_ok");
        exit;
    }
}

// DATA PENDUKUNG
$users     = mysqli_query($conn, "SELECT id_user, nama FROM tb_user WHERE role = 'customer' ORDER BY nama ASC");
$kategoris = mysqli_query($conn, "SELECT * FROM tb_kategori ORDER BY nama_kategori ASC");

$stmt = mysqli_prepare($conn, "SELECT alamat FROM tb_user WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, "i", $id_user_login);
mysqli_stmt_execute($stmt);
$user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// DAFTAR TRANSAKSI
$sql_transaksi = "
    SELECT t.*, u.nama AS nama_customer, u.no_hp, k.nama_kategori, k.harga, (t.berat_barang * k.harga) AS total_harga
    FROM tb_transaksi t
    JOIN tb_user u ON t.id_user = u.id_user
    JOIN tb_barang b ON t.id_barang = b.id_barang
    JOIN tb_kategori k ON b.id_kategori = k.id_kategori
";
if ($role_login !== 'admin') {
    $stmt = mysqli_prepare($conn, $sql_transaksi . " WHERE t.id_user = ? ORDER BY t.id_transaksi DESC");
    mysqli_stmt_bind_param($stmt, "i", $id_user_login);
    mysqli_stmt_execute($stmt);
    $transaksi = mysqli_stmt_get_result($stmt);
} else {
    $transaksi = mysqli_query($conn, $sql_transaksi . " ORDER BY t.id_transaksi DESC");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Kiloan.id</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand">Kiloan.id <span class="badge badge-teal"><?php echo htmlspecialchars(strtoupper($role_login)); ?></span></a>
        <nav class="nav-links">
            <a href="index.php" class="active"><?php echo ($role_login === 'admin') ? 'Transaksi & Cucian' : 'Order & Pelacakan'; ?></a>
            <?php if ($role_login === 'admin') { ?>
                <a href="customers.php">Data Customer</a>
            <?php } ?>
            <a href="kategori.php"><?php echo ($role_login === 'admin') ? 'Tarif Layanan' : 'Daftar Tarif'; ?></a>
            <a href="logout.php" style="color: #dc2626;">Logout (<?php echo htmlspecialchars($nama_login); ?>)</a>
        </nav>
    </div>
</header>

<main class="container">
    <?php if (isset($_GET['pesan'])) { ?>
        <div class="notice">
            <?php echo ($_GET['pesan'] === 'order_ok') ? 'Order laundry berhasil dibuat!' : 'Status cucian & antar jemput berhasil diperbarui!'; ?>
        </div>
    <?php } ?>

    <?php if ($error !== "") { ?>
        <div class="notice" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php } ?>

    <div class="grid">
        <!-- KOLOM KIRI: FORM ORDER -->
        <section class="panel">
            <h2 class="panel-title"><?php echo ($role_login === 'admin') ? 'Input Transaksi Baru' : 'Buat Order Laundry'; ?></h2>
            <form method="POST">
                <?php if ($role_login === 'admin') { ?>
                <div class="form-group">
                    <label>Pilih Pelanggan</label>
                    <select name="id_user" class="form-control" required>
                        <option value="">-- Pilih Customer --</option>
                        <?php while ($u = mysqli_fetch_assoc($users)) { ?>
                            <option value="<?php echo (int) $u['id_user']; ?>"><?php echo htmlspecialchars($u['nama']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>

                <div class="form-group">
                    <label>Jenis Layanan</label>
                    <select name="id_kategori" class="form-control" required>
                        <option value="">-- Pilih Layanan --</option>
                        <?php while ($k = mysqli_fetch_assoc($kategoris)) { ?>
                            <option value="<?php echo (int) $k['id_kategori']; ?>">
                                <?php echo htmlspecialchars($k['nama_kategori']); ?> — Rp <?php echo number_format($k['harga'], 0, ',', '.'); ?>/kg
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><?php echo ($role_login === 'admin') ? 'Berat Timbangan (Kg)' : 'Perkiraan Berat (Kg)'; ?></label>
                    <input type="number" step="0.01" min="0.5" name="berat_barang" class="form-control" placeholder="Contoh: 3.00" required>
                </div>

                <div class="form-group">
                    <label>Metode Antar Jemput</label>
                    <select name="metode_layanan" class="form-control">
                        <option value="Antar Jemput">Antar Jemput Kurir</option>
                        <option value="Datang Sendiri">Datang Sendiri ke Outlet</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Jadwal Penjemputan / Antar</label>
                    <input type="text" name="jadwal_jemput" class="form-control" placeholder="Misal: Besok, Jam 10.00 WIB" required>
                </div>

                <div class="form-group">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat_jemput" class="form-control" rows="2" placeholder="Masukkan alamat lengkap..." required><?php echo htmlspecialchars($user_info['alamat'] ?? ''); ?></textarea>
                </div>

                <button type="submit" name="simpan_order" class="btn">
                    <?php echo ($role_login === 'admin') ? 'Simpan Transaksi' : 'Kirim Order Sekarang'; ?>
                </button>
            </form>
        </section>

        <!-- KOLOM KANAN: TABEL TRANSAKSI -->
        <section class="panel">
            <h2 class="panel-title">
                <?php echo ($role_login === 'admin') ? 'Manajemen Transaksi, Status Cucian & Antar Jemput' : 'Pelacakan Status & Riwayat Transaksi'; ?>
            </h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Order & Pelanggan</th>
                            <th>Layanan & Total</th>
                            <th>Antar Jemput & Jadwal</th>
                            <th>Status Cucian</th>
                            <?php if ($role_login === 'admin') { ?><th>Aksi</th><?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $kolom = ($role_login === 'admin') ? 5 : 4;
                        if (mysqli_num_rows($transaksi) === 0) {
                            echo '<tr><td colspan="' . $kolom . '" style="text-align:center;color:#78716c;">Belum ada transaksi.</td></tr>';
                        }
                        while ($row = mysqli_fetch_assoc($transaksi)) {
                        ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo (int) $row['id_transaksi']; ?> - <?php echo htmlspecialchars($row['nama_customer']); ?></strong><br>
                                    <small style="color:#78716c;"><?php echo date('d/m/Y H:i', strtotime($row['tanggal_order'])); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['nama_kategori']); ?><br>
                                    <small><?php echo htmlspecialchars($row['berat_barang']); ?> kg × Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></small><br>
                                    <strong style="color:#0f766e;">Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-blue"><?php echo htmlspecialchars($row['metode_layanan']); ?></span><br>
                                    <small><strong>Jadwal:</strong> <?php echo htmlspecialchars($row['jadwal_jemput']); ?></small><br>
                                    <small><strong>Alamat:</strong> <?php echo htmlspecialchars($row['alamat_jemput']); ?></small><br>
                                    <small><strong>Kurir:</strong> <?php echo htmlspecialchars($row['status_jemput']); ?></small>
                                </td>

                                <?php if ($role_login === 'admin') { ?>
                                    <td>
                                        <form method="POST" id="form-<?php echo (int) $row['id_transaksi']; ?>">
                                            <input type="hidden" name="id_transaksi" value="<?php echo (int) $row['id_transaksi']; ?>">
                                            <select name="status_cucian" class="select-mini">
                                                <?php foreach ($list_status_cucian as $s) { ?>
                                                    <option value="<?php echo $s; ?>" <?php echo ($row['status_cucian'] === $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                                <?php } ?>
                                            </select>
                                            <select name="status_jemput" class="select-mini" style="margin-top:4px;">
                                                <?php foreach ($list_status_jemput as $s) { ?>
                                                    <option value="<?php echo $s; ?>" <?php echo ($row['status_jemput'] === $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                                <?php } ?>
                                            </select>
                                            <input type="number" step="0.01" min="0.5" name="berat_barang" class="select-mini" style="margin-top:4px;" value="<?php echo htmlspecialchars($row['berat_barang']); ?>" required>
                                            <button type="submit" name="update_status" class="btn btn-sm btn-save">Simpan</button>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="nota.php?id=<?php echo (int) $row['id_transaksi']; ?>" target="_blank" class="btn btn-sm btn-print">Nota</a>
                                        <a href="hapus.php?id=<?php echo (int) $row['id_transaksi']; ?>" class="btn btn-sm btn-del" onclick="return confirm('Hapus transaksi #<?php echo (int) $row['id_transaksi']; ?>?');">Hapus</a>
                                    </td>
                                <?php } else { ?>
                                    <td>
                                        <span class="badge badge-amber"><?php echo htmlspecialchars($row['status_cucian']); ?></span><br>
                                        <a href="nota.php?id=<?php echo (int) $row['id_transaksi']; ?>" target="_blank" class="btn btn-sm btn-print" style="margin-top:6px;">Nota</a>
                                    </td>
                                <?php } ?>
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