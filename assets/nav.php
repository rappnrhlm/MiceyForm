<?php
// nav.php — Shared sidebar + mobile topbar
// Include this in every page AFTER <body>
// $active_page should be set before including: 'index', 'add', 'list'
$active_page = $active_page ?? '';
$is_su = function_exists('is_superuser') ? is_superuser() : false;
?>
<!-- OVERLAY for mobile -->
<div class="nav-overlay" id="navOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <a href="index.php">🌊 MiceyStore</a>
    <span class="logo-badge">Finance</span>
  </div>

  <?php if ($is_su): ?>
  <div class="su-strip">
    <span>🔓 Superuser</span>
    <span class="su-timer" id="suTimer"><?= SU_TIMEOUT ?>s</span>
  </div>
  <?php else: ?>
  <button class="btn btn-ghost btn-sm su-unlock-btn" id="unlockBtn" type="button">🔒 Unlock Superuser</button>
  <?php endif; ?>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Menu</div>
    <a href="index.php" class="nav-link <?= $active_page==='index'?'active':'' ?>">
      <span class="nav-icon">🏠</span> Beranda
    </a>
    <a href="add.php" class="nav-link <?= $active_page==='add'?'active':'' ?>">
      <span class="nav-icon">➕</span> Input Transaksi
    </a>
    <a href="list.php" class="nav-link <?= $active_page==='list'?'active':'' ?>">
      <span class="nav-icon">🪼</span> Daftar Transaksi
    </a>

    <?php if ($is_su): ?>
    <div class="nav-section-label" style="margin-top:8px;">Superuser</div>
    <form method="POST" action="process.php" style="margin:0 0 2px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="su_logout">
      <button type="submit" class="nav-link" style="width:100%;background:none;border:none;cursor:pointer;font-family:inherit;text-align:left;">
        <span class="nav-icon">🔒</span> Lock Session
      </button>
    </form>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    🐳 MiceyStore Finance Panel<br>internal use only
  </div>
</aside>

<!-- MOBILE TOPBAR -->
<header class="topbar" id="topbar">
  <a href="index.php" class="topbar-brand">🌊 MiceyStore</a>
  <div class="topbar-right">
    <?php if ($is_su): ?>
      <span style="font-size:11px;font-weight:600;color:var(--green-c);">🔓 <span id="suTimerMobile"><?= SU_TIMEOUT ?></span>s</span>
    <?php endif; ?>
    <button class="hamburger" id="hamburger" type="button" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<!-- PIN MODAL -->
<div class="modal-ov" id="pinModal">
  <div class="modal-box">
    <div class="modal-head">
      <span>🔐 Unlock Superuser</span>
      <button class="modal-close" id="pinClose" type="button">✕</button>
    </div>
    <div class="modal-body">
      <p class="modal-desc">Masukkan PIN 6 digit (berlaku <?= defined('SU_TIMEOUT') ? SU_TIMEOUT/60 : 10 ?> menit).</p>
      <input type="password" class="form-control pin-input" id="pinInput"
             maxlength="6" inputmode="numeric" placeholder="● ● ● ● ● ●" autocomplete="off">
      <div id="pinError" class="pin-error"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost btn-sm" id="pinCancel">Batal</button>
      <button class="btn btn-primary btn-sm" id="pinSubmit">Unlock</button>
    </div>
  </div>
</div>

<script>
(function(){
  /* ─ NAV TOGGLE ─ */
  var sb  = document.getElementById('sidebar');
  var hbg = document.getElementById('hamburger');
  var ov  = document.getElementById('navOverlay');
  function openNav()  { sb.classList.add('open'); hbg&&hbg.classList.add('open'); ov.classList.add('open'); document.body.style.overflow='hidden'; }
  function closeNav() { sb.classList.remove('open'); hbg&&hbg.classList.remove('open'); ov.classList.remove('open'); document.body.style.overflow=''; }
  hbg && hbg.addEventListener('click', function(){ sb.classList.contains('open')?closeNav():openNav(); });
  ov  && ov.addEventListener('click', closeNav);

  /* ─ SU TIMER (both desktop + mobile) ─ */
  <?php if ($is_su): ?>
  var els = [document.getElementById('suTimer'), document.getElementById('suTimerMobile')].filter(Boolean);
  if (els.length) {
    var rem = parseInt(els[0].textContent, 10) || <?= SU_TIMEOUT ?>;
    var iv = setInterval(function(){
      if (--rem <= 0) { clearInterval(iv); location.reload(); }
      els.forEach(function(e){ e.textContent = rem + 's'; });
      if (rem <= 60) els.forEach(function(e){ e.style.color='var(--red)'; });
    }, 1000);
  }
  <?php endif; ?>

  /* ─ PIN MODAL ─ */
  var modal   = document.getElementById('pinModal');
  var pinInp  = document.getElementById('pinInput');
  var pinErr  = document.getElementById('pinError');
  var pinBtn  = document.getElementById('pinSubmit');
  function openModal()  { modal.classList.add('open'); setTimeout(function(){ pinInp&&pinInp.focus(); },80); }
  function closeModal() { modal.classList.remove('open'); pinInp&&(pinInp.value=''); pinErr&&(pinErr.textContent=''); }
  var unBtn = document.getElementById('unlockBtn');
  if (unBtn) unBtn.addEventListener('click', openModal);
  document.getElementById('pinClose')  && document.getElementById('pinClose').addEventListener('click', closeModal);
  document.getElementById('pinCancel') && document.getElementById('pinCancel').addEventListener('click', closeModal);
  modal && modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });

  function doUnlock(){
    var pin = pinInp?pinInp.value.trim():'';
    if(!pin){ pinErr&&(pinErr.textContent='Masukkan PIN.'); return; }
    if(pinBtn){ pinBtn.disabled=true; pinBtn.textContent='...'; }
    pinErr&&(pinErr.textContent='');
    var fd=new FormData(); fd.append('action','su_login'); fd.append('pin',pin);
    fetch('process.php',{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(d){
        if(d.success){location.reload();}
        else{pinErr&&(pinErr.textContent=d.message);pinInp&&(pinInp.value='',pinInp.focus());}
      })
      .catch(function(){pinErr&&(pinErr.textContent='Gagal terhubung.');})
      .finally(function(){if(pinBtn){pinBtn.disabled=false;pinBtn.textContent='Unlock';}});
  }
  pinBtn && pinBtn.addEventListener('click', doUnlock);
  pinInp && pinInp.addEventListener('keydown', function(e){if(e.key==='Enter')doUnlock();});
  window.requireSU = function(){ openModal(); return false; };
})();
</script>