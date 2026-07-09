<?php
require_once 'config/supabase.php';

$user    = get_current_user();
$profile = $user ? get_profile($user['id']) : null;

$id = trim($_GET['id'] ?? '');
if (!$id || !preg_match('/^[0-9a-f-]{36}$/i', $id)) {
    header('Location: /index.php'); exit;
}

db_rpc('increment_views', ['question_id' => $id]);

$qr = db_select('questions', "id=eq.{$id}&select=*,profiles(username)");
$q  = $qr['data'][0] ?? null;
if (!$q) { http_response_code(404); die('Không tìm thấy câu hỏi.'); }

$answer_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    if (!$user) { header('Location: /login.php'); exit; }

    if ($_POST['action'] === 'answer') {
        $body = trim($_POST['body'] ?? '');
        if (mb_strlen($body) < 5) {
            $answer_error = 'Câu trả lời quá ngắn.';
        } else {
            $image_url = null;
            if (!empty($_FILES['image']['tmp_name'])) {
                if ($_FILES['image']['size'] > 5*1024*1024) {
                    $answer_error = 'Ảnh tối đa 5MB.';
                } else {
                    $image_url = storage_upload($_FILES['image']['tmp_name'], $_FILES['image']['name'], 'answers');
                }
            }
            if (!$answer_error) {
                db_insert('answers', [
                    'question_id' => $id,
                    'user_id'     => $user['id'],
                    'body'        => $body,
                    'image_url'   => $image_url,
                ]);
                add_points($user['id'], 5, 'answer_posted', $id);
                header("Location: /question.php?id={$id}#answers"); exit;
            }
        }
    }

    if ($_POST['action'] === 'accept' && $user['id'] === $q['user_id']) {
        $aid = $_POST['answer_id'] ?? '';
        if (preg_match('/^[0-9a-f-]{36}$/i', $aid)) {
            db_update('answers', "question_id=eq.{$id}", ['is_accepted' => false]);
            db_update('answers', "id=eq.{$aid}", ['is_accepted' => true]);
            db_update('questions', "id=eq.{$id}", ['status' => 'answered']);
            $ar = db_select('answers', "id=eq.{$aid}&select=user_id");
            $owner = $ar['data'][0]['user_id'] ?? null;
            if ($owner) add_points($owner, (int)$q['points_cost'], 'answer_accepted', $aid);
            header("Location: /question.php?id={$id}#answers"); exit;
        }
    }

    if ($_POST['action'] === 'delete_question' && $user['id'] === $q['user_id']) {
        db_delete('questions', "id=eq.{$id}");
        header('Location: /index.php'); exit;
    }

    if ($_POST['action'] === 'delete_answer') {
        $aid = $_POST['answer_id'] ?? '';
        if (preg_match('/^[0-9a-f-]{36}$/i', $aid)) {
            $ar = db_select('answers', "id=eq.{$aid}&select=user_id");
            if (($ar['data'][0]['user_id'] ?? '') === $user['id']) {
                db_delete('answers', "id=eq.{$aid}");
            }
        }
        header("Location: /question.php?id={$id}#answers"); exit;
    }
}

$ar      = db_select('answers', "question_id=eq.{$id}&select=*,profiles(username)&order=is_accepted.desc,created_at.asc");
$answers = $ar['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($q['title']) ?> – HỏiBài</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<nav class="navbar">
  <a href="/index.php" class="logo">🎓 HỏiBài</a>
  <div class="nav-actions">
    <?php if ($user && $profile): ?>
      <span class="points-badge">⭐ <?= (int)$profile['points'] ?> điểm</span>
      <a href="/ask.php" class="btn btn-primary">+ Đặt câu hỏi</a>
      <span class="nav-username">👤 <?= h($profile['username']) ?></span>
      <a href="/logout.php" class="btn btn-ghost">Đăng xuất</a>
    <?php else: ?>
      <a href="/login.php" class="btn btn-ghost">Đăng nhập</a>
      <a href="/register.php" class="btn btn-outline">Đăng ký</a>
    <?php endif; ?>
  </div>
</nav>

<div class="container single-col">
  <div class="q-detail-card">
    <div class="q-detail-meta">
      <span class="grade-badge"><?= h($q['grade_group']) ?></span>
      <span class="subject-badge"><?= h($q['subject']) ?></span>
      <?php if ($q['status'] === 'answered'): ?>
        <span class="answered-badge">✅ Đã được giải</span>
      <?php endif; ?>
      <span class="reward-badge">🏆 Thưởng <?= (int)$q['points_cost'] ?>⭐</span>
    </div>
    <h1><?= h($q['title']) ?></h1>
    <p class="q-detail-info">
      👤 <?= h($q['profiles']['username'] ?? 'Ẩn danh') ?> •
      <?= time_ago($q['created_at']) ?> •
      👁️ <?= (int)$q['views'] ?> lượt xem
    </p>
    <?php if ($q['body']): ?>
      <div class="q-detail-body"><?= nl2br_safe($q['body']) ?></div>
    <?php endif; ?>
    <?php if ($q['image_url']): ?>
      <div class="q-image-wrap">
        <img src="<?= h($q['image_url']) ?>" class="q-image" alt="Ảnh đề bài"
             onclick="openLightbox(this.src)" loading="lazy">
      </div>
    <?php endif; ?>
    <?php if ($user && $user['id'] === $q['user_id']): ?>
      <div class="owner-actions">
        <form method="POST" onsubmit="return confirm('Xóa câu hỏi này?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete_question">
          <button type="submit" class="btn btn-danger btn-sm">🗑️ Xóa câu hỏi</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div id="answers">
    <h3 class="answers-heading"><?= count($answers) ?> câu trả lời</h3>
    <?php if (empty($answers)): ?>
      <div class="empty-state small">Chưa có câu trả lời. Hãy là người đầu tiên! 🌟</div>
    <?php endif; ?>
    <?php foreach ($answers as $a): ?>
      <div class="answer-card <?= $a['is_accepted'] ? 'accepted' : '' ?>" id="a-<?= h($a['id']) ?>">
        <?php if ($a['is_accepted']): ?>
          <div class="accepted-label">✅ Câu trả lời được chấp nhận – Nhận <?= (int)$q['points_cost'] ?>⭐</div>
        <?php endif; ?>
        <div class="answer-body"><?= nl2br_safe($a['body']) ?></div>
        <?php if ($a['image_url']): ?>
          <div class="a-image-wrap">
            <img src="<?= h($a['image_url']) ?>" class="a-image" alt="Ảnh trả lời"
                 onclick="openLightbox(this.src)" loading="lazy">
          </div>
        <?php endif; ?>
        <div class="answer-footer">
          <span class="answer-meta">
            👤 <?= h($a['profiles']['username'] ?? 'Ẩn danh') ?> • <?= time_ago($a['created_at']) ?>
          </span>
          <div class="answer-actions">
            <?php if ($user && $user['id'] === $q['user_id'] && !$a['is_accepted']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="answer_id" value="<?= h($a['id']) ?>">
                <button type="submit" class="btn btn-accept btn-sm">✅ Chấp nhận +<?= (int)$q['points_cost'] ?>⭐</button>
              </form>
            <?php endif; ?>
            <?php if ($user && $user['id'] === $a['user_id']): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Xóa?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_answer">
                <input type="hidden" name="answer_id" value="<?= h($a['id']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($user): ?>
    <div class="form-card">
      <h3>✍️ Viết câu trả lời
        <small class="text-muted">(+5⭐ khi gửi, +<?= (int)$q['points_cost'] ?>⭐ nếu được chấp nhận)</small>
      </h3>
      <?php if ($answer_error): ?>
        <div class="alert alert-error"><?= h($answer_error) ?></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="answer">
        <div class="form-group">
          <textarea name="body" rows="6"
                    placeholder="Viết lời giải chi tiết..." required><?= h($_POST['body'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>📷 Ảnh minh họa (tùy chọn)</label>
          <div class="upload-zone" id="a-upload-zone">
            <input type="file" name="image" id="a-image-input" accept="image/*" hidden>
            <div id="a-upload-placeholder"><span>📎 Click để chọn ảnh</span></div>
            <img id="a-image-preview" class="img-preview hidden" alt="Preview">
            <button type="button" id="a-remove-img" class="btn btn-ghost btn-sm hidden">✕ Xóa</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Gửi câu trả lời (+5⭐)</button>
      </form>
    </div>
  <?php else: ?>
    <div class="login-cta">
      <p>Đăng nhập để trả lời và nhận điểm thưởng!</p>
      <a href="/login.php" class="btn btn-primary">Đăng nhập</a>
      <a href="/register.php" class="btn btn-outline">Đăng ký nhận 60⭐</a>
    </div>
  <?php endif; ?>
</div>

<div id="lightbox" class="lightbox hidden" onclick="closeLightbox()">
  <img id="lightbox-img" src="" alt="Phóng to">
  <button class="lightbox-close" onclick="closeLightbox()">✕</button>
</div>

<script>
function openLightbox(src) {
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox').classList.remove('hidden');
}
function closeLightbox() {
  document.getElementById('lightbox').classList.add('hidden');
}
const az = document.getElementById('a-upload-zone');
const af = document.getElementById('a-image-input');
const ap = document.getElementById('a-image-preview');
const ar = document.getElementById('a-remove-img');
const ah = document.getElementById('a-upload-placeholder');
if (az) {
  az.addEventListener('click', e => { if(e.target!==ar) af.click(); });
  af.addEventListener('change', e => { if(e.target.files[0]) showAPrev(e.target.files[0]); });
  ar.addEventListener('click', e => {
    e.stopPropagation(); af.value=''; ap.src='';
    ap.classList.add('hidden'); ah.classList.remove('hidden'); ar.classList.add('hidden');
  });
  function showAPrev(file) {
    const r = new FileReader();
    r.onload = e => { ap.src=e.target.result; ap.classList.remove('hidden'); ah.classList.add('hidden'); ar.classList.remove('hidden'); };
    r.readAsDataURL(file);
  }
}
</script>
<?php require_once 'includes/footer.php'; ?>
