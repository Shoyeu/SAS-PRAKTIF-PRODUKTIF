<?php
session_start();
include 'koneksi.php';

// Jika sudah login, tidak perlu daftar lagi
if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit;
}

$error  = "";
$nama   = "";
$user   = "";
$no_hp  = "";
$alamat = "";

if (isset($_POST['daftar'])) {
    $nama     = trim($_POST['nama'] ?? '');
    $user     = trim($_POST['username'] ?? '');
    $pass     = $_POST['pass'] ?? '';
    $no_hp    = trim($_POST['no_hp'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');

    if ($nama === '' || $user === '' || $pass === '' || $no_hp === '') {
        $error = "Nama, username, password, dan no. HP wajib diisi.";
    } elseif (!preg_match('/^[0-9+\- ]{8,20}$/', $no_hp)) {
        $error = "No. HP tidak valid. Gunakan angka saja, minimal 8 digit.";
    } else {
        try {
            // Cek apakah username sudah dipakai
            $stmt = mysqli_prepare($conn, "SELECT id_user FROM tb_user WHERE username = ?");
            mysqli_stmt_bind_param($stmt, "s", $user);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $sudah_ada = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            if ($sudah_ada) {
                $error = "Username sudah digunakan, pilih username lain.";
            } else {
                // Password disimpan apa adanya agar kompatibel dengan login.php saat ini
                $stmt = mysqli_prepare($conn, "INSERT INTO tb_user (username, pass, role, nama, no_hp, alamat) VALUES (?, ?, 'customer', ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sssss", $user, $pass, $nama, $no_hp, $alamat);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                header("Location: login.php?daftar=sukses");
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            // Pesan teknis hanya ke log server, bukan ke pengunjung
            error_log("Register gagal: " . $e->getMessage());
            $error = "Gagal mendaftar, silakan coba lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelanggan - Kiloan.id</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-wrapper { max-width: 380px; margin: 60px auto; padding: 0 20px; }
        .auth-header { text-align: center; margin-bottom: 24px; }
        .auth-header h1 { font-size: 22px; color: #0f766e; margin-bottom: 4px; }
        .auth-header p { font-size: 14px; color: #78716c; }
        .error-box { background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
        .auth-footer { text-align: center; margin-top: 18px; font-size: 13px; color: #57534e; }
        .auth-footer a { color: #0f766e; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-header">
        <h1>Buat Akun Baru</h1>
        <p>Daftar sebagai pelanggan laundry</p>
    </div>

    <div class="panel">
        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" class="form-control" placeholder="Misal: Siti Aminah" required
                       value="<?= htmlspecialchars($nama); ?>">
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" placeholder="Buat username" required
                       value="<?= htmlspecialchars($user); ?>">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="pass" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-group">
                <label>No. HP</label>
                <input type="tel" name="no_hp" class="form-control" placeholder="Contoh: 08123456789" required
                       value="<?= htmlspecialchars($no_hp); ?>">
            </div>

            <div class="form-group">
                <label>Alamat (opsional)</label>
                <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat untuk penjemputan cucian..."><?= htmlspecialchars($alamat); ?></textarea>
            </div>

            <button type="submit" name="daftar" class="btn">Daftar Sekarang</button>
        </form>

        <div class="auth-footer">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>
</div>

</body>
</html>