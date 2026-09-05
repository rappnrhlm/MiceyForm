<?php

require 'config/db.php';
require 'functions.php';

$action = trim($_POST['action'] ?? '');

if ($action === 'su_login') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }
    $pin = trim($_POST['pin'] ?? '');
    echo json_encode(su_login($pin, $conn));
    exit;
}

if ($action === 'su_logout') {
    csrf_verify();
    su_logout();
    header('Location: list.php?msg=su_logout');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if ($action === '' || $action === 'add') {
    header('Content-Type: application/json');
    csrf_verify();

    $tanggal    = trim($_POST['tanggal']     ?? '');
    $tipe       = trim($_POST['tipe']        ?? '');
    $deskripsi  = trim($_POST['deskripsi']   ?? '');
    $akun_masuk = trim($_POST['akun_masuk']  ?? '');
    $akun_keluar= trim($_POST['akun_keluar'] ?? '');
    $terlibat   = trim($_POST['terlibat']    ?? '');

    $masuk  = preg_replace('/[^0-9]/', '', $_POST['masuk']  ?? '0') ?: '0';
    $keluar = preg_replace('/[^0-9]/', '', $_POST['keluar'] ?? '0') ?: '0';
    $admin  = preg_replace('/[^0-9]/', '', $_POST['admin']  ?? '0') ?: '0';

    $errors = [];
    if (empty($tanggal))                                                              $errors[] = 'Tanggal wajib diisi.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal))                              $errors[] = 'Format tanggal tidak valid.';
    if (!in_array($tipe, ['INCOME','EXPENSE','TRANSFER','WITHDRAWAL'], true))         $errors[] = 'Tipe transaksi tidak valid.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    $deskripsi = htmlspecialchars($deskripsi, ENT_QUOTES, 'UTF-8');

    $stmt = $conn->prepare("
        INSERT INTO transactions
            (tanggal, tipe, deskripsi, masuk, keluar, admin, akun_masuk, akun_keluar, terlibat, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('sssiiisss', $tanggal, $tipe, $deskripsi, $masuk, $keluar, $admin, $akun_masuk, $akun_keluar, $terlibat);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Transaksi berhasil disimpan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

csrf_verify();

if ($action === 'confirm') {
    if (!is_superuser()) { header('Location: list.php?msg=su_required'); exit; }
    su_refresh();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { header('Location: list.php?msg=error'); exit; }
    $stmt = $conn->prepare("UPDATE transactions SET status='CONFIRMED', confirmed_at=NOW() WHERE id=? AND is_deleted=0 AND status='PENDING'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    header('Location: list.php?msg=' . ($affected > 0 ? 'confirmed' : 'error'));
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { header('Location: list.php?msg=error'); exit; }
    $chk = $conn->prepare("SELECT status FROM transactions WHERE id=? AND is_deleted=0 LIMIT 1");
    $chk->bind_param('i', $id);
    $chk->execute();
    $chk->bind_result($cur_status);
    $chk->fetch();
    $chk->close();
    if ($cur_status === 'CONFIRMED' && !is_superuser()) { header('Location: list.php?msg=su_required'); exit; }
    if (is_superuser()) su_refresh();
    $stmt = $conn->prepare("UPDATE transactions SET is_deleted=1 WHERE id=? AND is_deleted=0");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    header('Location: list.php?msg=' . ($affected > 0 ? 'deleted' : 'error'));
    exit;
}

if ($action === 'bulk_confirm') {
    if (!is_superuser()) { header('Location: list.php?msg=su_required'); exit; }
    su_refresh();
    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v) => $v > 0);
    if (empty($ids)) { header('Location: list.php?msg=no_selection'); exit; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("UPDATE transactions SET status='CONFIRMED', confirmed_at=NOW() WHERE id IN ($placeholders) AND is_deleted=0 AND status='PENDING'");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    header("Location: list.php?msg=bulk_confirmed&count={$affected}");
    exit;
}

if ($action === 'bulk_delete') {
    if (!is_superuser()) { header('Location: list.php?msg=su_required'); exit; }
    su_refresh();
    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v) => $v > 0);
    if (empty($ids)) { header('Location: list.php?msg=no_selection'); exit; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("UPDATE transactions SET is_deleted=1 WHERE id IN ($placeholders) AND is_deleted=0");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    header("Location: list.php?msg=bulk_deleted&count={$affected}");
    exit;
}

if ($action === 'export') {
    if (!is_superuser()) { header('Location: list.php?msg=su_required'); exit; }
    su_refresh();

    $filter_tipe   = trim($_POST['filter_tipe']   ?? '');
    $filter_status = trim($_POST['filter_status'] ?? '');
    $filter_bulan  = trim($_POST['filter_bulan']  ?? '');
    $filter_dari   = trim($_POST['filter_dari']   ?? '');
    $filter_sampai = trim($_POST['filter_sampai'] ?? '');

    $where_parts = ['t.is_deleted = 0'];
    $params = []; $types = '';

    if ($filter_tipe && in_array($filter_tipe, ['INCOME','EXPENSE','TRANSFER','WITHDRAWAL'], true)) {
        $where_parts[] = 't.tipe = ?'; $params[] = $filter_tipe; $types .= 's';
    }
    if ($filter_status && in_array($filter_status, ['PENDING','CONFIRMED'], true)) {
        $where_parts[] = 't.status = ?'; $params[] = $filter_status; $types .= 's';
    }
    if ($filter_bulan && preg_match('/^\d{4}-\d{2}$/', $filter_bulan)) {
        $where_parts[] = "DATE_FORMAT(t.tanggal,'%Y-%m') = ?"; $params[] = $filter_bulan; $types .= 's';
    }
    if ($filter_dari && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_dari)) {
        $where_parts[] = 't.tanggal >= ?'; $params[] = $filter_dari; $types .= 's';
    }
    if ($filter_sampai && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_sampai)) {
        $where_parts[] = 't.tanggal <= ?'; $params[] = $filter_sampai; $types .= 's';
    }

    $where = 'WHERE ' . implode(' AND ', $where_parts);
    $sql   = "SELECT t.id, t.tanggal, t.tipe, t.deskripsi, t.masuk, t.keluar, t.admin,
                     t.akun_masuk, t.akun_keluar, t.terlibat, t.status, t.confirmed_at
              FROM transactions t $where ORDER BY t.tanggal ASC, t.id ASC";

    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transaksi_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-store, no-cache');
    header('Pragma: no-cache');

    $out = fopen('php:
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID','Tanggal','Tipe','Deskripsi','Masuk','Keluar','Admin','Akun Masuk','Akun Keluar','Terlibat','Status','Confirmed At']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [$row['id'],$row['tanggal'],$row['tipe'],$row['deskripsi'],
                       $row['masuk'],$row['keluar'],$row['admin'],
                       $row['akun_masuk'],$row['akun_keluar'],$row['terlibat'],
                       $row['status'],$row['confirmed_at'] ?? '']);
    }
    fclose($out);
    $stmt->close(); $conn->close();
    exit;
}

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Unknown action.']);

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