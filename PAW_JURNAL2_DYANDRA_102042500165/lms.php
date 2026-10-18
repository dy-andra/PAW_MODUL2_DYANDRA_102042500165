<?php
session_start();
require 'koneksi.php';

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token'];
$jenisValid = ['mata_kuliah', 'materi', 'tugas'];
$jenis = $_GET['jenis'] ?? 'mata_kuliah';
if (!in_array($jenis, $jenisValid, true)) $jenis = 'mata_kuliah';
$aksi = $_GET['aksi'] ?? 'daftar';
$pesan = '';
$error = '';

function e($nilai) { 
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8'); 
    }
function panjang($teks) { 
    return function_exists('mb_strlen') ? mb_strlen($teks) : strlen($teks); 
    }
function ambilMataKuliah($conn) {
    $hasil = $conn->query('SELECT id, kode, nama FROM mata_kuliah ORDER BY nama');
    return $hasil->fetch_all(MYSQLI_ASSOC);
}
function ambilSatu($conn, $jenis, $id) {
    if ($jenis === 'mata_kuliah') {
        $sql = 'SELECT * FROM mata_kuliah 
        WHERE id = ?';
    } elseif ($jenis === 'materi') {
        $sql = 'SELECT materi.*, 
        mata_kuliah.nama AS nama_mk 
        FROM materi JOIN mata_kuliah 
        ON materi.mata_kuliah_id = mata_kuliah.id 
        WHERE materi.id = ?';
    } else {
        $sql = 'SELECT tugas.*, 
        mata_kuliah.nama AS nama_mk 
        FROM tugas JOIN mata_kuliah 
        ON tugas.mata_kuliah_id = mata_kuliah.id 
        WHERE tugas.id = ?';
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
function urlJenis($jenis, $tambahan = '') {
    return 'lms.php?jenis=' . urlencode($jenis) . $tambahan;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $jenisPost = $_POST['jenis'] ?? '';
    $idPost = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $tokenPost = $_POST['token'] ?? '';
    if (!in_array($jenisPost, $jenisValid, true) || !$idPost || !hash_equals($token, $tokenPost)) {
        $error = 'Permintaan hapus tidak valid.';
    } else {
        try {
            $stmt = $conn->prepare('DELETE FROM ' . $jenisPost . ' WHERE id = ?');
            $stmt->bind_param('i', $idPost);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                header('Location: ' . urlJenis($jenisPost) . '&pesan=Data+berhasil+dihapus');
                exit;
            }
            $error = 'Data tidak ditemukan atau gagal dihapus.';
        } catch (mysqli_sql_exception $ex) {
            if ($jenisPost === 'mata_kuliah' && $ex->getCode() === 1451) {
                $error = 'Mata kuliah masih dipakai materi atau tugas. Hapus data terkait lebih dulu.';
            } else {
                $error = 'Data gagal dihapus. Periksa hubungan data dan coba lagi.';
            }
        }
    }
}

if (isset($_GET['pesan'])) $pesan = (string)$_GET['pesan'];
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$barisEdit = null;
if (in_array($aksi, ['edit','detail','konfirmasi'], true)) {
    if (!$id) {
        $error = 'ID tidak valid.';
        $aksi = 'daftar';
    } else {
        $barisEdit = ambilSatu($conn, $jenis, $id);
        if (!$barisEdit) {
            $error = 'Data tidak ditemukan.';
            $aksi = 'daftar';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['aksi'] ?? '', ['simpan_baru','simpan_edit'], true)) {
    $aksiPost = $_POST['aksi'];
    $jenisPost = $_POST['jenis'] ?? '';
    $idPost = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!in_array($jenisPost, $jenisValid, true)) {
        $error = 'Jenis data tidak valid.';
    } elseif (!hash_equals($token, $_POST['token'] ?? '')) {
        $error = 'Token tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $jenis = $jenisPost;
        $aksi = $aksiPost === 'simpan_edit' ? 'edit' : 'tambah';
        $mkId = trim($_POST['mata_kuliah_id'] ?? '');
        $kode = strtoupper(trim($_POST['kode'] ?? ''));
        $nama = trim($_POST['nama'] ?? '');
        $dosen = trim($_POST['dosen'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $judul = trim($_POST['judul'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        $tautan = trim($_POST['tautan'] ?? '');
        $instruksi = trim($_POST['instruksi'] ?? '');
        $tenggat = trim($_POST['tenggat'] ?? '');
        $status = $_POST['status'] ?? '';
        $data = $_POST;

        if ($jenis === 'mata_kuliah') {
            if (!preg_match('/^[A-Z0-9-]{3,20}$/', $kode)) 
                $error = 'Kode harus 3-20 karakter: huruf kapital, angka, atau tanda -.';
            elseif (panjang($nama) < 3 || panjang($nama) > 100) 
                $error = 'Nama harus 3-100 karakter.';
            elseif (panjang($dosen) < 3 || panjang($dosen) > 100) 
                $error = 'Nama dosen harus 3-100 karakter.';
            elseif (!ctype_digit($semester) || (int)$semester < 1 || (int)$semester > 14) 
                $error = 'Semester harus angka bulat 1-14.';
        } 
        else {
            $mkValid = ctype_digit($mkId) && (int)$mkId > 0;

            if (!$mkValid) 
                $error = 'Pilih mata kuliah yang valid.';
            else {
                $cek = $conn->prepare('SELECT id FROM mata_kuliah WHERE id = ?');
                $mkInt = (int)$mkId; $cek->bind_param('i', $mkInt); $cek->execute();
                if (!$cek->get_result()->fetch_assoc()) 
                    $error = 'Mata kuliah tidak ditemukan.';
            }

            if (!$error && (panjang($judul) < 3 || panjang($judul) > 150)) 
                $error = 'Judul harus 3-150 karakter.';
            if (!$error && $jenis === 'materi' && (panjang($isi) < 3 || panjang($isi) > 2000)) 
                $error = 'Isi materi harus 3-2000 karakter.';
            if (!$error && $jenis === 'materi' && (panjang($tautan) > 255 || !preg_match('/^https?:\/\//i', $tautan) || !filter_var($tautan, FILTER_VALIDATE_URL))) 
                $error = 'Tautan harus URL valid yang diawali http:// atau https://.';
            if (!$error && $jenis === 'tugas' && (panjang($instruksi) < 3 || panjang($instruksi) > 2000)) 
                $error = 'Instruksi harus 3-2000 karakter.';
            if (!$error && $jenis === 'tugas') {
                $tanggal = DateTime::createFromFormat('!Y-m-d', $tenggat);
                
                if (!$tanggal || $tanggal->format('Y-m-d') !== $tenggat) 
                    $error = 'Tanggal tenggat tidak valid.';
                elseif (!in_array($status, ['Draf','Aktif','Selesai'], true)) 
                    $error = 'Status tugas tidak valid.';
            }
        }

        if (!$error && $aksiPost === 'simpan_edit' && !$idPost) 
            $error = 'ID untuk edit tidak valid.';
        if (!$error && $aksiPost === 'simpan_edit' && !ambilSatu($conn, $jenis, $idPost)) 
            $error = 'Data yang mau diedit tidak ditemukan.';

        if (!$error) {
            try {
                if ($jenis === 'mata_kuliah') {
                    if ($aksiPost === 'simpan_baru') 
                        $stmt = $conn->prepare('INSERT INTO mata_kuliah (kode,nama,dosen,semester) VALUES (?,?,?,?)');
                    else 
                        $stmt = $conn->prepare('UPDATE mata_kuliah SET kode=?, nama=?, dosen=?, semester=? WHERE id=?');
                        $semInt = (int)$semester;
                    if ($aksiPost === 'simpan_baru') 
                        $stmt->bind_param('sssi', $kode,$nama,$dosen,$semInt);
                    else 
                        $stmt->bind_param('sssii', $kode,$nama,$dosen,$semInt,$idPost);
                } 
                elseif ($jenis === 'materi') {
                    if ($aksiPost === 'simpan_baru') 
                        $stmt = $conn->prepare('INSERT INTO materi (mata_kuliah_id,judul,isi,tautan) VALUES (?,?,?,?)');
                    else 
                        $stmt = $conn->prepare('UPDATE materi SET mata_kuliah_id=?, judul=?, isi=?, tautan=? WHERE id=?');
                    if ($aksiPost === 'simpan_baru') 
                        $stmt->bind_param('isss', $mkInt,$judul,$isi,$tautan);
                    else 
                        $stmt->bind_param('isssi', $mkInt,$judul,$isi,$tautan,$idPost);
                } 
                else {
                    if ($aksiPost === 'simpan_baru') 
                        $stmt = $conn->prepare('INSERT INTO tugas (mata_kuliah_id,judul,instruksi,tenggat,status) VALUES (?,?,?,?,?)');
                    else 
                        $stmt = $conn->prepare('UPDATE tugas SET mata_kuliah_id=?, judul=?, instruksi=?, tenggat=?, status=? WHERE id=?');
                    if ($aksiPost === 'simpan_baru') 
                        $stmt->bind_param('issss', $mkInt,$judul,$instruksi,$tenggat,$status);
                    else 
                        $stmt->bind_param('issssi', $mkInt,$judul,$instruksi,$tenggat,$status,$idPost);
                }

                $stmt->execute();

                if ($aksiPost === 'simpan_edit' && $stmt->affected_rows === 0) {
                    $cek = ambilSatu($conn, $jenis, $idPost);
                    $pesan = 'Tidak ada perubahan pada data.';
                } 
                else {
                    $pesan = $aksiPost === 'simpan_baru' ? 'Data berhasil ditambahkan.' : 'Data berhasil diperbarui.';
                }

                $_SESSION['pesan_sementara'] = $pesan;
                header('Location: ' . urlJenis($jenis));
                exit;

            } 
            catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() === 1062) 
                    $error = 'Kode mata kuliah sudah digunakan. Gunakan kode lain.';
                elseif ($ex->getCode() === 1451) 
                    $error = 'Data masih digunakan oleh data lain dan tidak bisa dihapus.';
                else 
                    $error = 'Data gagal disimpan. Periksa kembali isian dan database.';
            }
        }
        if ($aksiPost === 'simpan_edit' && $idPost) 
            $barisEdit = ambilSatu($conn, $jenis, $idPost);
    }
}
if (isset($_SESSION['pesan_sementara'])) { 
    $pesan = $_SESSION['pesan_sementara']; 
    unset($_SESSION['pesan_sementara']); 
}

$namaJenis = ['mata_kuliah'=>'Mata Kuliah','materi'=>'Materi','tugas'=>'Tugas'];
$mataKuliah = ambilMataKuliah($conn);
$keyword = trim($_GET['q'] ?? '');
$filterMk = trim($_GET['mk'] ?? '');
$detail = null;
if ($aksi === 'detail' && $barisEdit) 
    $detail = $barisEdit;

$jumlah = [];
foreach ($jenisValid as $t) { 
    $r = $conn->query('SELECT COUNT(*) AS total FROM ' . $t)->fetch_assoc(); $jumlah[$t] = $r['total']; }

$daftar = [];
if ($aksi === 'daftar') {
    if ($jenis === 'mata_kuliah') {
        $sql = 'SELECT * FROM mata_kuliah';
        if ($keyword !== '') { 
            $sql .= ' WHERE kode LIKE ? OR nama LIKE ?'; 
            $stmt=$conn->prepare($sql . ' ORDER BY nama'); 
            $cari='%'.$keyword.'%'; 
            $stmt->bind_param('ss',$cari,$cari); 
            $stmt->execute(); 
            $daftar=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
        }
        else 
            $daftar=$conn->query($sql . ' ORDER BY nama')->fetch_all(MYSQLI_ASSOC);
    } 
    else {
        $kolom = $jenis === 'materi' ? 'materi' : 'tugas';
        $sql = 'SELECT ' . $kolom . '.*, mata_kuliah.nama AS nama_mk FROM ' . $kolom . ' JOIN mata_kuliah ON ' . $kolom . '.mata_kuliah_id=mata_kuliah.id WHERE 1=1';
        $tipe=''; 
        $params=[];
        if ($keyword !== '') { 
            $sql .= ' AND ' . $kolom . '.judul LIKE ?'; 
            $tipe.='s'; 
            $params[]='%'.$keyword.'%'; 
        }
        if ($filterMk !== '' && ctype_digit($filterMk)) { 
            $sql .= ' AND ' . $kolom . '.mata_kuliah_id = ?'; 
            $tipe.='i'; 
            $params[]=(int)$filterMk; 
        }
        $sql .= ' ORDER BY ' . $kolom . '.id DESC';
        $stmt=$conn->prepare($sql);
        if ($tipe !== '') 
            $stmt->bind_param($tipe, ...$params);
            $stmt->execute(); $daftar=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$nilaiForm = $_POST;
if ($barisEdit && !isset($_POST['aksi'])) 
    $nilaiForm = $barisEdit;
if ($aksi === 'edit' && $barisEdit && isset($_POST['aksi']) && ($_POST['aksi'] ?? '') === 'simpan_edit') 
    $nilaiForm = $_POST;
?>

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>LMS Kampus</title><link rel="stylesheet" href="style.css">
    </head>
    <body>
        <header class="topbar">
            <a class="brand" href="lms.php">
                <span class="brand-icon">L</span>
                <span>
                    ruang
                    <span class="brand-light">
                        kelas
                    </span>
                    <small>Learning Management System</small>
                </span>
            </a>
            <span class="top-note">PAW • Modul 2</span>
        </header>
        <div class="layout">
        <aside class="sidebar">
                <p class="side-label">MENU UTAMA</p>
                <a class="nav-link <?= $aksi==='beranda'?'active':'' ?>" href="lms.php?aksi=beranda">
                    <span>⌂</span> 
                    Beranda
                </a>
                <?php foreach ($jenisValid as $j): ?>
                <a class="nav-link <?= $jenis===$j && $aksi!=='beranda'?'active':'' ?>" href="<?= e(urlJenis($j)) ?>">
                <span>
                    <?= $j==='mata_kuliah'?'▤':($j==='materi'?'▧':'☷') ?>
                </span> 
                <?= e($namaJenis[$j]) ?>
            </a>
            <?php endforeach; ?>
            <div class="side-bottom">
                <div class="side-dot">
            </div>
            <div>
                <b>PAW LMS</b>
                <small>Kelola belajar lebih mudah</small>
            </div>
        </div>
        </aside>

        <main class="main">
            <?php if ($pesan !== ''): ?>
            <div class="alert success">
                <?= e($pesan) ?>
            </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
            <div class="alert danger">
                <?= e($error) ?>
            </div>
            <?php endif; ?>
            <?php if ($aksi === 'beranda' || !isset($_GET['jenis']) && !isset($_GET['aksi'])): ?>
            <div class="page-heading">
                <div>
                    <p class="eyebrow">RINGKASAN</p>
                    <h1>Halo, selamat datang! 👋</h1>
                    <p class="subheading">Semua kebutuhan kelas kamu, ada di satu tempat.</p>
                </div>
            </div>
            <div class="stats">
                <div class="stat-card purple">
                    <div class="stat-top">
                        <span>Mata Kuliah</span>
                        <span class="stat-symbol">▤</span>
                    </div>
                    <strong>
                        <?= $jumlah['mata_kuliah'] ?>
                    </strong>
                    <small>Data mata kuliah</small>
                </div>
                <div class="stat-card green">
                    <div class="stat-top">
                        <span>Materi</span>
                        <span class="stat-symbol">▧</span>
                    </div>
                    <strong>
                        <?= $jumlah['materi'] ?>
                    </strong>
                    <small>Materi pembelajaran</small>
                </div>
                <div class="stat-card orange">
                    <div class="stat-top">
                        <span>Tugas</span>
                        <span class="stat-symbol">☷</span>
                    </div>
                    <strong>
                        <?= $jumlah['tugas'] ?>
                    </strong>
                    <small>Daftar tugas</small>
                </div>
            </div>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Mulai dari sini</h2>
                        <p>Pilih data yang mau kamu kelola.</p>
                    </div>
                </div>
                <div class="quick-links">
                    <a href="lms.php?jenis=mata_kuliah">
                        <span class="quick-icon">▤</span>
                        <b>Mata Kuliah</b>
                        <small>Atur daftar kelas</small>
                        <span class="arrow">→</span>
                    </a>
                    <a href="lms.php?jenis=materi">
                        <span class="quick-icon green-icon">▧</span>
                        <b>Materi</b>
                        <small>Buka bahan belajar</small>
                        <span class="arrow">→</span>
                    </a>
                    <a href="lms.php?jenis=tugas">
                        <span class="quick-icon orange-icon">☷</span>
                        <b>Tugas</b>
                        <small>Cek instruksi tugas</small>
                        <span class="arrow">→</span>
                    </a>
                </div>
            </section>
            <?php elseif ($aksi === 'tambah' || $aksi === 'edit'): ?>
            <div class="page-heading">
                <div>
                    <p class="eyebrow"><?= $aksi==='edit'?'UBAH DATA':'DATA BARU' ?></p>
                    <h1><?= $aksi==='edit'?'Edit ':'Tambah ' . e($namaJenis[$jenis]) ?></h1>
                    <p class="subheading">Isi bagian di bawah dengan data yang sesuai.</p>
                </div>
                <a class="btn btn-light" href="<?= e(urlJenis($jenis)) ?>">← Kembali</a>
            </div>

            <form class="panel form-panel" method="post" action="<?= e(urlJenis($jenis, '&aksi=' . $aksi . ($aksi==='edit'?'&id='.(int)$id:''))) ?>">
                <input type="hidden" name="aksi" value="<?= $aksi==='edit'?'simpan_edit':'simpan_baru' ?>">
                <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <?php if ($aksi==='edit'): ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
                <?php if ($jenis==='mata_kuliah'): ?>
                    <div class="form-grid">
                        <label>Kode Mata Kuliah
                            <input name="kode" maxlength="20" required value="<?= e($nilaiForm['kode'] ?? '') ?>" placeholder="Contoh: PAW">
                            <small>3-20 karakter, huruf kapital, angka, atau -.</small>
                        </label>
                        <label>Nama Mata Kuliah
                            <input name="nama" minlength="3" maxlength="100" required value="<?= e($nilaiForm['nama'] ?? '') ?>" placeholder="Nama mata kuliah">
                        </label>
                        <label>Nama Dosen
                            <input name="dosen" minlength="3" maxlength="100" required value="<?= e($nilaiForm['dosen'] ?? '') ?>" placeholder="Nama dosen">
                        </label>
                        <label>Semester
                            <input type="number" name="semester" min="1" max="14" required value="<?= e($nilaiForm['semester'] ?? '') ?>" placeholder="1-14">
                        </label>
                    </div>
                <?php else: ?>
                    <label>Mata Kuliah
                        <select name="mata_kuliah_id" required>
                            <option value="">Pilih mata kuliah</option>
                            <?php foreach ($mataKuliah as $mk): ?>
                                <option value="<?= (int)$mk['id'] ?>" <?= (string)($nilaiForm['mata_kuliah_id'] ?? '')===(string)$mk['id']?'selected':'' ?>>
                                    <?= e($mk['kode'].' - '.$mk['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Judul
                        <input name="judul" minlength="3" maxlength="150" required value="<?= e($nilaiForm['judul'] ?? '') ?>" placeholder="Masukkan judul">
                    </label>
                <?php if ($jenis==='materi'): ?>
                    <label>Isi Materi
                        <textarea name="isi" minlength="3" maxlength="2000" rows="5" required placeholder="Tulis ringkasan materi...">
                            <?= e($nilaiForm['isi'] ?? '') ?>
                        </textarea>
                    </label>
                    <label>Tautan Materi
                        <input type="url" name="tautan" maxlength="255" required value="<?= e($nilaiForm['tautan'] ?? '') ?>" placeholder="https://contoh.com">
                        <small>Gunakan tautan yang diawali http:// atau https://.</small>
                    </label>
                <?php else: ?>
                    <label>Instruksi Tugas
                        <textarea name="instruksi" minlength="3" maxlength="2000" rows="5" required placeholder="Jelaskan tugas yang harus dikerjakan..."><?= e($nilaiForm['instruksi'] ?? '') ?></textarea>
                    </label>
                    <div class="form-grid">
                        <label>Tenggat
                            <input type="date" name="tenggat" required value="<?= e($nilaiForm['tenggat'] ?? '') ?>">
                        </label>
                        <label>Status
                            <select name="status" required><?php foreach (['Draf','Aktif','Selesai'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($nilaiForm['status'] ?? 'Draf')===$s?'selected':'' ?>>
                                    <?= $s ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
                <div class="form-actions">
                    <a class="btn btn-light" href="<?= e(urlJenis($jenis)) ?>">
                        Batal
                    </a>
                    <button class="btn btn-primary" type="submit">
                        Simpan Data
                    </button>
                </div>
            </form>
            <?php elseif ($aksi==='detail' && $detail): ?>
                <div class="page-heading">
                    <div>
                        <p class="eyebrow">DETAIL DATA</p>
                        <h1><?= e($detail['judul'] ?? $detail['nama']) ?></h1>
                        <p class="subheading">Informasi lengkap data yang dipilih.</p>
                    </div>
                    <a class="btn btn-light" href="<?= e(urlJenis($jenis)) ?>">← Kembali</a>
                </div>
                <section class="panel detail-panel">
                    <?php if ($jenis==='mata_kuliah'): ?>
                        <div class="detail-row">
                            <span>Kode</span>
                            <b><?= e($detail['kode']) ?></b>
                        </div>
                        <div class="detail-row">
                            <span>Nama Mata Kuliah</span>
                            <b><?= e($detail['nama']) ?></b>
                        </div>
                        <div class="detail-row">
                            <span>Dosen</span>
                            <b><?= e($detail['dosen']) ?></b>
                        </div>
                        <div class="detail-row">
                            <span>Semester</span>
                            <b><?= e($detail['semester']) ?></b>
                        </div>
                    <?php else: ?>
                        <div class="detail-row">
                            <span>Mata Kuliah</span>
                            <b><?= e($detail['nama_mk']) ?></b>
                        </div>
                        <div class="detail-row">
                            <span>Judul</span>
                            <b><?= e($detail['judul']) ?></b>
                        </div>
                    <?php if ($jenis==='materi'): ?>
                        <div class="detail-row vertical">
                            <span>Isi Materi</span>
                            <p><?= nl2br(e($detail['isi'])) ?></p>
                        </div>
                        <div class="detail-row">
                            <span>Tautan</span>
                            <a href="<?= e($detail['tautan']) ?>" target="_blank" rel="noopener noreferrer"><?= e($detail['tautan']) ?></a>
                        </div>
                    <?php else: ?>
                        <div class="detail-row vertical">
                            <span>Instruksi</span>
                            <p><?= nl2br(e($detail['instruksi'])) ?></p>
                        </div>
                        <div class="detail-row">
                            <span>Tenggat</span>
                            <b><?= e($detail['tenggat']) ?></b>
                        </div>
                        <div class="detail-row">
                            <span>Status</span>
                            <span class="status"><?= e($detail['status']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <div class="form-actions">
                        <a class="btn btn-light" href="<?= e(urlJenis($jenis,'&aksi=edit&id='.(int)$id)) ?>">Edit Data</a>
                    </div>
                </section>
            <?php elseif ($aksi==='konfirmasi' && $barisEdit): ?>
                <div class="page-heading">
                    <div>
                        <p class="eyebrow">KONFIRMASI</p>
                        <h1>Hapus data ini?</h1>
                        <p class="subheading">Pastikan kamu memang ingin menghapus data yang dipilih.</p>
                    </div>
                </div>
                <section class="panel">
                    <p>Data: <b><?= e($barisEdit['judul'] ?? $barisEdit['nama']) ?></b></p>
                    <p class="subheading">Tindakan ini tidak bisa dibatalkan. Mata kuliah yang masih memiliki materi atau tugas tidak dapat dihapus.</p>
                    <form method="post" action="<?= e(urlJenis($jenis)) ?>">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <div class="form-actions">
                            <a class="btn btn-light" href="<?= e(urlJenis($jenis)) ?>">Batal</a>
                            <button class="btn btn-danger" type="submit">Ya, Hapus</button>
                        </div>
                    </form>
                </section>
            <?php else: ?>
                <div class="page-heading">
                    <div>
                        <p class="eyebrow">PENGELOLAAN</p>
                        <h1><?= e($namaJenis[$jenis]) ?></h1>
                        <p class="subheading">Lihat dan kelola data <?= strtolower($namaJenis[$jenis]) ?> kamu.</p>
                    </div>
                    <a class="btn btn-primary" href="<?= e(urlJenis($jenis,'&aksi=tambah')) ?>">＋ Tambah Data</a>
                </div>
                <section class="panel">
                    <form class="search-bar" method="get" action="lms.php">
                        <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                        <input type="search" name="q" value="<?= e($keyword) ?>" placeholder="<?= $jenis==='mata_kuliah'?'Cari kode atau nama mata kuliah...':'Cari judul '.strtolower($namaJenis[$jenis]).'...' ?>">
                        <?php if ($jenis!=='mata_kuliah'): ?>
                            <select name="mk">
                                <option value="">Semua mata kuliah</option>
                                <?php foreach ($mataKuliah as $mk): ?>
                                    <option value="<?= (int)$mk['id'] ?>" <?= (string)$filterMk===(string)$mk['id']?'selected':'' ?>>
                                        <?= e($mk['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            <button class="btn btn-primary" type="submit">Cari</button>
                            <?php if ($keyword!=='' || $filterMk!==''): ?>
                                <a class="btn btn-light" href="<?= e(urlJenis($jenis)) ?>">Reset</a>
                                <?php endif; ?>
                    </form>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($jenis==='mata_kuliah'): ?>
                                    <th>Kode</th>
                                    <th>Nama Mata Kuliah</th>
                                    <th>Dosen</th>
                                    <th>Semester</th>
                                <?php else: ?>
                                    <th>Judul</th>
                                    <th>Mata Kuliah</th>
                                    <?php if ($jenis==='materi'): ?>
                                        <th>Tautan</th>
                                    <?php else: ?>
                                        <th>Tenggat</th>
                                        <th>Status</th>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftar as $d): ?>
                                <tr>
                                    <?php if ($jenis==='mata_kuliah'): ?>
                                        <td>
                                            <span class="code-pill"><?= e($d['kode']) ?></span>
                                        </td>
                                        <td class="name-cell"><?= e($d['nama']) ?></td>
                                        <td><?= e($d['dosen']) ?></td><td><?= e($d['semester']) ?></td>
                                    <?php else: ?>
                                        <td class="name-cell"><?= e($d['judul']) ?></td>
                                        <td><?= e($d['nama_mk']) ?></td>
                                    <?php if ($jenis==='materi'): ?>
                                        <td>
                                            <a class="table-link" href="<?= e($d['tautan']) ?>" target="_blank" rel="noopener noreferrer">
                                                Buka tautan ↗
                                            </a>
                                        </td>
                                    <?php else: ?>
                                        <td><?= e($d['tenggat']) ?></td>
                                        <td>
                                            <span class="status status-<?= strtolower($d['status']) ?>"><?= e($d['status']) ?></span>
                                        </td>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <td>
                                        <div class="actions">
                                            <a title="Detail" href="<?= e(urlJenis($jenis,'&aksi=detail&id='.(int)$d['id'])) ?>">Lihat</a>
                                            <a title="Edit" href="<?= e(urlJenis($jenis,'&aksi=edit&id='.(int)$d['id'])) ?>">Edit</a>
                                            <a class="delete-link" title="Hapus" href="<?= e(urlJenis($jenis,'&aksi=konfirmasi&id='.(int)$d['id'])) ?>">Hapus</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$daftar): ?>
                                <tr>
                                    <td class="empty" colspan="6">
                                        <span>⌕</span>
                                        <b>Data belum ditemukan</b>
                                        <small>Coba kata kunci lain atau tekan Reset.</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">Total data ditemukan: <b><?= count($daftar) ?></b>
                </div>
            </section>
            <?php endif; ?>
            <footer>© <?= date('Y') ?> ruangkelas • Praktikum PAW</footer>
        </main>
    </div>
    </body>
</html>
