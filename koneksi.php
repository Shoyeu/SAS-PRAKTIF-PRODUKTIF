<?php
// Koneksi ke database db_laundry
$conn = mysqli_connect("localhost", "root", "", "db_laundry");

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>
