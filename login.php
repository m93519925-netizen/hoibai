<?php
require_once 'config/supabase.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } else {
        $r = auth_sign_in($email, $password);
        if ($r['status'] === 200 && !empty($r['data']['access_token'])) {
            $_SESSION['access_token']  = $r['data']['access_token'];
            $_SESSION['refresh_token'] = $r['data']['refresh_token'];
            $_SESSION['user_id']       = $r['data']['user']['id'];
            header('Location: /index.php');
            exit;
        } else {
            $error = 'Email hoặc mật khẩu không đúng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập – HỏiBài</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="auth-container">
  <div class="auth-box">
    <h1>🎓 HỏiBài</h1>
    <p class="subtitle">Hỏi đáp bài tập K1–12</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email"
               value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required autofocus>
      </div>
      <div class="form-group">
        <label>Mật khẩu</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Đăng nhập</button>
    </form>

    <p class="auth-switch">
      Chưa có tài khoản? <a href="/register.php">Đăng ký nhận 60 điểm!</a>
    </p>
  </div>
</div>
</body>
</html>
