<?php
require_once 'config/supabase.php';
require_once 'includes/subjects.php';
require_once 'includes/head.php';

$user    = require_login();
$profile = get_profile($user['id']);
include_head('Đặt câu hỏi');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    rate_limit('ask_question', 5);

    $grade   = $_POST['grade']        ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $title   = trim($_POST['title']   ?? '');
    $body    = trim($_POST['body']    ?? '');
    $cost    = (int)($_POST['points_cost'] ?? 10);

    if (!in_array($grade, array_keys(get_grade_groups()))) {
        $error = 'Vui lòng chọn khối lớp hợp lệ.';
    } elseif (!in_array($subject, get_all_subjects_flat())) {
        $error = 'Vui lòng chọn môn học hợp lệ.';
    } elseif (mb_strlen($title) < 10 || mb_strlen($title) > 300) {
        $error = 'Tiêu đề từ 10–300 ký tự.';
    } elseif ($cost < 10 || $cost > 60) {
        $error = 'Điểm từ 10–60.';
    } elseif ($profile['points'] < $cost) {
        $error = "Không đủ điểm. Bạn có {$profile['points']}⭐, cần {$cost}⭐.";
    } else {
        $image_url = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            if ($_FILES['image']['size'] > 5*1024*1024) {
                $error = 'Ảnh tối đa 5MB.';
            } else {
                $image_url = storage_upload($_FILES['image']['tmp_name'], $_FILES['image']['name'], 'questions');
                if (!$image_url) $error = 'Upload ảnh thất bại.';
            }
        }
        if (!$error) {
            if (!deduct_points($user['id'], $cost, 'ask_question')) {
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
                    header('Location: /question.php?id='.$r['data'][0]['id']); exit;
                }
                $error = 'Đăng câu hỏi thất bại. Vui lòng thử lại.';
            }
        }
    }
}
?>
<?php require_once 'includes/navbar.php'; ?>

<div class="container single-col">
  <div class="form-card">
    <h2><i class="fa-solid fa-pen-to-square"></i> Đặt câu hỏi mới</h2>

    <div class="info-box">
      <i class="fa-solid fa-circle-info"></i>
      <div>
        <strong>Bạn đang có <?= (int)$profile['points'] ?>⭐</strong><br>
        <small>Đặt câu hỏi → dùng điểm treo thưởng · Người trả lời được chấp nhận → nhận điểm đó · Trả lời câu hỏi khác để kiếm thêm +5⭐/câu</small>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i> <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-row">
        <div class="form-group">
          <label><i class="fa-solid fa-layer-group"></i> Khối lớp <span class="req">*</span></label>
          <select name="grade" id="grade-select" required onchange="loadSubjects(this.value)">
            <option value="">-- Chọn khối lớp --</option>
            <?php foreach (get_grade_groups() as $k => $l): ?>
              <option value="<?= h($k) ?>" <?= ($_POST['grade']??'')===$k?'selected':'' ?>>
                <?= h($l) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-book"></i> Môn học <span class="req">*</span></label>
          <select name="subject" id="subject-select" required>
            <option value="">-- Chọn khối lớp trước --</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label><i class="fa-solid fa-heading"></i> Tiêu đề câu hỏi <span class="req">*</span></label>
        <input type="text" name="title"
               value="<?= h($_POST['title']??'') ?>"
               placeholder="VD: Giải bài toán tìm x: 2x + 3 = 11"
               required minlength="10" maxlength="300"
               oninput="document.getElementById('tc').textContent=this.value.length+'/300'">
        <small id="tc">0/300</small>
      </div>

      <div class="form-group">
        <label><i class="fa-solid fa-align-left"></i> Mô tả chi tiết</label>
        <textarea name="body" rows="5"
                  placeholder="Trình bày đề bài, những gì bạn đã thử..."><?= h($_POST['body']??'') ?></textarea>
      </div>

      <div class="form-group">
        <label><i class="fa-solid fa-image"></i> Ảnh đề bài <small>(tùy chọn, tối đa 5MB)</small></label>
        <div class="upload-zone" id="upload-zone">
          <input type="file" name="image" id="image-input" accept="image/*" hidden>
          <div id="upload-placeholder" class="upload-placeholder">
            <i class="fa-solid fa-cloud-arrow-up fa-2x"></i>
            <span>Kéo thả hoặc <strong>click để chọn</strong></span>
            <small>PNG, JPG, GIF, WebP</small>
          </div>
          <img id="image-preview" class="img-preview hidden" alt="Preview">
          <button type="button" id="remove-img" class="btn btn-ghost btn-sm hidden">
            <i class="fa-solid fa-xmark"></i> Xóa ảnh
          </button>
        </div>
      </div>

      <div class="form-group">
        <label>
          <i class="fa-solid fa-star"></i> Điểm thưởng
          <span class="req">*</span>
          <small>(Bạn có <?= (int)$profile['points'] ?>⭐)</small>
        </label>
        <div class="slider-wrap">
          <input type="range" name="points_cost" id="pts-slider"
                 min="10" max="<?= min(60,(int)$profile['points']) ?>"
                 step="5" value="<?= (int)($_POST['points_cost']??10) ?>"
                 oninput="document.getElementById('pts-val').textContent=this.value;document.getElementById('pts-cost').textContent=this.value">
          <div class="pts-display">
            <i class="fa-solid fa-star"></i>
            <strong id="pts-val"><?= (int)($_POST['points_cost']??10) ?></strong> điểm
          </div>
        </div>
        <div class="pts-tiers">
          <span class="tier tier-low"><i class="fa-solid fa-circle fa-xs"></i> 10–20: Cơ bản</span>
          <span class="tier tier-mid"><i class="fa-solid fa-circle fa-xs"></i> 25–40: Ưu tiên</span>
          <span class="tier tier-high"><i class="fa-solid fa-circle fa-xs"></i> 45–60: Khẩn cấp</span>
        </div>
      </div>

      <div class="form-actions">
        <a href="/index.php" class="btn btn-ghost">
          <i class="fa-solid fa-xmark"></i> Hủy
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-paper-plane"></i>
          Đăng câu hỏi (trừ <span id="pts-cost"><?= (int)($_POST['points_cost']??10) ?></span>⭐)
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
    subs.forEach(s => {
      const o = document.createElement('option');
      o.value = s; o.textContent = s;
      if (s === savedSubject) o.selected = true;
      og.appendChild(o);
    });
    sel.appendChild(og);
  }
}
if (savedGrade) loadSubjects(savedGrade);

// Upload preview
const zone = document.getElementById('upload-zone');
const fi   = document.getElementById('image-input');
const prev = document.getElementById('image-preview');
const rem  = document.getElementById('remove-img');
const ph   = document.getElementById('upload-placeholder');

zone.addEventListener('click', e => { if(e.target!==rem && !rem.contains(e.target)) fi.click(); });
['dragover','dragenter'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('drag-over'); }));
['dragleave','dragend'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('drag-over')));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('drag-over');
  if (e.dataTransfer.files[0]) showPrev(e.dataTransfer.files[0]);
});
fi.addEventListener('change', e => { if(e.target.files[0]) showPrev(e.target.files[0]); });
function showPrev(file) {
  if (file.size > 5*1024*1024) { alert('Ảnh tối đa 5MB!'); return; }
  const r = new FileReader();
  r.onload = e => {
    prev.src = e.target.result;
    prev.classList.remove('hidden');
    ph.classList.add('hidden');
    rem.classList.remove('hidden');
  };
  r.readAsDataURL(file);
}
rem.addEventListener('click', e => {
  e.stopPropagation(); fi.value=''; prev.src='';
  prev.classList.add('hidden'); ph.classList.remove('hidden'); rem.classList.add('hidden');
});
</script>
<?php require_once 'includes/footer.php'; ?>
