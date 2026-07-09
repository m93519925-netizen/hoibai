<?php
require_once 'config/supabase.php';
require_once 'includes/subjects.php';

$user    = get_supabase_user();
$profile = ($user && isset($user['id'])) ? get_profile($user['id']) : null;

$grade   = $_GET['grade']   ?? '';
$subject = $_GET['subject'] ?? '';
$search  = trim($_GET['q']  ?? '');
$sort    = $_GET['sort']    ?? 'newest';
$page    = max(0, (int)($_GET['page'] ?? 0));
$per     = 10;

$filters   = ["select=id,title,body,image_url,subject,grade_group,points_cost,views,created_at,status,profiles(username)"];
$filters[] = "order=" . ($sort === 'popular' ? 'views.desc' : 'created_at.desc');
$filters[] = "limit={$per}&offset=" . ($page * $per);
if ($grade)   $filters[] = "grade_group=eq." . urlencode($grade);
if ($subject) $filters[] = "subject=eq."     . urlencode($subject);
if ($search)  $filters[] = "title=ilike.*"   . urlencode($search) . '*';

$r         = db_select('questions', implode('&', $filters));
$questions = $r['data'] ?? [];
$subjects_by_grade = $grade ? subjects_for_grade($grade) : [];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HỏiBài – Hỏi đáp bài tập K1–12</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<nav class="navbar">
  <a href="/index.php" class="logo">🎓 HỏiBài</a>
  <form class="search-form" method="GET" action="/index.php">
    <?php if ($grade):   ?><input type="hidden" name="grade"   value="<?= h($grade) ?>"><?php endif; ?>
    <?php if ($subject): ?><input type="hidden" name="subject" value="<?= h($subject) ?>"><?php endif; ?>
    <input type="text" name="q" value="<?= h($search) ?>" placeholder="🔍 Tìm câu hỏi..." class="search-bar">
  </form>
  <div class="nav-actions">
    <?php if ($user && $profile): ?>
      <span class="points-badge">⭐ <?= (int)$profile['points'] ?> điểm</span>
      <a href="/ask.php" class="btn btn-primary">+ Đặt câu hỏi</a>
      <span class="nav-username">👤 <?= h($profile['username']) ?></span>
      <a href="/logout.php" class="btn btn-ghost">Đăng xuất</a>
    <?php else: ?>
      <a href="/ask.php" class="btn btn-primary">+ Đặt câu hỏi</a>
      <a href="/login.php" class="btn btn-ghost">Đăng nhập</a>
      <a href="/register.php" class="btn btn-outline">Đăng ký</a>
    <?php endif; ?>
  </div>
</nav>

<div class="container">
  <aside class="sidebar">
    <div class="sidebar-section">
      <h3>📚 Khối lớp</h3>
      <?php foreach (get_grade_groups() as $key => $label): ?>
        <a href="?grade=<?= urlencode($key) ?>"
           class="sidebar-link <?= $grade === $key ? 'active' : '' ?>">
          <?= h($label) ?>
        </a>
      <?php endforeach; ?>
      <?php if ($grade): ?>
        <a href="/index.php" class="sidebar-link text-muted">✕ Bỏ lọc</a>
      <?php endif; ?>
    </div>

    <?php if ($grade && $subjects_by_grade): ?>
    <div class="sidebar-section">
      <h3>📖 Môn học</h3>
      <?php foreach ($subjects_by_grade as $group => $subs): ?>
        <p class="sidebar-group"><?= h($group) ?></p>
        <?php foreach ($subs as $sub): ?>
          <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($sub) ?>"
             class="sidebar-link indent <?= $subject === $sub ? 'active' : '' ?>">
            <?= h($sub) ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="sidebar-section">
      <div class="points-info-box">
        <h4>💡 Hệ thống điểm</h4>
        <ul>
          <li>🎁 Đăng ký: <strong>+60 điểm</strong></li>
          <li>❓ Đặt câu hỏi: <strong>-10 đến -60 điểm</strong></li>
          <li>✅ Được chấp nhận: <strong>+15 điểm</strong></li>
          <li>📝 Trả lời: <strong>+5 điểm</strong></li>
        </ul>
      </div>
    </div>
  </aside>

  <main>
    <div class="feed-header">
      <h2>
        <?php if ($subject): echo h($subject);
        elseif ($grade): echo h(get_grade_groups()[$grade]);
        else: echo 'Tất cả câu hỏi'; endif; ?>
      </h2>
      <div class="feed-controls">
        <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($subject) ?>&q=<?= urlencode($search) ?>&sort=newest"
           class="sort-btn <?= $sort !== 'popular' ? 'active' : '' ?>">Mới nhất</a>
        <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($subject) ?>&q=<?= urlencode($search) ?>&sort=popular"
           class="sort-btn <?= $sort === 'popular' ? 'active' : '' ?>">Nhiều xem</a>
      </div>
    </div>

    <?php if (empty($questions)): ?>
      <div class="empty-state">
        <p>😕 Chưa có câu hỏi nào<?= $search ? " cho \"" . h($search) . "\"" : '' ?>.</p>
        <a href="/ask.php" class="btn btn-primary">Đặt câu hỏi đầu tiên!</a>
      </div>
    <?php else: ?>
      <div class="questions-list">
        <?php foreach ($questions as $q): ?>
          <a href="/question.php?id=<?= h($q['id']) ?>" class="question-card">
            <div class="q-stats">
              <div class="stat-item">
                <strong><?= (int)$q['views'] ?></strong>
                <span>lượt xem</span>
              </div>
              <div class="stat-item cost">
                <strong><?= (int)$q['points_cost'] ?>⭐</strong>
                <span>thưởng</span>
              </div>
            </div>
            <div class="q-body">
              <div class="q-meta-top">
                <span class="grade-badge"><?= h($q['grade_group']) ?></span>
                <span class="subject-badge"><?= h($q['subject']) ?></span>
                <?php if ($q['status'] === 'answered'): ?>
                  <span class="answered-badge">✅ Đã giải</span>
                <?php endif; ?>
              </div>
              <h3 class="q-title"><?= h($q['title']) ?></h3>
              <?php if ($q['body']): ?>
                <p class="q-excerpt"><?= h(mb_substr($q['body'], 0, 120)) ?><?= mb_strlen($q['body']) > 120 ? '...' : '' ?></p>
              <?php endif; ?>
              <?php if ($q['image_url']): ?>
                <img src="<?= h($q['image_url']) ?>" class="q-thumb" alt="Ảnh" loading="lazy">
              <?php endif; ?>
              <div class="q-meta-bottom">
                👤 <?= h($q['profiles']['username'] ?? 'Ẩn danh') ?> • <?= time_ago($q['created_at']) ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="pagination">
        <?php if ($page > 0): ?>
          <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($subject) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $page-1 ?>" class="page-btn">← Trước</a>
        <?php endif; ?>
        <?php if (count($questions) === $per): ?>
          <a href="?grade=<?= urlencode($grade) ?>&subject=<?= urlencode($subject) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $page+1 ?>" class="page-btn">Tiếp →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<?php require_once 'includes/footer.php'; ?>
