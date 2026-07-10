<?php
require_once 'config/supabase.php';
require_once 'includes/head.php';
include_head('Đăng nhập');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    rate_limit('login', 10);
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } else {
        $r = auth_sign_in($email, $password);
        if ($r['status'] === 200 && !empty($r['data']['access_token'])) {
            session_regenerate_id(true);
            $_SESSION['access_token']  = $r['data']['access_token'];
            $_SESSION['refresh_token'] = $r['data']['refresh_token'];
            $_SESSION['user_id']       = $r['data']['user']['id'];
            header('Location: /index.php'); exit;
        } else {
            $error = 'Email hoặc mật khẩu không đúng.';
        }
    }
}
?>
<div class="auth-container">
  <div class="auth-box">
    <div class="auth-logo">
      <i class="fa-solid fa-graduation-cap"></i>
    </div>
    <h1>Chào mừng trở lại!</h1>
    <p class="subtitle">Đăng nhập vào HỏiBài</p>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i> <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label><i class="fa-solid fa-envelope"></i> Email</label>
        <input type="email" name="email"
               value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required autofocus>
      </div>

      <div class="form-group">
        <label><i class="fa-solid fa-lock"></i> Mật khẩu</label>
        <div class="input-wrap">
          <input type="password" name="password" id="pwd"
                 placeholder="••••••••" required>
          <button type="button" class="toggle-pwd" onclick="togglePwd()">
            <i class="fa-solid fa-eye" id="eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full">
        <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
      </button>
    </form>

    <p class="auth-switch">
      Chưa có tài khoản?
      <a href="/register.php">Đăng ký nhận 60 <i class="fa-solid fa-star fa-xs"></i></a>
    </p>
  </div>
</div>

<script>
function togglePwd() {
  const pwd  = document.getElementById('pwd');
  const icon = document.getElementById('eye-icon');
  if (pwd.type === 'password') {
    pwd.type = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    pwd.type = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}
</script>
<?php require_once 'includes/footer.php'; ?>
