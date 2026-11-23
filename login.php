<?php
session_start();
include 'koneksi.php';

// Jika sudah login, langsung arahkan ke index.php
if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $pass     = mysqli_real_escape_string($conn, $_POST['pass']);

    $query = mysqli_query($conn, "SELECT * FROM tb_user WHERE username = '$username' AND pass = '$pass'");

    if (mysqli_num_rows($query) === 1) {
        $user = mysqli_fetch_assoc($query);

        // Simpan data user ke dalam Session
        $_SESSION['id_user']  = $user['id_user'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama']     = $user['nama'];
        $_SESSION['role']     = $user['role'];

        header("Location: index.php");
        exit;
    } else {
        $error = "Username atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Kiloan.id</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-wrapper {
            max-width: 380px;
            margin: 80px auto;
            padding: 0 20px;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .auth-header h1 {
            font-size: 22px;
            color: #0f766e;
            margin-bottom: 4px;
        }
        .auth-header p {
            font-size: 14px;
            color: #78716c;
        }
        .error-box {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .auth-footer {
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: #57534e;
        }
        .auth-footer a {
            color: #0f766e;
            font-weight: 600;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-header">
        <h1>Kiloan.id</h1>
        <p>Masuk untuk mengelola atau memantau cucian</p>
    </div>

    <div class="panel">
        <?php if ($error): ?>
            <div class="error-box"><?= $error; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['daftar']) && $_GET['daftar'] == 'sukses'): ?>
            <div class="notice">Akun berhasil dibuat. Silakan masuk.</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="pass" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" name="login" class="btn">Masuk</button>
        </form>

        <div class="auth-footer">
            Belum punya akun pelanggan? <a href="register.php">Daftar di sini</a>
        </div>
    </div>
</div>

</body>
</html>
