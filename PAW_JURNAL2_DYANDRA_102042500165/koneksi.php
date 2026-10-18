<?php
$conn = new mysqli('localhost', 'root', '', 'db_lms');
if ($conn->connect_error) {
    die('Koneksi database gagal. Periksa MySQL dan nama database.');
}
$conn->set_charset('utf8mb4');
