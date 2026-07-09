<?php
require_once 'config/supabase.php';
require_once 'includes/subjects.php';

$user    = require_login();
$profile = get_profile($user['id']);
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $grade   = $_POST['grade']        ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $title   = trim($_POST['title']   ?? '');
    $body    = trim($_POST['body']    ?? '');
    $cost    = (int)($_POST['points_cost'] ?? 10);

    $valid_grades   = array_keys(get_grade_groups());
    $valid_subjects = get_all_subjects_flat();

    if (!in_array($grade, $valid_grades)) {
        $error = 'Vui lòng chọn khối lớp hợp lệ.';
    } elseif (!in_array($subject, $valid_subjects)) {
        $error = 'Vui lòng chọn môn học hợp lệ.';
    } elseif (mb_strlen($title) < 10 || mb_strlen($title) > 300) {
        $error = 'Tiêu đề từ 10 đến 300 ký tự.';
    } elseif ($cost < 10 || $cost > 60) {
        $error = 'Điểm từ 10 đến 60.';
    } elseif ($profile['points'] < $cost) {
        $error = "Không đủ điểm. Bạn có {$profile['points']} điểm, cần {$cost} điểm.";
    } else {
        $image_url = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $error = 'Ảnh tối đa 5MB.';
            } else {
                $image_url = storage_upload($_FILES['image']['tmp_name'], $_FILES['image']['name'], 'questions');
                if (!$image_url) $error = 'Upload ảnh thất bại.';
            }
        }
        if (!$error) {
            $ok = deduct_points($user['id'], $cost, 'ask_question');
            if (!$ok) {
                $error = 'Không đủ điểm.';
            } else {
                $r = db_insert('questions', [
                    'user_id'     => $user['id'],
                    'grade_group' => $grade,
                    'subject'     => $subject,
                    'title'       => $title,
                    'body'        => $body ?: null,
                    'image_url'   => $image_url,
                    'points_cost' => $cost,
                ]);
                if (!empty($r['data'][0]['id'])) {
                    header('Location: /question.php?id=' . $r['data'][0]['id']);
                    exit;
                }
                $error = 'Đăng câu hỏi thất bại.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đặt câu hỏi – HỏiBài</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<nav class="navbar">
  <a href="/index.php" class="logo">🎓 HỏiBài</a>
  <div class="nav-actions">
    <span class="points-badge">⭐ <?= (int)$profile['points'] ?> điểm</span>
    <span class="nav-username">👤 <?= h($profile['username']) ?></span>
    <a href="/logout.php" class="btn btn-ghost">Đăng xuất</a>
  </div>
</nav>

<div class="container single-col">
  <div class="form-card">
    <h2>📝 Đặt câu hỏi mới</h2>

    <div class="points-explain-box">
      <strong>💡 Cách hoạt động:</strong>
      <ul>
        <li>Bạn đang có <strong><?= (int)$profile['points'] ?> điểm</strong></li>
        <li>Đặt câu hỏi → dùng điểm treo thưởng (10–60⭐)</li>
        <li>Người trả lời được chấp nhận → nhận toàn bộ điểm đó</li>
        <li>Muốn kiếm điểm? Hãy trả lời câu hỏi khác! (+5⭐/câu)</li>
      </ul>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label>Khối lớp <span class="req">*</span></label>
        <select name="grade" id="grade-select" required onchange="loadSubjects(this.value)">
          <option value="">-- Chọn khối lớp --</option>
          <?php foreach (get_grade_groups() as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= ($_POST['grade'] ?? '') === $key ? 'selected' : '' ?>>
              <?= h($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Môn học <span class="req">*</span></label>
        <select name="subject" id="subject-select" required>
          <option value="">-- Chọn khối lớp trước --</option>
        </select>
      </div>

      <div class="form-group">
        <label>Tiêu đề câu hỏi <span class="req">*</span></label>
        <input type="text" name="title"
               value="<?= h($_POST['title'] ?? '') ?>"
               placeholder="VD: Giải bài toán tìm x: 2x + 3 = 11"
               required minlength="10" maxlength="300">
        <small id="title-count">0/300</small>
      </div>

      <div class="form-group">
        <label>Mô tả chi tiết</label>
        <textarea name="body" rows="5"
                  placeholder="Trình bày đề bài, những gì bạn đã thử..."><?= h($_POST['body'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label>📷 Ảnh đề bài (tùy chọn, tối đa 5MB)</label>
        <div class="upload-zone" id="upload-zone">
          <input type="file" name="image" id="image-input" accept="image/*" hidden>
          <div id="upload-placeholder">
            <span>📎 Kéo thả hoặc <strong>click để chọn ảnh</strong></span>
            <small>PNG, JPG, GIF, WebP</small>
          </div>
          <img id="image-preview" class="img-preview hidden" alt="Preview">
          <button type="button" id="remove-img" class="btn btn-ghost btn-sm hidden">✕ Xóa ảnh</button>
        </div>
      </div>

      <div class="form-group">
        <label>⭐ Điểm thưởng <span class="req">*</span>
          <small>(Bạn có <?= (int)$profile['points'] ?> điểm)</small>
        </label>
        <div class="points-slider-wrap">
          <input type="range" name="points_cost" id="points-slider"
                 min="10" max="<?= min(60, (int)$profile['points']) ?>"
                 step="5" value="<?= (int)($_POST['points_cost'] ?? 10) ?>">
          <div class="points-display">
            <strong id="points-val"><?= (int)($_POST['points_cost'] ?? 10) ?></strong> điểm
          </div>
        </div>
        <div class="points-tiers">
          <span class="tier tier-low">10–20: Cơ bản</span>
          <span class="tier tier-mid">25–40: Ưu tiên cao</span>
          <span class="tier tier-high">45–60: Khẩn cấp</span>
        </div>
      </div>

      <div class="form-actions">
        <a href="/index.php" class="btn btn-ghost">Hủy</a>
        <button type="submit" class="btn btn-primary">
          Đăng câu hỏi (trừ <span id="cost-preview">10</span>⭐)
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const subjectsData = <?= json_encode(get_subjects(), JSON_UNESCAPED_UNICODE) ?>;
const savedSubject = <?= json_encode($_POST['subject'] ?? '') ?>;
const savedGrade   = <?= json_encode($_POST['grade']   ?? '') ?>;

function loadSubjects(grade) {
  const sel = document.getElementById('subject-select');
  sel.innerHTML = '<option value="">-- Chọn môn học --</option>';
  if (!grade || !subjectsData[grade]) return;
  for (const [group, subs] of Object.entries(subjectsData[grade])) {
    const og = document.createElement('optgroup');
    og.label = group;
    subs.forEach(sub => {
      const opt = document.createElement('option');
      opt.value = sub; opt.textContent = sub;
      if (sub === savedSubject) opt.selected = true;
      og.appendChild(opt);
    });
    sel.appendChild(og);
  }
}
if (savedGrade) loadSubjects(savedGrade);

const slider  = document.getElementById('points-slider');
const valDisp = document.getElementById('points-val');
const costPrev= document.getElementById('cost-preview');
slider.addEventListener('input', () => {
  valDisp.textContent = costPrev.textContent = slider.value;
});

const titleInput = document.querySelector('input[name="title"]');
titleInput.addEventListener('input', () => {
  document.getElementById('title-count').textContent = titleInput.value.length + '/300';
});

const zone = document.getElementById('upload-zone');
const fi   = document.getElementById('image-input');
const prev = document.getElementById('image-preview');
const rem  = document.getElementById('remove-img');
const ph   = document.getElementById('upload-placeholder');

zone.addEventListener('click', e => { if(e.target !== rem) fi.click(); });
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('drag-over');
  if (e.dataTransfer.files[0]) showPrev(e.dataTransfer.files[0]);
});
fi.addEventListener('change', e => { if(e.target.files[0]) showPrev(e.target.files[0]); });
function showPrev(file) {
  if (file.size > 5*1024*1024) { alert('Ảnh tối đa 5MB!'); return; }
  const r = new FileReader();
  r.onload = e => { prev.src=e.target.result; prev.classList.remove('hidden'); ph.classList.add('hidden'); rem.classList.remove('hidden'); };
  r.readAsDataURL(file);
}
rem.addEventListener('click', e => {
  e.stopPropagation(); fi.value=''; prev.src='';
  prev.classList.add('hidden'); ph.classList.remove('hidden'); rem.classList.add('hidden');
});
</script>
<?php require_once 'includes/footer.php'; ?>
