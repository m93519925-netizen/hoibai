<?php
require_once 'config/supabase.php';
require_once 'includes/subjects.php';
require_once 'includes/head.php';
include_head('Hỏi đáp bài tập K1–12');

$user    = get_supabase_user();
$profile = ($user && isset($user['id'])) ? get_profile($user['id']) : null;

$grade   = $_GET['grade']   ?? '';
$subject = $_GET['subject'] ?? '';
$search  = trim($_GET['q']  ?? '');
$sort    = $_GET['sort']    ?? 'newest';
$page    = max(0, (int)($_GET['page'] ?? 0));
$per     = 10;

$filters   = ["select=id,title,body,image_url,subject,grade_group,points_cost,views,created_at,status,profiles(username)"];
$filters[] = "order=".($sort==='popular'?'views.desc':'created_at.desc');
$filters[] = "limit={$per}&offset=".($page*$per);
if ($grade)   $filters[] = "grade_group=eq.".urlencode($grade);
if ($subject) $filters[] = "subject=eq.".urlencode($subject);
if ($search)  {
    $safe = sanitize_search($search);
    if ($safe !== '') $filters[] = "title=ilike.*".urlencode($safe).'*';
}

$r         = db_select('questions', implode('&', $filters));
$questions = $r['data'] ?? [];
$subjects_by_grade = $grade ? subjects_for_grade($grade) : [];
?>
<?php require_once 'includes/navbar.php'; ?>

<div class="container">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-card">
      <h3><i class="fa-solid fa-layer-group"></i> Khối lớp</h3>
      <?php foreach (get_grade_groups() as $key => $label): ?>
        <a href="?grade=<?= urlencode($key) ?>"
           class="sidebar-link <?= $grade===$key?'active':'' ?>">
          <i class="fa-solid fa-book-open"></i> <?= h($label) ?>
        </a>
      <?php endforeach; ?>
      <?php if ($grade): ?>
        <a href="/index.php" class="sidebar-link muted">
          <i class="fa-solid fa-xmark"></i> Bỏ lọc
        </a>
      <?php endif; ?>
    </div>

    <?php if ($grade && $subjects_by_grade): ?>
    <div class="sidebar-card">
      <h3><i class="fa-solid fa-list-ul"></i> Môn học</h3>
      <?php foreach ($subjects_by_grade as $group => $subs): ?>
        <p class="sidebar-group"><?= h($group) ?></p>
        <?php foreach ($subs as $sub): ?>
          <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($sub) ?>"
             class="sidebar-link indent <?= $subject===$sub?'active':'' ?>">
            <?= h($sub) ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="sidebar-card points-card">
      <h3><i class="fa-solid fa-star"></i> Hệ thống điểm</h3>
      <ul class="points-list">
        <li><i class="fa-solid fa-gift"></i> Đăng ký: <strong>+60⭐</strong></li>
        <li><i class="fa-solid fa-circle-question"></i> Đặt câu hỏi: <strong>-10 ~ -60⭐</strong></li>
        <li><i class="fa-solid fa-pen"></i> Trả lời: <strong>+5⭐</strong></li>
        <li><i class="fa-solid fa-check-circle"></i> Được chấp nhận: <strong>+điểm câu hỏi</strong></li>
      </ul>
    </div>
  </aside>

  <!-- MAIN -->
  <main>
    <div class="feed-header">
      <h2>
        <?php if ($subject): ?>
          <i class="fa-solid fa-book"></i> <?= h($subject) ?>
        <?php elseif ($grade): ?>
          <i class="fa-solid fa-users"></i> <?= h(get_grade_groups()[$grade]) ?>
        <?php else: ?>
          <i class="fa-solid fa-fire"></i> Tất cả câu hỏi
        <?php endif; ?>
      </h2>
      <div class="feed-controls">
        <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($subject) ?>&q=<?= urlencode($search) ?>&sort=newest"
           class="sort-btn <?= $sort!=='popular'?'active':'' ?>">
          <i class="fa-solid fa-clock"></i> Mới nhất
        </a>
        <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($subject) ?>&q=<?= urlencode($search) ?>&sort=popular"
           class="sort-btn <?= $sort==='popular'?'active':'' ?>">
          <i class="fa-solid fa-fire"></i> Nhiều xem
        </a>
      </div>
    </div>

    <?php if (empty($questions)): ?>
      <div class="empty-state">
        <i class="fa-solid fa-inbox fa-3x"></i>
        <p>Chưa có câu hỏi nào<?= $search?' cho "'.h($search).'"':'' ?>.</p>
        <a href="/ask.php" class="btn btn-primary">
          <i class="fa-solid fa-plus"></i> Đặt câu hỏi đầu tiên!
        </a>
      </div>
    <?php else: ?>
      <div class="questions-list">
        <?php foreach ($questions as $q): ?>
          <a href="/question.php?id=<?= h($q['id']) ?>" class="question-card">
            <div class="q-stats">
              <div class="stat-item">
                <strong><?= (int)$q['views'] ?></strong>
                <span><i class="fa-solid fa-eye fa-xs"></i> xem</span>
              </div>
              <div class="stat-item reward">
                <strong><?= (int)$q['points_cost'] ?></strong>
                <span><i class="fa-solid fa-star fa-xs"></i> thưởng</span>
              </div>
            </div>
            <div class="q-body">
              <div class="q-meta-top">
                <span class="badge badge-grade">
                  <i class="fa-solid fa-graduation-cap fa-xs"></i>
                  <?= h($q['grade_group']) ?>
                </span>
                <span class="badge badge-subject">
                  <i class="fa-solid fa-book fa-xs"></i>
                  <?= h($q['subject']) ?>
                </span>
                <?php if ($q['status']==='answered'): ?>
                  <span class="badge badge-solved">
                    <i class="fa-solid fa-check fa-xs"></i> Đã giải
                  </span>
                <?php endif; ?>
              </div>
              <h3 class="q-title"><?= h($q['title']) ?></h3>
              <?php if ($q['body']): ?>
                <p class="q-excerpt"><?= h(mb_substr($q['body'],0,120)) ?><?= mb_strlen($q['body'])>120?'...':'' ?></p>
              <?php endif; ?>
              <?php if ($q['image_url']): ?>
                <img src="<?= h($q['image_url']) ?>" class="q-thumb" alt="Ảnh" loading="lazy">
              <?php endif; ?>
              <div class="q-meta-bottom">
                <i class="fa-solid fa-user fa-xs"></i>
                <?= h($q['profiles']['username'] ?? 'Ẩn danh') ?> •
                <i class="fa-regular fa-clock fa-xs"></i>
                <?= time_ago($q['created_at']) ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="pagination">
        <?php if ($page > 0): ?>
          <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($subject) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $page-1 ?>"
             class="page-btn">
            <i class="fa-solid fa-chevron-left"></i> Trước
          </a>
        <?php endif; ?>
        <span class="page-info">Trang <?= $page+1 ?></span>
        <?php if (count($questions)===$per): ?>
          <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($subject) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $page+1 ?>"
             class="page-btn">
            Tiếp <i class="fa-solid fa-chevron-right"></i>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php require_once 'includes/footer.php'; ?>
