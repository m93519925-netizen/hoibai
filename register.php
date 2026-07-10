<?php
require_once 'config/supabase.php';
require_once 'includes/head.php';
include_head('Đăng ký');
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    rate_limit('register', 3);
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';
    $username = trim($_POST['username'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu tối thiểu 6 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (mb_strlen($username) < 2 || mb_strlen($username) > 30) {
        $error = 'Tên hiển thị từ 2–30 ký tự.';
    } else {
        $r = auth_sign_up($email, $password);
        if ($r['status'] === 200) {
            $uid = $r['data']['user']['id'] ?? null;
            if ($uid) db_update('profiles', "id=eq.{$uid}", ['username' => $username]);
            $success = 'Đăng ký thành công! Kiểm tra email để xác nhận tài khoản.';
        } else {
            $error = h($r['data']['msg'] ?? ($r['data']['error_description'] ?? 'Đăng ký thất bại.'));
        }
    }
}
?>
<div class="auth-container">
  <div class="auth-box">
    <div class="auth-logo">
      <i class="fa-solid fa-graduation-cap"></i>
    </div>
    <h1>Tạo tài khoản</h1>

    <div class="points-banner">
      <i class="fa-solid fa-gift"></i>
      Đăng ký nhận ngay <strong>60 điểm</strong> miễn phí!
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i> <?= h($error) ?>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i> <?= $success ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label><i class="fa-solid fa-user"></i> Tên hiển thị</label>
        <input type="text" name="username"
               value="<?= h($_POST['username'] ?? '') ?>"
               placeholder="Nguyễn Văn A" required maxlength="30">
      </div>

      <div class="form-group">
        <label><i class="fa-solid fa-envelope"></i> Email</label>
        <input type="email" name="email"
               value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required>
      </div>

      <div class="form-group">
        <label><i class="fa-solid fa-lock"></i> Mật khẩu</label>
        <div class="input-wrap">
          <input type="password" name="password" id="pwd"
                 placeholder="Tối thiểu 6 ký tự" required>
          <button type="button" class="toggle-pwd" onclick="togglePwd()">
            <i class="fa-solid fa-eye" id="eye-icon"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label><i class="fa-solid fa-lock"></i> Xác nhận mật khẩu</label>
        <input type="password" name="confirm" placeholder="Nhập lại mật khẩu" required>
      </div>

      <button type="submit" class="btn btn-primary btn-full">
        <i class="fa-solid fa-user-plus"></i> Tạo tài khoản
      </button>
    </form>

    <p class="auth-switch">
      Đã có tài khoản? <a href="/login.php">Đăng nhập</a>
    </p>
  </div>
</div>
<script>
function togglePwd() {
  const pwd  = document.getElementById('pwd');
  const icon = document.getElementById('eye-icon');
  pwd.type   = pwd.type === 'password' ? 'text' : 'password';
  icon.className = pwd.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
}
</script>
<?php require_once 'includes/footer.php'; ?>
