<?php
require 'config/db.php';
require 'functions.php';

$accounts = [];
$stmt = $conn->prepare("SELECT kode, nama, owner FROM accounts ORDER BY kode ASC");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $accounts[] = $row;
$stmt->close();

$active_page = 'add';
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
<div class="app-shell">
  <?php include 'assets/nav.php'; ?>

  <main class="app-main">
    <div class="page-head">
      <div class="page-head-left">
        <h1>Input Transaksi 🐚</h1>
        <p>Catat transaksi baru ke dalam sistem</p>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Detail Transaksi</div>
          <div class="card-subtitle">Field bertanda <span style="color:var(--red)">*</span> wajib diisi</div>
        </div>
      </div>
      <div class="card-body">
        <form id="txForm" method="POST" action="process.php" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <div class="form-grid">

            <div class="form-group">
              <label class="form-label" for="tanggal">Tanggal <span class="req">*</span></label>
              <input type="date" class="form-control" id="tanggal" name="tanggal"
                     value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="tipe">Tipe <span class="req">*</span></label>
              <select class="form-control" id="tipe" name="tipe" required>
                <option value="">— Pilih Tipe —</option>
                <option value="INCOME">INCOME</option>
                <option value="EXPENSE">EXPENSE</option>
                <option value="TRANSFER">TRANSFER</option>
                <option value="WITHDRAWAL">WITHDRAWAL</option>
              </select>
            </div>

            <div class="form-group span2">
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
                <option>MIRAI PEDIA</option><option>PREFLIX</option>
                <option>ERABIELLA</option><option>KARENINA</option>
                <option>ALIAN</option><option>STOK</option><option>INTERNAL</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="akun_masuk">Akun Masuk</label>
              <select class="form-control" id="akun_masuk" name="akun_masuk">
                <option value="">— Pilih Akun —</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= htmlspecialchars($a['kode']) ?>">
                  <?= htmlspecialchars($a['kode']) ?> - <?= htmlspecialchars($a['nama']) ?> (<?= htmlspecialchars($a['owner']) ?>)
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
                  <?= htmlspecialchars($a['kode']) ?> - <?= htmlspecialchars($a['nama']) ?> (<?= htmlspecialchars($a['owner']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>

          </div>

          <div class="divider" style="margin:18px 0;"></div>

          <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
            <button type="button" class="btn btn-ghost" id="resetBtn">↺ Reset</button>
            <button type="submit" class="btn btn-primary" id="submitBtn">💾 Simpan Transaksi</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  function el(id){ return document.getElementById(id); }
  function toRaw(v){ return String(v||'').replace(/[^0-9]/g,''); }
  function fmtRp(raw){
    var n=parseInt(raw,10);
    return (!raw||isNaN(n))?'':'Rp '+new Intl.NumberFormat('id-ID').format(n);
  }
  ['masuk','keluar','admin'].forEach(function(id){
    var inp=el(id); if(!inp)return;
    inp.addEventListener('input',function(){ var r=toRaw(this.value); this.value=r?fmtRp(r):''; });
    inp.addEventListener('blur', function(){ var r=toRaw(this.value); this.value=r?fmtRp(r):''; });
  });

  var tipeEl=el('tipe'), masukEl=el('masuk'), hintMasuk=el('hint-masuk');
  if(tipeEl) tipeEl.addEventListener('change',function(){
    if(this.value==='EXPENSE'){
      masukEl&&(masukEl.disabled=true,masukEl.value='');
      hintMasuk&&(hintMasuk.textContent='Dikosongkan untuk EXPENSE');
    } else {
      masukEl&&(masukEl.disabled=false);
      hintMasuk&&(hintMasuk.textContent='');
    }
  });

  function showToast(msg,type){
    var wrap=el('toastWrap'); if(!wrap)return;
    var t=document.createElement('div');
    t.className='toast toast-'+(type||'success');
    t.innerHTML='<span class="toast-msg">'+msg+'</span><button class="toast-close" onclick="this.parentElement.remove()">✕</button>';
    wrap.appendChild(t);
    setTimeout(function(){ t.classList.add('hide'); setTimeout(function(){t.remove();},280); },4000);
  }

  function doReset(){
    var f=el('txForm'); f&&f.reset();
    var tgl=el('tanggal'); if(tgl)tgl.value=new Date().toISOString().split('T')[0];
    masukEl&&(masukEl.disabled=false,masukEl.value='');
    hintMasuk&&(hintMasuk.textContent='');
  }
  var rBtn=el('resetBtn'); rBtn&&rBtn.addEventListener('click',doReset);

  var form=el('txForm');
  form&&form.addEventListener('submit',function(e){
    e.preventDefault();
    if(!el('tanggal').value){showToast('Tanggal wajib diisi!','error');return;}
    if(!el('tipe').value){showToast('Pilih tipe transaksi!','error');return;}
    var btn=el('submitBtn'); if(btn){btn.disabled=true;btn.textContent='Menyimpan...';}
    var fd=new FormData(form);
    ['masuk','keluar','admin'].forEach(function(k){ fd.set(k,toRaw(fd.get(k))); });
    fetch('process.php',{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(d){
        if(d.success){showToast('Transaksi berhasil disimpan! 🎉','success');doReset();}
        else{showToast(d.message||'Terjadi kesalahan.','error');}
      })
      .catch(function(){showToast('Gagal terhubung.','error');})
      .finally(function(){if(btn){btn.disabled=false;btn.textContent='💾 Simpan Transaksi';}});
  });
});
</script>
</body>
</html>