<?php
$current = basename($_SERVER['PHP_SELF']);
$user    = get_supabase_user();
?>
<!-- Mobile Bottom Nav -->
<nav class="mobile-nav">
  <a href="/index.php" class="<?= $current==='index.php'?'active':'' ?>">
    <i class="fa-solid fa-house"></i>
    <span>Trang chủ</span>
  </a>
  <a href="/ask.php" class="<?= $current==='ask.php'?'active':'' ?>">
    <i class="fa-solid fa-pen-to-square"></i>
    <span>Hỏi bài</span>
  </a>
  <?php if ($user): ?>
  <a href="/logout.php">
    <i class="fa-solid fa-right-from-bracket"></i>
    <span>Đăng xuất</span>
  </a>
  <?php else: ?>
  <a href="/login.php" class="<?= $current==='login.php'?'active':'' ?>">
    <i class="fa-solid fa-user"></i>
    <span>Đăng nhập</span>
  </a>
  <?php endif; ?>
</nav>

<footer class="site-footer">
  <p>
    <i class="fa-solid fa-graduation-cap"></i>
    HỏiBài – Nền tảng hỏi đáp bài tập K1–12
    &copy; <?= date('Y') ?>
  </p>
</footer>
</body>
</html>
