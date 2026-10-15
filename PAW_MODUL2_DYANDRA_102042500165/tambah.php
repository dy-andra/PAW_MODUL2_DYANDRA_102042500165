<?php
/** @var \mysqli $conn */
require 'koneksi.php';
$kode = $nama = $brand = $warna = $tipe = $harga = '';
$pesan = '';
if (isset($_POST['simpan'])) {
$kode = mysqli_real_escape_string($conn, trim($_POST['kode_mobil']));
$nama = mysqli_real_escape_string($conn, trim($_POST['nama_mobil']));
$brand = mysqli_real_escape_string($conn, trim($_POST['brand_mobil']));
$warna = mysqli_real_escape_string($conn, trim($_POST['warna_mobil']));
$tipe = mysqli_real_escape_string($conn, trim($_POST['tipe_mobil']));
$harga = trim($_POST['harga_mobil']);
if ($kode == '' || $nama == '' || $brand == '' ||
$warna == '' || $tipe == '') {
$pesan = 'Semua kolom wajib diisi.';
} elseif (!is_numeric($harga) || $harga <= 0 || $harga > 99999999999) {
$pesan = 'Harga harus berupa angka positif, tanpa Rp dan titik.';
} else {
$cek = mysqli_query($conn,
"SELECT id FROM showroom_mobil WHERE kode_mobil = '$kode'");
if (mysqli_num_rows($cek) > 0) {
$pesan = 'Kode mobil sudah dipakai.';
} else {
mysqli_query($conn,
"INSERT INTO showroom_mobil
(kode_mobil, nama_mobil, brand_mobil, warna_mobil,
tipe_mobil, harga_mobil)
VALUES ('$kode', '$nama', '$brand', '$warna',
'$tipe', '$harga')");
header('Location: index.php?pesan=1');
exit;
}
}
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Tambah mobil - ShowRoom EAD</title>
<link rel="stylesheet" href="style.css">
</head>
<body><main>
<p class="label">SHOWROOM EAD</p>
<h1>Tambah mobil</h1>
<p><a href="index.php">Kembali ke daftar</a></p>
<?php if ($pesan != ''): ?>
<div class="kesalahan"><?= $pesan ?></div>
<?php endif; ?>
<form method="post">
<label for="kode">Kode mobil</label>
<input id="kode" name="kode_mobil" maxlength="20" value="<?= $kode ?>">
<label for="nama">Nama mobil</label>
<input id="nama" name="nama_mobil" maxlength="100" value="<?= $nama ?>">
<label for="brand">Brand</label>
<input id="brand" name="brand_mobil" maxlength="50"
value="<?= $brand ?>">
<label for="warna">Warna</label>
<input id="warna" name="warna_mobil" maxlength="30"
value="<?= $warna ?>">
<label for="tipe">Tipe (contoh: MPV, SUV, Sedan)</label>
<input id="tipe" name="tipe_mobil" maxlength="20" value="<?= $tipe ?>">
<label for="harga">Harga (angka saja, tanpa Rp dan titik)</label>
<input id="harga" name="harga_mobil" value="<?= $harga ?>">
<button name="simpan">Simpan mobil</button>
</form>
</main></body>
</html>