<?php
require 'config/db.php';

$filter_tipe = trim($_GET['tipe'] ?? '');
$tipe_valid  = ['INCOME', 'EXPENSE', 'TRANSFER', 'WITHDRAWAL'];

$where = ''; $params = []; $types = '';
if ($filter_tipe && in_array($filter_tipe, $tipe_valid, true)) {
    $where = 'WHERE tipe = ?';
    $params[] = $filter_tipe;
    $types = 's';
}

$sql = "SELECT DATE_FORMAT(tanggal,'%d/%m/%Y') AS tanggal, tipe, deskripsi,
               masuk, keluar, admin, akun_masuk, akun_keluar, terlibat
        FROM transactions $where ORDER BY tanggal ASC, id ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = []; $total_masuk = 0; $total_keluar = 0;
while ($row = $result->fetch_assoc()) {
    $rows[]       = $row;
    $total_masuk  += (int)$row['masuk'];
    $total_keluar += (int)$row['keluar'];
}
$stmt->close();

function rp(int $n): string {
    return $n > 0 ? 'Rp ' . number_format($n, 0, ',', '.') : '—';
}
function badge(string $tipe): string {
    $cls = ['INCOME'=>'badge-income','EXPENSE'=>'badge-expense','TRANSFER'=>'badge-transfer','WITHDRAWAL'=>'badge-withdrawal'][$tipe] ?? '';
    return "<span class='badge $cls'>" . htmlspecialchars($tipe) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Transaksi — MiceyStore</title>
    <style>
        <?php include 'assets/style.css'; ?>
        @media print {
            .topnav, .no-print { display: none !important; }
            body::before { display: none; }
            .card { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>

<nav class="topnav no-print">
    <a href="index.php" class="topnav-brand">🌊 MiceyStore</a>
    <div class="topnav-links">
        <a href="add.php" class="btn btn-primary btn-sm">＋ Input</a>
        <a href="list.php" class="btn btn-ghost btn-sm">🫧 List</a>
    </div>
</nav>

<div class="page-wrap-wide">

    <div class="page-header">
        <div>
            <h1>Daftar Transaksi 🪼</h1>
            <p>
                Total <?= count($rows) ?> transaksi
                <?= $filter_tipe ? "· Filter: <strong style='color:var(--cyan)'>$filter_tipe</strong>" : '' ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;" class="no-print">
            <a href="list.php" class="btn btn-ghost btn-sm">Semua</a>
            <a href="?tipe=INCOME"     class="btn btn-sm <?= $filter_tipe==='INCOME'     ? 'btn-success' : 'btn-ghost' ?>">INCOME</a>
            <a href="?tipe=EXPENSE"    class="btn btn-sm <?= $filter_tipe==='EXPENSE'    ? 'btn-primary' : 'btn-ghost' ?>">EXPENSE</a>
            <a href="?tipe=TRANSFER"   class="btn btn-sm <?= $filter_tipe==='TRANSFER'   ? 'btn-primary' : 'btn-ghost' ?>">TRANSFER</a>
            <a href="?tipe=WITHDRAWAL" class="btn btn-sm <?= $filter_tipe==='WITHDRAWAL' ? 'btn-primary' : 'btn-ghost' ?>">WITHDRAWAL</a>
            <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨 Print</button>
        </div>
    </div>

    <div class="summary-row">
        <div class="summary-card">
            <div class="summary-label">Total Masuk</div>
            <div class="summary-value green"><?= rp($total_masuk) ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Keluar</div>
            <div class="summary-value red"><?= rp($total_keluar) ?></div>
        </div>
        <div class="summary-card">
            <?php $net = $total_masuk - $total_keluar; ?>
            <div class="summary-label">Net Selisih</div>
            <div class="summary-value <?= $net >= 0 ? 'green' : 'red' ?>">
                <?= ($net < 0 ? '−' : '') . rp(abs($net)) ?>
            </div>
        </div>
    </div>

    <div class="card">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <div class="icon">🫧</div>
                <p>Belum ada transaksi<?= $filter_tipe ? " dengan tipe $filter_tipe" : '' ?>.</p>
                <a href="add.php" class="btn btn-primary" style="margin-top:16px;">＋ Tambah Transaksi</a>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Deskripsi</th>
                        <th class="text-right">Masuk</th>
                        <th class="text-right">Keluar</th>
                        <th class="text-right">Admin</th>
                        <th>Akun Masuk</th>
                        <th>Akun Keluar</th>
                        <th>Terlibat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['tanggal']) ?></td>
                        <td><?= badge($r['tipe']) ?></td>
                        <td><?= htmlspecialchars($r['deskripsi']) ?></td>
                        <td class="text-right text-money <?= $r['masuk'] > 0 ? 'money-masuk' : '' ?>"><?= rp((int)$r['masuk']) ?></td>
                        <td class="text-right text-money <?= $r['keluar'] > 0 ? 'money-keluar' : '' ?>"><?= rp((int)$r['keluar']) ?></td>
                        <td class="text-right text-money"><?= rp((int)$r['admin']) ?></td>
                        <td><?= htmlspecialchars($r['akun_masuk']) ?></td>
                        <td><?= htmlspecialchars($r['akun_keluar']) ?></td>
                        <td><?= htmlspecialchars($r['terlibat']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($rows)): ?>
    <div style="margin-top:10px;text-align:right;" class="no-print">
        <span style="font-size:12px;color:var(--text-muted);">
            💡 Pilih semua isi tabel → Ctrl+C → Paste di Google Sheets / Excel
        </span>
    </div>
    <?php endif; ?>

</div>
</body>
</html>