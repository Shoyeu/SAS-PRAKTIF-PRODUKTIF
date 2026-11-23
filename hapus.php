<?php
include 'koneksi.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    mysqli_query($conn, "DELETE FROM tb_transaksi WHERE id_transaksi = '$id'");
}

header("Location: index.php");
exit;
?>
