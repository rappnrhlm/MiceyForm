<?php
require 'config/db.php';
require 'functions.php';

$tipe_valid   = ['INCOME','EXPENSE','TRANSFER','WITHDRAWAL'];
$status_valid = ['PENDING','CONFIRMED'];

$filter_tipe   = trim($_GET['tipe']   ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_bulan  = trim($_GET['bulan']  ?? '');
$filter_dari   = trim($_GET['dari']   ?? '');
$filter_sampai = trim($_GET['sampai'] ?? '');

if (!in_array($filter_tipe,   $tipe_valid,   true)) $filter_tipe   = '';
if (!in_array($filter_status, $status_valid, true)) $filter_status = '';
if (!preg_match('/^\d{4}-\d{2}$/',       $filter_bulan))  $filter_bulan  = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_dari))   $filter_dari   = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_sampai)) $filter_sampai = '';

$where_parts = ['t.is_deleted = 0'];
$params=[]; $types='';
if($filter_tipe)   { $where_parts[]='t.tipe = ?';                          $params[]=$filter_tipe;   $types.='s'; }
if($filter_status) { $where_parts[]='t.status = ?';                        $params[]=$filter_status; $types.='s'; }
if($filter_bulan)  { $where_parts[]="DATE_FORMAT(t.tanggal,'%Y-%m') = ?"; $params[]=$filter_bulan;  $types.='s'; }
if($filter_dari)   { $where_parts[]='t.tanggal >= ?';                      $params[]=$filter_dari;   $types.='s'; }
if($filter_sampai) { $where_parts[]='t.tanggal <= ?';                      $params[]=$filter_sampai; $types.='s'; }

$where = 'WHERE '.implode(' AND ', $where_parts);
$sql   = "SELECT t.id, DATE_FORMAT(t.tanggal,'%d/%m/%Y') AS tgl,
                 t.tipe, t.deskripsi, t.masuk, t.keluar, t.admin,
                 t.akun_masuk, t.akun_keluar, t.terlibat, t.status
          FROM transactions t $where ORDER BY t.tanggal DESC, t.id DESC";

$stmt=$conn->prepare($sql);
if($params) $stmt->bind_param($types,...$params);
$stmt->execute();
$res=$stmt->get_result();

$rows=[]; $tot_masuk=0; $tot_keluar=0; $tot_pending=0;
while($row=$res->fetch_assoc()){
  $rows[]=$row;
  if($row['status']==='CONFIRMED'){ 
      $tot_masuk  += (int)$row['masuk'];
      $tot_keluar += (int)$row['keluar'] + (int)$row['admin'];
  }
  if($row['status']==='PENDING')     $tot_pending++;
}
$stmt->close();

$net = $tot_masuk - $tot_keluar;

$gaji_raffa = $net > 0 ? (int)round($net * 0.60) : 0;
$gaji_restu = $net > 0 ? (int)round($net * 0.40) : 0;

$msg_map=[
  'confirmed'      =>['✅ Transaksi berhasil dikonfirmasi.','success'],
  'deleted'        =>['🗑️ Transaksi berhasil dihapus.','success'],
  'bulk_confirmed' =>['✅ '.($_GET['count']??0).' transaksi dikonfirmasi.','success'],
  'bulk_deleted'   =>['🗑️ '.($_GET['count']??0).' transaksi dihapus.','success'],
  'su_required'    =>['🔒 Aksi ini memerlukan Superuser aktif.','error'],
  'su_logout'      =>['👋 Superuser session diakhiri.','info'],
  'csrf_error'     =>['⛔ Token keamanan tidak valid.','error'],
  'no_selection'   =>['⚠️ Pilih minimal satu transaksi.','warning'],
  'error'          =>['❌ Terjadi kesalahan.','error'],
];
$mk=$_GET['msg']??'';
$msg_text=$msg_map[$mk][0]??'';
$msg_type=$msg_map[$mk][1]??'info';
$is_su=is_superuser();
$has_filter=$filter_tipe||$filter_status||$filter_bulan||$filter_dari||$filter_sampai;
$active_page='list';
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
      .app-main { padding: 0 !important; }
      .card { box-shadow: none; border: 1px solid #ccc; }
    }
  </style>
</head>
<body>
<div class="app-shell">
  <?php include 'assets/nav.php'; ?>

  <main class="app-main">

    <?php if($msg_text): ?>
    <div class="flash-msg flash-<?= $msg_type ?> no-print"><?= $msg_text ?></div>
    <?php endif; ?>

    <!-- PAGE HEAD -->
    <div class="page-head">
      <div class="page-head-left">
        <h1>Daftar Transaksi 🪼</h1>
        <p><?= count($rows) ?> transaksi tampil<?= $has_filter?' &middot; <span style="color:var(--cyan);font-weight:600;">Filter aktif</span>':'' ?></p>
      </div>
      <div class="page-head-right no-print">
        <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨 Print</button>
        <!-- Filter toggle button -->
        <button class="btn btn-ghost btn-sm" id="filterToggle" type="button">
          🔽 Filter<?= $has_filter?' <span style="background:var(--blue);color:#fff;border-radius:99px;padding:1px 6px;font-size:10px;margin-left:3px;">ON</span>':'' ?>
        </button>
        <?php if($is_su): ?>
        <form method="POST" action="process.php" style="display:contents;">
          <?= csrf_field() ?>
          <input type="hidden" name="action"        value="export">
          <input type="hidden" name="filter_tipe"   value="<?= htmlspecialchars($filter_tipe) ?>">
          <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filter_status) ?>">
          <input type="hidden" name="filter_bulan"  value="<?= htmlspecialchars($filter_bulan) ?>">
          <input type="hidden" name="filter_dari"   value="<?= htmlspecialchars($filter_dari) ?>">
          <input type="hidden" name="filter_sampai" value="<?= htmlspecialchars($filter_sampai) ?>">
          <button type="submit" class="btn btn-ghost btn-sm">⬇ CSV</button>
        </form>
        <?php else: ?>
        <button class="btn btn-ghost btn-sm" onclick="return requireSU()">⬇ CSV</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- ACTIVE FILTER CHIPS -->
    <?php if($has_filter): ?>
    <div class="filter-bar no-print">
      <div class="active-filters">
        <?php if($filter_tipe):   ?><span class="filter-chip"><?= $filter_tipe ?>   <button onclick="removeFilter('tipe')">×</button></span><?php endif; ?>
        <?php if($filter_status): ?><span class="filter-chip"><?= $filter_status ?> <button onclick="removeFilter('status')">×</button></span><?php endif; ?>
        <?php if($filter_bulan):  ?><span class="filter-chip"><?= $filter_bulan ?>  <button onclick="removeFilter('bulan')">×</button></span><?php endif; ?>
        <?php if($filter_dari):   ?><span class="filter-chip">dari: <?= $filter_dari ?>   <button onclick="removeFilter('dari')">×</button></span><?php endif; ?>
        <?php if($filter_sampai): ?><span class="filter-chip">s/d: <?= $filter_sampai ?> <button onclick="removeFilter('sampai')">×</button></span><?php endif; ?>
      </div>
      <a href="list.php" class="btn btn-ghost btn-sm">✕ Reset</a>
    </div>
    <?php endif; ?>

    <!-- FILTER DRAWER (hidden by default, toggle via button) -->
    <div class="filter-drawer no-print" id="filterDrawer" <?= $has_filter?'style="display:block;"':'' ?>>
      <form method="GET" action="list.php">
        <div class="filter-drawer-inner">
          <div class="filter-field">
            <label class="filter-label">Tipe</label>
            <select class="form-control" name="tipe">
              <option value="">Semua Tipe</option>
              <?php foreach($tipe_valid as $t): ?>
              <option value="<?=$t?>" <?=$filter_tipe===$t?'selected':''?>><?=$t?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-field">
            <label class="filter-label">Status</label>
            <select class="form-control" name="status">
              <option value="">Semua Status</option>
              <option value="PENDING"   <?=$filter_status==='PENDING'?'selected':''?>>PENDING</option>
              <option value="CONFIRMED" <?=$filter_status==='CONFIRMED'?'selected':''?>>CONFIRMED</option>
            </select>
          </div>
          <div class="filter-field">
            <label class="filter-label">Bulan–Tahun</label>
            <input type="month" class="form-control" name="bulan" value="<?=htmlspecialchars($filter_bulan)?>">
          </div>
          <div class="filter-row2">
            <div class="filter-field">
              <label class="filter-label">Dari Tanggal</label>
              <input type="date" class="form-control" name="dari" value="<?=htmlspecialchars($filter_dari)?>">
            </div>
            <div class="filter-field">
              <label class="filter-label">Sampai Tanggal</label>
              <input type="date" class="form-control" name="sampai" value="<?=htmlspecialchars($filter_sampai)?>">
            </div>
            <div class="filter-btns">
              <button type="submit" class="btn btn-primary">🔍 Terapkan</button>
              <a href="list.php" class="btn btn-ghost">✕</a>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-grid">
      <div class="stat-card stat-income">
        <div class="stat-icon">↑</div>
        <div class="stat-body">
          <div class="stat-label">Total Masuk</div>
          <div class="stat-val val-pos"><?= rp($tot_masuk) ?></div>
        </div>
      </div>
      <div class="stat-card stat-expense">
        <div class="stat-icon">↓</div>
        <div class="stat-body">
          <div class="stat-label">Total Keluar</div>
          <div class="stat-val val-neg"><?= rp($tot_keluar) ?></div>
        </div>
      </div>
      <div class="stat-card stat-net">
        <div class="stat-icon"><?= $net>=0?'≈':'!' ?></div>
        <div class="stat-body">
          <div class="stat-label">Net Selisih</div>
          <div class="stat-val <?= $net>=0?'val-pos':'val-neg' ?>">
            <?= ($net<0?'−':'').rp(abs($net)) ?>
          </div>
        </div>
      </div>
      <div class="stat-card stat-pending">
        <div class="stat-icon">⏳</div>
        <div class="stat-body">
          <div class="stat-label">Pending</div>
          <div class="stat-val val-warn"><?= $tot_pending ?></div>
        </div>
      </div>

      <!-- TAMBAHAN CARD GAJI -->
      <div class="stat-card stat-raffa">
        <div class="stat-icon">👤</div>
        <div class="stat-body">
          <div class="stat-label">Gaji Raffa (60%)</div>
          <div class="stat-val val-pos"><?= rp($gaji_raffa) ?></div>
        </div>
      </div>
      <div class="stat-card stat-restu">
        <div class="stat-icon">👤</div>
        <div class="stat-body">
          <div class="stat-label">Gaji Restu (40%)</div>
          <div class="stat-val val-pos"><?= rp($gaji_restu) ?></div>
        </div>
      </div>
    </div>

    <!-- BULK FORM -->
    <form method="POST" action="process.php" id="bulkForm">
      <?= csrf_field() ?>
      <input type="hidden" name="filter_tipe"   value="<?=htmlspecialchars($filter_tipe)?>">
      <input type="hidden" name="filter_status" value="<?=htmlspecialchars($filter_status)?>">
      <input type="hidden" name="filter_bulan"  value="<?=htmlspecialchars($filter_bulan)?>">
      <input type="hidden" name="filter_dari"   value="<?=htmlspecialchars($filter_dari)?>">
      <input type="hidden" name="filter_sampai" value="<?=htmlspecialchars($filter_sampai)?>">
      <input type="hidden" name="action" id="bulkAction" value="">

      <!-- Bulk toolbar -->
      <div class="bulk-bar no-print" id="bulkToolbar">
        <span class="bulk-count" id="bulkCount">0 dipilih</span>
        <div class="bulk-acts">
          <button type="button" class="btn btn-success btn-sm" onclick="submitBulk('bulk_confirm')">✅ Confirm</button>
          <button type="button" class="btn btn-danger  btn-sm" onclick="submitBulk('bulk_delete')">🗑 Hapus</button>
          <button type="button" class="btn btn-ghost   btn-sm" onclick="uncheckAll()">✕ Batal</button>
        </div>
      </div>

      <!-- Table -->
      <div class="card">
      <?php if(empty($rows)): ?>
        <div class="empty-state">
          <div class="es-icon">🫧</div>
          <p>Belum ada transaksi<?= $has_filter?' sesuai filter':'' ?>.</p>
          <a href="add.php" class="btn btn-primary" style="margin-top:14px;">➕ Tambah Transaksi</a>
        </div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="tx-table">
          <thead>
            <tr>
              <th class="c-cb no-print"><input type="checkbox" id="selAll" class="cb" title="Pilih Semua"></th>
              <th class="c-id">#</th>
              <th class="c-dt">Tanggal</th>
              <th class="c-ty">Tipe</th>
              <th class="c-ds">Deskripsi</th>
              <th class="c-num num">Masuk</th>
              <th class="c-num num">Keluar</th>
              <th class="c-num num">Admin</th>
              <th class="c-ac">Akun Masuk</th>
              <th class="c-ac">Akun Keluar</th>
              <th class="c-who">Terlibat</th>
              <th class="c-st">Status</th>
              <th class="c-act no-print">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r):
            $conf=$r['status']==='CONFIRMED'; ?>
          <tr class="<?=$conf?'tx-confirmed':''?>">
            <td class="c-cb no-print"><input type="checkbox" name="ids[]" value="<?=(int)$r['id']?>" class="cb row-check"></td>
            <td class="c-id"><span class="cell-id"><?=(int)$r['id']?></span></td>
            <td class="c-dt"><span class="cell-dt"><?=htmlspecialchars($r['tgl'])?></span></td>
            <td class="c-ty"><?=badge($r['tipe'])?></td>
            <td class="c-ds"><span class="cell-desc" title="<?=htmlspecialchars($r['deskripsi'])?>"><?=htmlspecialchars($r['deskripsi'])?></span></td>
            <td class="c-num">
              <?php if($r['masuk']>0):?><span class="money money-in">+<?=rp((int)$r['masuk'])?></span><?php
              else:?><span class="money-nil">—</span><?php endif;?>
            </td>
            <td class="c-num">
              <?php if($r['keluar']>0):?><span class="money money-out"><?=rp((int)$r['keluar'])?></span><?php
              else:?><span class="money-nil">—</span><?php endif;?>
            </td>
            <td class="c-num">
              <?php if($r['admin']>0):?><span class="money money-fee"><?=rp((int)$r['admin'])?></span><?php
              else:?><span class="money-nil">—</span><?php endif;?>
            </td>
            <td class="c-ac"><span class="acct-tag"><?=htmlspecialchars($r['akun_masuk'])?:'—'?></span></td>
            <td class="c-ac"><span class="acct-tag"><?=htmlspecialchars($r['akun_keluar'])?:'—'?></span></td>
            <td class="c-who"><?=htmlspecialchars($r['terlibat'])?:'—'?></td>
            <td class="c-st"><?=status_badge($r['status'])?></td>
            <td class="c-act no-print">
              <div class="act-wrap">
                <?php if(!$conf):?>
                <form method="POST" action="process.php">
                  <?=csrf_field()?>
                  <input type="hidden" name="action" value="confirm">
                  <input type="hidden" name="id"     value="<?=(int)$r['id']?>">
                  <button type="submit" class="act-btn act-ok <?=!$is_su?'act-locked':''?>" title="Konfirmasi"
                    <?=!$is_su?"onclick=\"return requireSU()\"":"onclick=\"return confirm('Konfirmasi?')\""?>>✓</button>
                </form>
                <?php endif;?>
                <form method="POST" action="process.php">
                  <?=csrf_field()?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?=(int)$r['id']?>">
                  <button type="submit" class="act-btn act-del <?=($conf&&!$is_su)?'act-locked':''?>" title="Hapus"
                    <?php if($conf&&!$is_su):?>onclick="return requireSU()"
                    <?php else:?>onclick="return confirm('Hapus transaksi ini?')"
                    <?php endif;?>>🗑</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach;?>
          </tbody>
        </table>
      </div>
      <?php endif;?>
      </div>
    </form>

  </main>
</div>

<script>
(function(){
  var ftBtn    = document.getElementById('filterToggle');
  var fDrawer  = document.getElementById('filterDrawer');
  ftBtn && ftBtn.addEventListener('click', function(){
    fDrawer.classList.toggle('open');
    if(fDrawer.style.display==='block'){
      fDrawer.style.display='none'; fDrawer.classList.remove('open');
    } else {
      fDrawer.style.display='block'; fDrawer.classList.add('open');
    }
  });
  window.removeFilter = function(key){
    var url=new URL(location.href);
    url.searchParams.delete(key);
    location.href=url.toString();
  };

  var selAll  = document.getElementById('selAll');
  var toolbar = document.getElementById('bulkToolbar');
  var countEl = document.getElementById('bulkCount');
  var actInp  = document.getElementById('bulkAction');

  function getChk(){ return document.querySelectorAll('.row-check:checked'); }
  function updateBar(){
    var n=getChk().length;
    toolbar&&toolbar.classList.toggle('active',n>0);
    countEl&&(countEl.textContent=n+' dipilih');
  }
  selAll&&selAll.addEventListener('change',function(){
    document.querySelectorAll('.row-check').forEach(function(cb){cb.checked=selAll.checked;});
    updateBar();
  });
  document.querySelectorAll('.row-check').forEach(function(cb){
    cb.addEventListener('change',function(){
      updateBar();
      if(selAll) selAll.checked=!document.querySelectorAll('.row-check:not(:checked)').length;
    });
  });
  window.uncheckAll=function(){
    document.querySelectorAll('.row-check').forEach(function(cb){cb.checked=false;});
    selAll&&(selAll.checked=false); updateBar();
  };
  window.submitBulk=function(act){
    var n=getChk().length;
    if(!n){alert('Pilih minimal satu transaksi.');return;}
    var msg=act==='bulk_delete'?'Hapus '+n+' transaksi?':'Konfirmasi '+n+' transaksi?';
    if(!confirm(msg))return;
    actInp&&(actInp.value=act);
    document.getElementById('bulkForm').submit();
  };

  <?php if($msg_text):?>
  (function(){
    var w=document.createElement('div'); w.className='toast-wrap';
    w.style.cssText='position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
    var t=document.createElement('div');
    t.className='toast toast-<?=$msg_type==='success'?'success':'error'?>';
    t.innerHTML='<span class="toast-msg"><?=addslashes($msg_text)?></span><button class="toast-close" onclick="this.parentElement.parentElement.remove()">✕</button>';
    w.appendChild(t); document.body.appendChild(w);
    setTimeout(function(){t.classList.add('hide');setTimeout(function(){w.remove();},280);},4000);
  })();
  <?php endif;?>
})();
</script>
</body>
</html>
