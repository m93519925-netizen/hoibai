<?php
$current = basename($_SERVER['PHP_SELF']);
$user    = get_supabase_user();
$profile = ($user && isset($user['id'])) ? get_profile($user['id']) : null;
?>
<nav class="navbar">
  <a href="/index.php" class="logo">
    <i class="fa-solid fa-graduation-cap"></i>
    HỏiBài
  </a>

  <form class="search-form" method="GET" action="/index.php">
    <div class="search-wrap">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="text" name="q"
             value="<?= h($_GET['q'] ?? '') ?>"
             placeholder="Tìm câu hỏi...">
    </div>
  </form>

  <div class="nav-actions">
    <?php if ($user && $profile): ?>
      <div class="points-badge">
        <i class="fa-solid fa-star"></i>
        <?= (int)$profile['points'] ?> điểm
      </div>
      <a href="/ask.php" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i>
        <span class="hide-xs">Đặt câu hỏi</span>
      </a>
      <div class="user-dropdown">
        <button class="user-btn" onclick="toggleDropdown()">
          <i class="fa-solid fa-circle-user"></i>
          <span class="hide-xs"><?= h($profile['username']) ?></span>
          <i class="fa-solid fa-chevron-down fa-xs"></i>
        </button>
        <div class="dropdown-menu" id="dropdown-menu">
          <a href="/logout.php" class="dropdown-item">
            <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
          </a>
        </div>
      </div>
    <?php else: ?>
      <a href="/login.php" class="btn btn-ghost">
        <i class="fa-solid fa-right-to-bracket"></i>
        <span class="hide-xs">Đăng nhập</span>
      </a>
      <a href="/register.php" class="btn btn-primary">
        <i class="fa-solid fa-user-plus"></i>
        <span class="hide-xs">Đăng ký</span>
      </a>
    <?php endif; ?>
  </div>
</nav>
<script>
function toggleDropdown() {
  document.getElementById('dropdown-menu').classList.toggle('show');
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('.user-dropdown')) {
    document.getElementById('dropdown-menu')?.classList.remove('show');
  }
});
</script>
