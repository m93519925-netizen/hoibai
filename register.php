<?php
require_once 'config/supabase.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $username = trim($_POST['username'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu tối thiểu 6 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (strlen($username) < 2 || strlen($username) > 30) {
        $error = 'Tên hiển thị từ 2 đến 30 ký tự.';
    } else {
        $r = auth_sign_up($email, $password);
        if ($r['status'] === 200) {
            $uid = $r['data']['user']['id'] ?? null;
            if ($uid) {
                db_update('profiles', "id=eq.{$uid}", ['username' => $username]);
            }
            $success = '✅ Đăng ký thành công! Kiểm tra email để xác nhận tài khoản, sau đó đăng nhập.';
        } else {
            $msg = $r['data']['msg'] ?? ($r['data']['error_description'] ?? 'Đăng ký thất bại.');
            $error = h($msg);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng ký – HỏiBài</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="auth-container">
  <div class="auth-box">
    <h1>🎓 HỏiBài</h1>
    <p class="subtitle">Hỏi đáp bài tập K1–12</p>

    <div class="points-banner">
      🎁 Đăng ký ngay nhận <strong>60 điểm</strong> miễn phí!<br>
      <small>Dùng điểm để đặt câu hỏi, trả lời để kiếm thêm điểm.</small>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label>Tên hiển thị</label>
        <input type="text" name="username"
               value="<?= h($_POST['username'] ?? '') ?>"
               placeholder="Nguyễn Văn A" required maxlength="30">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email"
               value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required>
      </div>
      <div class="form-group">
        <label>Mật khẩu</label>
        <input type="password" name="password"
               placeholder="Tối thiểu 6 ký tự" required>
      </div>
      <div class="form-group">
        <label>Xác nhận mật khẩu</label>
        <input type="password" name="confirm"
               placeholder="Nhập lại mật khẩu" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full">
        Tạo tài khoản
      </button>
    </form>

    <p class="auth-switch">
      Đã có tài khoản? <a href="/login.php">Đăng nhập</a>
    </p>
  </div>
</div>
</body>
</html>
