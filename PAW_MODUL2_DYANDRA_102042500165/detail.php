<?php
    /** @var \mysqli $conn */
    require 'koneksi.php';
    $id = (int) ($_GET['id'] ?? 0);
    $hasil = mysqli_query($conn,
    "SELECT * FROM showroom_mobil WHERE id = $id");
    $mobil = mysqli_fetch_assoc($hasil);
    if (!$mobil) {
    exit('Mobil tidak ditemukan. Kembali ke daftar mobil.');
    }
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Detail mobil - ShowRoom EAD</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body><main>
        <p class="label">SHOWROOM EAD / DETAIL</p>
        <h1><?= $mobil['nama_mobil'] ?></h1>
        <dl>
        <dt>Kode</dt><dd><?= $mobil['kode_mobil'] ?></dd>
        <dt>Brand</dt><dd><?= $mobil['brand_mobil'] ?></dd>
        <dt>Warna</dt><dd><?= $mobil['warna_mobil'] ?></dd>
        <dt>Tipe</dt><dd><?= $mobil['tipe_mobil'] ?></dd>
        <dt>Harga</dt>
        <dd>Rp <?= number_format($mobil['harga_mobil'], 0, ',', '.') ?></dd>
        </dl>
        <a href="index.php">Daftar mobil</a>
        <a href="edit.php?id=<?= $id ?>">Edit mobil</a>
        <a href="hapus.php?id=<?= $id ?>">Hapus mobil</a>
    </main></body>
</html>