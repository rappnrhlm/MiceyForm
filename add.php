<?php
require 'config/db.php';

$accounts = [];
$stmt = $conn->prepare("SELECT kode, nama, owner FROM accounts ORDER BY kode ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Transaksi — MiceyStore</title>
    <style><?php include 'assets/style.css'; ?></style>
</head>
<body>

<nav class="topnav">
    <a href="index.php" class="topnav-brand">🌊 MiceyStore Finance</a>
    <div class="topnav-links">
        <a href="add.php" class="btn btn-primary btn-sm">＋ Input</a>
        <a href="list.php" class="btn btn-ghost btn-sm">🫧 List</a>
    </div>
</nav>

<div class="page-wrap">
    <div class="page-header">
        <div>
            <h1>Input Transaksi 🐚</h1>
            <p>Catat transaksi baru ke dalam sistem</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Detail Transaksi</div>
            <div class="card-subtitle">Field bertanda <span style="color:var(--danger)">*</span> wajib diisi</div>
        </div>
        <div class="card-body">
            <form id="txForm" method="POST" action="process.php" novalidate>
                <div class="form-grid">

                    <div class="form-group">
                        <label class="form-label" for="tanggal">Tanggal <span class="req">*</span></label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal"
                               value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="tipe">Tipe Transaksi <span class="req">*</span></label>
                        <select class="form-control" id="tipe" name="tipe" required>
                            <option value="">— Pilih Tipe —</option>
                            <option value="INCOME">INCOME</option>
                            <option value="EXPENSE">EXPENSE</option>
                            <option value="TRANSFER">TRANSFER</option>
                            <option value="WITHDRAWAL">WITHDRAWAL</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label class="form-label" for="deskripsi">Deskripsi</label>
                        <input type="text" class="form-control" id="deskripsi" name="deskripsi"
                               placeholder="Keterangan singkat transaksi..." maxlength="255">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="masuk">Jumlah Masuk</label>
                        <input type="text" class="form-control" id="masuk" name="masuk"
                               placeholder="Rp 0" inputmode="numeric" autocomplete="off">
                        <span class="form-hint" id="hint-masuk"></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="keluar">Jumlah Keluar</label>
                        <input type="text" class="form-control" id="keluar" name="keluar"
                               placeholder="Rp 0" inputmode="numeric" autocomplete="off">
                        <span class="form-hint" id="hint-keluar"></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="admin">Biaya Admin</label>
                        <input type="text" class="form-control" id="admin" name="admin"
                               placeholder="Rp 0" inputmode="numeric" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="terlibat">Terlibat</label>
                        <select class="form-control" id="terlibat" name="terlibat">
                            <option value="">— Pilih —</option>
                            <option value="MIRAI PEDIA">MIRAI PEDIA</option>
                            <option value="PREFLIX">PREFLIX</option>
                            <option value="ERABIELLA">ERABIELLA</option>
                            <option value="KARENINA">KARENINA</option>
                            <option value="ALIAN">ALIAN</option>
                            <option value="STOK">STOK</option>
                            <option value="INTERNAL">INTERNAL</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="akun_masuk">Akun Masuk</label>
                        <select class="form-control" id="akun_masuk" name="akun_masuk">
                            <option value="">— Pilih Akun —</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= htmlspecialchars($a['kode']) ?>">
                                    <?= htmlspecialchars($a['kode']) ?> - <?= htmlspecialchars($a['nama']) ?>
                                    (<?= htmlspecialchars($a['owner']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="akun_keluar">Akun Keluar</label>
                        <select class="form-control" id="akun_keluar" name="akun_keluar">
                            <option value="">— Pilih Akun —</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= htmlspecialchars($a['kode']) ?>">
                                    <?= htmlspecialchars($a['kode']) ?> - <?= htmlspecialchars($a['nama']) ?>
                                    (<?= htmlspecialchars($a['owner']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <div class="divider" style="margin:20px 0;"></div>

                <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
                    <button type="button" class="btn btn-ghost" id="resetBtn">Reset</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Simpan Transaksi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    function el(id) { return document.getElementById(id); }

    function toRaw(val) {
        if (val === null || val === undefined) return '';
        return String(val).replace(/[^0-9]/g, '');
    }

    function formatRp(raw) {
        var num = parseInt(raw, 10);
        if (!raw || isNaN(num)) return '';
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
    }

    function attachRpFormat(id) {
        var input = el(id);
        if (!input) return;
        input.addEventListener('input', function () {
            var raw = toRaw(this.value);
            this.value = raw ? formatRp(raw) : '';
        });
        input.addEventListener('blur', function () {
            var raw = toRaw(this.value);
            this.value = raw ? formatRp(raw) : '';
        });
    }
    ['masuk', 'keluar', 'admin'].forEach(attachRpFormat);


    var tipeEl     = el('tipe');
    var masukEl    = el('masuk');
    var hintMasuk  = el('hint-masuk');
    var hintKeluar = el('hint-keluar');

    function applyTipeRules(tipe) {
        if (masukEl)    masukEl.disabled = false;
        if (hintMasuk)  hintMasuk.textContent = '';
        if (hintKeluar) hintKeluar.textContent = '';

        if (tipe === 'EXPENSE') {
            if (masukEl)   { masukEl.disabled = true; masukEl.value = ''; }
            if (hintMasuk) { hintMasuk.textContent = 'Dikosongkan otomatis untuk EXPENSE'; }
        }
    }
    if (tipeEl) {
        tipeEl.addEventListener('change', function () { applyTipeRules(this.value); });
    }


    function showToast(msg, type) {
        type = type || 'success';
        var wrap = el('toastWrap');
        if (!wrap) return;
        var icon = type === 'success' ? '✅' : '❌';
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML =
            '<span class="toast-icon">' + icon + '</span>' +
            '<span class="toast-msg">' + msg + '</span>' +
            '<button class="toast-close" onclick="this.parentElement.remove()">✕</button>';
        wrap.appendChild(toast);
        setTimeout(function () { dismissToast(toast); }, 4000);
    }
    function dismissToast(t) {
        if (!t || !t.parentElement) return;
        t.classList.add('hide');
        setTimeout(function () { if (t.parentElement) t.remove(); }, 320);
    }


    function doReset() {
        var form = el('txForm');
        if (form) form.reset();
        var tgl = el('tanggal');
        if (tgl) tgl.value = new Date().toISOString().split('T')[0];
        if (masukEl)    { masukEl.disabled = false; masukEl.value = ''; }
        if (hintMasuk)  hintMasuk.textContent = '';
        if (hintKeluar) hintKeluar.textContent = '';
    }
    var resetBtn = el('resetBtn');
    if (resetBtn) resetBtn.addEventListener('click', doReset);


    var form = el('txForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var tanggalEl = el('tanggal');
            var tipeSelEl = el('tipe');
            var masukInp  = el('masuk');
            var keluarInp = el('keluar');
            var adminInp  = el('admin');

            if (!tanggalEl || !tanggalEl.value) { showToast('Tanggal wajib diisi!', 'error'); return; }
            if (!tipeSelEl || !tipeSelEl.value)  { showToast('Pilih tipe transaksi dulu!', 'error'); return; }

            var btn = el('submitBtn');
            if (btn) { btn.disabled = true; btn.textContent = 'Menyimpan...'; }

            var fd = new FormData(form);
            fd.set('masuk',  toRaw(masukInp  ? masukInp.value  : ''));
            fd.set('keluar', toRaw(keluarInp ? keluarInp.value : ''));
            fd.set('admin',  toRaw(adminInp  ? adminInp.value  : ''));

            fetch('process.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) { showToast('Transaksi berhasil disimpan! 🎉', 'success'); doReset(); }
                    else { showToast(data.message || 'Terjadi kesalahan.', 'error'); }
                })
                .catch(function () { showToast('Gagal terhubung ke server.', 'error'); })
                .finally(function () {
                    if (btn) { btn.disabled = false; btn.textContent = 'Simpan Transaksi'; }
                });
        });
    }

    <?php if (isset($_GET['status']) && $_GET['status'] === 'ok'): ?>
    showToast('Transaksi berhasil disimpan!', 'success');
    <?php elseif (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
    showToast('Terjadi kesalahan saat menyimpan.', 'error');
    <?php endif; ?>

});
</script>
</body>
</html>