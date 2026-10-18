CREATE DATABASE IF NOT EXISTS db_lms;
USE db_lms;

CREATE TABLE mata_kuliah (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  dosen VARCHAR(100) NOT NULL,
  semester TINYINT UNSIGNED NOT NULL,
  CHECK (semester BETWEEN 1 AND 14)
) ENGINE=InnoDB;

CREATE TABLE materi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mata_kuliah_id INT UNSIGNED NOT NULL,
  judul VARCHAR(150) NOT NULL,
  isi TEXT NOT NULL,
  tautan VARCHAR(255) NOT NULL,
  CONSTRAINT fk_materi_mk FOREIGN KEY (mata_kuliah_id)
    REFERENCES mata_kuliah(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tugas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mata_kuliah_id INT UNSIGNED NOT NULL,
  judul VARCHAR(150) NOT NULL,
  instruksi TEXT NOT NULL,
  tenggat DATE NOT NULL,
  status ENUM('Draf','Aktif','Selesai') NOT NULL DEFAULT 'Draf',
  CONSTRAINT fk_tugas_mk FOREIGN KEY (mata_kuliah_id)
    REFERENCES mata_kuliah(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO mata_kuliah (kode,nama,dosen,semester) VALUES
('PAW','Pemrograman Aplikasi Web','Pak Andi',2),
('BASDAT','Basis Data','Bu Rina',2);

INSERT INTO materi (mata_kuliah_id,judul,isi,tautan) VALUES
(1,'Pengenalan PHP','Materi dasar tentang variabel, tipe data, dan sintaks PHP.','https://www.php.net/manual/en/'),
(2,'Dasar SQL','Materi pengenalan query SELECT, INSERT, UPDATE, dan DELETE.','https://dev.mysql.com/doc/');

INSERT INTO tugas (mata_kuliah_id,judul,instruksi,tenggat,status) VALUES
(1,'Latihan CRUD','Buat aplikasi sederhana dengan fitur tambah, lihat, edit, dan hapus data.','2026-11-01','Aktif'),
(2,'Latihan Query','Buat beberapa query SQL berdasarkan data latihan.','2026-11-05','Draf');
