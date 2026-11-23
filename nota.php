<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['id_user']) || !isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
$data = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT t.*, u.nama AS nama_customer, u.no_hp, k.nama_kategori, k.harga, (t.berat_barang * k.harga) AS total_harga
    FROM tb_transaksi t
    JOIN tb_user u ON t.id_user = u.id_user
    JOIN tb_barang b ON t.id_barang = b.id_barang
    JOIN tb_kategori k ON b.id_kategori = k.id_kategori
    WHERE t.id_transaksi = '$id'
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota #<?= $data['id_transaksi']; ?> - Kiloan.id</title>
    <style>
        body { font-family: monospace; background: #f5f5f4; padding: 20px; }
        .struk {
            max-width: 340px;
            margin: 0 auto;
            background: #fff;
            padding: 24px;
            border: 1px dashed #a8a29e;
        }
        .center { text-align: center; }
        .line { border-top: 1px dashed #a8a29e; margin: 12px 0; }
        table { width: 100%; font-size: 13px; }
        td { padding: 3px 0; }
        .right { text-align: right; }
        .btn-print {
            display: block;
            width: 100%;
            margin-top: 16px;
            padding: 10px;
            background: #0f766e;
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: sans-serif;
            font-weight: bold;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .struk { border: none; max-width: 100%; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

<div class="struk">
    <div class="center">
        <h2 style="margin:0;">KILOAN.ID LAUNDRY</h2>
        <small>Nota Transaksi Layanan Cucian</small>
    </div>

    <div class="line"></div>

    <table>
        <tr><td>No. Nota</td><td class="right">#<?= $data['id_transaksi']; ?></td></tr>
        <tr><td>Tanggal</td><td class="right"><?= date('d/m/Y H:i', strtotime($data['tanggal_order'])); ?></td></tr>
        <tr><td>Pelanggan</td><td class="right"><?= htmlspecialchars($data['nama_customer']); ?></td></tr>
        <tr><td>Metode</td><td class="right"><?= $data['metode_layanan']; ?></td></tr>
        <tr><td>Status</td><td class="right"><strong><?= $data['status_cucian']; ?></strong></td></tr>
    </table>

    <div class="line"></div>

    <table>
        <tr>
            <td colspan="2"><strong><?= htmlspecialchars($data['nama_kategori']); ?></strong></td>
        </tr>
        <tr>
            <td><?= $data['berat_barang']; ?> kg × Rp <?= number_format($data['harga'], 0, ',', '.'); ?></td>
            <td class="right"><strong>Rp <?= number_format($data['total_harga'], 0, ',', '.'); ?></strong></td>
        </tr>
    </table>

    <div class="line"></div>

    <div class="center">
        <small>Terima kasih telah mempercayakan cucian Anda kepada kami.</small>
    </div>

    <button class="btn-print" onclick="window.print()">Cetak Nota Sekarang</button>
</div>

</body>
</html>
