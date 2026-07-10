<?php
require_once 'config/supabase.php';
require_once 'includes/head.php';

$user    = get_supabase_user();
$profile = ($user && isset($user['id'])) ? get_profile($user['id']) : null;

$id = trim($_GET['id'] ?? '');
if (!$id || !preg_match('/^[0-9a-f-]{36}$/i', $id)) {
    header('Location: /index.php'); exit;
}

$view_key = 'viewed_'.$id;
if (empty($_SESSION[$view_key]) || time()-$_SESSION[$view_key] >= 30) {
    $_SESSION[$view_key] = time();
    db_rpc('increment_views', ['question_id' => $id]);
}

$qr = db_select('questions', "id=eq.{$id}&select=*,profiles(username)");
$q  = $qr['data'][0] ?? null;
if (!$q) { http_response_code(404); die('Không tìm thấy câu hỏi.'); }

include_head(h($q['title']));

$answer_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    if (!$user) { header('Location: /login.php'); exit; }

    $action = $_POST['action'] ?? '';
    if ($action==='answer')          rate_limit('answer', 10);
    if ($action==='accept')          rate_limit('accept', 5);
    if ($action==='delete_question') rate_limit('delete_question', 10);
    if ($action==='delete_answer')   rate_limit('delete_answer', 10);

    if ($action === 'answer') {
        $body = trim($_POST['body'] ?? '');
        if (mb_strlen($body) < 5) {
            $answer_error = 'Câu trả lời quá ngắn (tối thiểu 5 ký tự).';
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
                db_insert('answers', ['question_id'=>$id,'user_id'=>$user['id'],'body'=>$body,'image_url'=>$image_url]);
                add_points($user['id'], 5, 'answer_posted', $id);
                header("Location: /question.php?id={$id}#answers"); exit;
            }
        }
    }

    if ($action==='accept' && $user['id']===$q['user_id']) {
        $aid = $_POST['answer_id'] ?? '';
        if (preg_match('/^[0-9a-f-]{36}$/i', $aid)) {
            $dup = db_select('point_transactions', "reason=eq.answer_accepted&ref_id=eq.{$aid}&select=id&limit=1");
            $already = !empty($dup['data']);
            db_update('answers', "question_id=eq.{$id}", ['is_accepted'=>false]);
            db_update('answers', "id=eq.{$aid}", ['is_accepted'=>true]);
            db_update('questions', "id=eq.{$id}", ['status'=>'answered']);
            if (!$already) {
                $ar = db_select('answers', "id=eq.{$aid}&select=user_id");
                $owner = $ar['data'][0]['user_id'] ?? null;
                if ($owner) add_points($owner, (int)$q['points_cost'], 'answer_accepted', $aid);
            }
            header("Location: /question.php?id={$id}#answers"); exit;
        }
    }

    if ($action==='delete_question' && $user['id']===$q['user_id']) {
        db_delete('questions', "id=eq.{$id}");
        header('Location: /index.php'); exit;
    }

    if ($action==='delete_answer') {
        $aid = $_POST['answer_id'] ?? '';
        if (preg_match('/^[0-9a-f-]{36}$/i', $aid)) {
            $ar = db_select('answers', "id=eq.{$aid}&select=user_id");
            if (($ar['data'][0]['user_id']??'') === $user['id']) db_delete('answers', "id=eq.{$aid}");
        }
        header("Location: /question.php?id={$id}#answers"); exit;
    }
}

$ar      = db_select('answers', "question_id=eq.{$id}&select=*,profiles(username)&order=is_accepted.desc,created_at.asc");
$answers = $ar['data'] ?? [];
?>
<?php require_once 'includes/navbar.php'; ?>

<div class="container single-col">
  <!-- Câu hỏi -->
  <div class="q-detail-card">
    <div class="q-meta-top">
      <span class="badge badge-grade">
        <i class="fa-solid fa-graduation-cap fa-xs"></i> <?= h($q['grade_group']) ?>
      </span>
      <span class="badge badge-subject">
        <i class="fa-solid fa-book fa-xs"></i> <?= h($q['subject']) ?>
      </span>
      <?php if ($q['status']==='answered'): ?>
        <span class="badge badge-solved">
          <i class="fa-solid fa-check fa-xs"></i> Đã được giải
        </span>
      <?php endif; ?>
      <span class="badge badge-reward">
        <i class="fa-solid fa-trophy fa-xs"></i> Thưởng <?= (int)$q['points_cost'] ?>⭐
      </span>
    </div>

    <h1><?= h($q['title']) ?></h1>

    <div class="q-detail-info">
      <span><i class="fa-solid fa-user"></i> <?= h($q['profiles']['username'] ?? 'Ẩn danh') ?></span>
      <span><i class="fa-regular fa-clock"></i> <?= time_ago($q['created_at']) ?></span>
      <span><i class="fa-solid fa-eye"></i> <?= (int)$q['views'] ?> lượt xem</span>
    </div>

    <?php if ($q['body']): ?>
      <div class="q-detail-body"><?= nl2br_safe($q['body']) ?></div>
    <?php endif; ?>

    <?php if ($q['image_url']): ?>
      <div class="q-image-wrap">
        <img src="<?= h($q['image_url']) ?>" class="q-image"
             alt="Ảnh đề bài" onclick="openLightbox(this.src)" loading="lazy">
        <small><i class="fa-solid fa-magnifying-glass"></i> Click để phóng to</small>
      </div>
    <?php endif; ?>

    <?php if ($user && $user['id']===$q['user_id']): ?>
      <div class="owner-actions">
        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa câu hỏi này?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete_question">
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="fa-solid fa-trash"></i> Xóa câu hỏi
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <!-- Câu trả lời -->
  <div id="answers">
    <h3 class="answers-heading">
      <i class="fa-solid fa-comments"></i>
      <?= count($answers) ?> câu trả lời
    </h3>

    <?php if (empty($answers)): ?>
      <div class="empty-state small">
        <i class="fa-solid fa-comment-slash fa-2x"></i>
        <p>Chưa có câu trả lời. Hãy là người đầu tiên! 🌟</p>
      </div>
    <?php endif; ?>

    <?php foreach ($answers as $a): ?>
      <div class="answer-card <?= $a['is_accepted']?'accepted':'' ?>" id="a-<?= h($a['id']) ?>">
        <?php if ($a['is_accepted']): ?>
          <div class="accepted-label">
            <i class="fa-solid fa-circle-check"></i>
            Câu trả lời được chấp nhận – Nhận <?= (int)$q['points_cost'] ?>⭐
          </div>
        <?php endif; ?>

        <div class="answer-body"><?= nl2br_safe($a['body']) ?></div>

        <?php if ($a['image_url']): ?>
          <div class="a-image-wrap">
            <img src="<?= h($a['image_url']) ?>" class="a-image"
                 alt="Ảnh trả lời" onclick="openLightbox(this.src)" loading="lazy">
          </div>
        <?php endif; ?>

        <div class="answer-footer">
          <span class="answer-meta">
            <i class="fa-solid fa-user fa-xs"></i>
            <?= h($a['profiles']['username'] ?? 'Ẩn danh') ?> •
            <i class="fa-regular fa-clock fa-xs"></i>
            <?= time_ago($a['created_at']) ?>
          </span>
          <div class="answer-actions">
            <?php if ($user && $user['id']===$q['user_id'] && !$a['is_accepted']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="answer_id" value="<?= h($a['id']) ?>">
                <button type="submit" class="btn btn-accept btn-sm">
                  <i class="fa-solid fa-check"></i>
                  Chấp nhận +<?= (int)$q['points_cost'] ?>⭐
                </button>
              </form>
            <?php endif; ?>
            <?php if ($user && $user['id']===$a['user_id']): ?>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('Xóa câu trả lời này?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_answer">
                <input type="hidden" name="answer_id" value="<?= h($a['id']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Form trả lời -->
  <?php if ($user): ?>
    <div class="form-card" id="reply">
      <h3>
        <i class="fa-solid fa-pen-to-square"></i> Viết câu trả lời
        <small class="text-muted">
          (+5⭐ khi gửi · +<?= (int)$q['points_cost'] ?>⭐ nếu được chấp nhận)
        </small>
      </h3>

      <?php if ($answer_error): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-circle-exclamation"></i> <?= h($answer_error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="answer">

        <div class="form-group">
          <textarea name="body" rows="6"
                    placeholder="Viết lời giải chi tiết, dễ hiểu..."
                    required><?= h($_POST['body']??'') ?></textarea>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-image"></i> Ảnh minh họa (tùy chọn)</label>
          <div class="upload-zone" id="a-upload-zone">
            <input type="file" name="image" id="a-image-input" accept="image/*" hidden>
            <div id="a-upload-placeholder" class="upload-placeholder">
              <i class="fa-solid fa-cloud-arrow-up fa-xl"></i>
              <span>Click để chọn ảnh</span>
            </div>
            <img id="a-image-preview" class="img-preview hidden" alt="Preview">
            <button type="button" id="a-remove-img" class="btn btn-ghost btn-sm hidden">
              <i class="fa-solid fa-xmark"></i> Xóa
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full">
          <i class="fa-solid fa-paper-plane"></i> Gửi câu trả lời (+5⭐)
        </button>
      </form>
    </div>
  <?php else: ?>
    <div class="login-cta">
      <i class="fa-solid fa-lock fa-2x"></i>
      <p>Đăng nhập để trả lời và nhận điểm thưởng!</p>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a href="/login.php" class="btn btn-primary">
          <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
        </a>
        <a href="/register.php" class="btn btn-outline">
          <i class="fa-solid fa-user-plus"></i> Đăng ký nhận 60⭐
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Lightbox -->
<div id="lightbox" class="lightbox hidden" onclick="closeLightbox()">
  <img id="lightbox-img" src="" alt="Phóng to">
  <button class="lightbox-close" onclick="closeLightbox()">
    <i class="fa-solid fa-xmark"></i>
  </button>
</div>

<script>
function openLightbox(src) {
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.add('hidden');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeLightbox(); });

// Upload answer image
const az=document.getElementById('a-upload-zone'),
      af=document.getElementById('a-image-input'),
      ap=document.getElementById('a-image-preview'),
      ar=document.getElementById('a-remove-img'),
      ah=document.getElementById('a-upload-placeholder');
if(az){
  az.addEventListener('click',e=>{ if(e.target!==ar&&!ar.contains(e.target)) af.click(); });
  af.addEventListener('change',e=>{ if(e.target.files[0]) showA(e.target.files[0]); });
  ar.addEventListener('click',e=>{
    e.stopPropagation(); af.value=''; ap.src='';
    ap.classList.add('hidden'); ah.classList.remove('hidden'); ar.classList.add('hidden');
  });
  function showA(file){
    const r=new FileReader();
    r.onload=e=>{ ap.src=e.target.result; ap.classList.remove('hidden'); ah.classList.add('hidden'); ar.classList.remove('hidden'); };
    r.readAsDataURL(file);
  }
}
</script>
<?php require_once 'includes/footer.php'; ?>
