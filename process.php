<?php
require 'config/db.php';

header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}


$tanggal    = trim($_POST['tanggal']   ?? '');
$tipe       = trim($_POST['tipe']      ?? '');
$deskripsi  = trim($_POST['deskripsi'] ?? '');
$akun_masuk = trim($_POST['akun_masuk']  ?? '');
$akun_keluar= trim($_POST['akun_keluar'] ?? '');
$terlibat   = trim($_POST['terlibat']  ?? '');


$masuk  = preg_replace('/[^0-9]/', '', $_POST['masuk']  ?? '0');
$keluar = preg_replace('/[^0-9]/', '', $_POST['keluar'] ?? '0');
$admin  = preg_replace('/[^0-9]/', '', $_POST['admin']  ?? '0');


$masuk  = $masuk  ?: '0';
$keluar = $keluar ?: '0';
$admin  = $admin  ?: '0';


$errors = [];

if (empty($tanggal)) {
    $errors[] = 'Tanggal wajib diisi.';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $errors[] = 'Format tanggal tidak valid.';
}

$tipe_valid = ['INCOME', 'EXPENSE', 'TRANSFER', 'WITHDRAWAL'];
if (!in_array($tipe, $tipe_valid, true)) {
    $errors[] = 'Tipe transaksi tidak valid.';
}


if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}


$deskripsi = htmlspecialchars($deskripsi, ENT_QUOTES, 'UTF-8');


$stmt = $conn->prepare("
    INSERT INTO transactions
        (tanggal, tipe, deskripsi, masuk, keluar, admin, akun_masuk, akun_keluar, terlibat)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'sssiiisss',
    $tanggal,
    $tipe,
    $deskripsi,
    $masuk,
    $keluar,
    $admin,
    $akun_masuk,
    $akun_keluar,
    $terlibat
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Transaksi berhasil disimpan.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $stmt->error]);
}

$stmt->close();
$conn->close();