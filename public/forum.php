<?php
require dirname(__DIR__) . '/src/bootstrap.php';
$me   = require_login();
$sort = (($_GET['sort'] ?? 'top') === 'new') ? 'new' : 'top';
$slug = $_GET['s'] ?? '';
$subject = null;

if ($slug !== '' && $slug !== 'all') {
  $q = $pdo->prepare('SELECT * FROM subjects WHERE slug=?');
  $q->execute([$slug]);
  $subject = $q->fetch();
  if (!$subject) { header('Location: forum.php'); exit; }
}
$browsing = $slug !== '';

$subjects = cache_remember('forum_subjects', 30, fn() => $pdo->query("SELECT s.name, s.slug, COUNT(p.id) AS n
  FROM subjects s LEFT JOIN posts p ON p.subject_id=s.id AND p.status='open'
  GROUP BY s.id ORDER BY s.name")->fetchAll());
$totalOpen = array_sum(array_column($subjects, 'n'));

if ($browsing) {
  $sql = "SELECT p.*, (SELECT COUNT(*) FROM offers o WHERE o.post_id=p.id) AS offer_count,
    u.username, u.tier, s.name AS subject, v.value AS my_vote
    FROM posts p
    JOIN users u ON u.id=p.user_id
    JOIN subjects s ON s.id=p.subject_id
    LEFT JOIN votes v ON v.post_id=p.id AND v.user_id=:me
    WHERE p.status='open'" . ($subject ? " AND p.subject_id=:sid" : "") .
    " ORDER BY " . ($sort === 'new' ? "p.created_at DESC" : "p.score DESC, p.created_at DESC") . " LIMIT 50";
  $params = [':me' => $me['id']];
  if ($subject) $params[':sid'] = $subject['id'];
  $q = $pdo->prepare($sql);
  $q->execute($params);
  $posts = $q->fetchAll();
  $here  = here();
}
?>
<!doctype html>
<html lang="en"><head><title>Forum · Academic Commission</title><?php include TEMPLATE_PATH . 'head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/forum.css"></head>
<body>
<?php include TEMPLATE_PATH . 'nav.php'; ?>
<main class="wrap">

<?php if (!$browsing): ?>
  <div class="page-head">
    <h1>Forum</h1>
    <p>Pick a subject to see open requests. Only clients post here, and commissioners reply with private offers.</p>
  </div>
  <div class="subject-cards">
    <a class="subject-card all" href="forum.php?s=all">
      <span class="sc-name">All requests</span>
      <span class="sc-count"><?= (int)$totalOpen ?><small>open</small></span>
    </a>
    <?php foreach ($subjects as $s): ?>
      <a class="subject-card" href="forum.php?s=<?= e($s['slug']) ?>">
        <span class="sc-name"><?= e($s['name']) ?></span>
        <span class="sc-count"><?= (int)$s['n'] ?><small>open</small></span>
      </a>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <div class="page-head">
    <p><a href="forum.php">All subjects</a></p>
    <h1><?= $subject ? e($subject['name']) : 'All requests' ?></h1>
    <p>Only client requests are posted here. Commissioners reply with private offers.</p>
  </div>

  <div class="forum-layout">
    <div>
      <div class="toolbar">
        <div class="seg" role="group" aria-label="Sort">
          <a href="forum.php?s=<?= e($slug) ?>&sort=top" class="<?= $sort==='top'?'on':'' ?>">Top</a>
          <a href="forum.php?s=<?= e($slug) ?>&sort=new" class="<?= $sort==='new'?'on':'' ?>">New</a>
        </div>
        <span class="muted"><?= e(plural(count($posts), 'request', 'requests')) ?></span>
      </div>

      <?php if (!$posts): ?>
        <section class="panel"><p class="empty">No open requests here yet.
          <?= $me['role']==='client' ? 'Be the first to post one.' : 'Check back soon or browse another subject.' ?></p></section>
      <?php endif; ?>

      <div class="feed">
      <?php foreach ($posts as $p): $urgent = is_urgent($p); ?>
        <article class="rq<?= $urgent ? ' is-urgent' : '' ?>">
          <?= vote_box($p, $me, $here) ?>
          <div class="rq-main">
            <div class="rq-kicker">
              <?php if (!$subject): ?><span><?= e($p['subject']) ?></span><span class="dot" aria-hidden="true">·</span><?php endif; ?>
              <span><?= e($p['type']) ?></span>
              <?php if ($urgent): ?>
                <span class="tag urgent" data-prefix="Urgent: " data-deadline="<?= e(date('c', strtotime($p['deadline']))) ?>">Urgent</span>
              <?php endif; ?>
            </div>
            <h3><a href="post.php?id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a></h3>
            <p class="rq-excerpt"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $p['description']), 0, 150, '…')) ?></p>
            <div class="rq-foot">
              <span class="offers-pill<?= $p['offer_count'] ? ' has' : '' ?>"><?= e(plural((int)$p['offer_count'], 'offer', 'offers')) ?></span>
              <span><?= (int)$p['pages'] ?> <?= $p['pages'] == 1 ? 'page' : 'pages' ?></span>
              <span>by <?= e($p['username']) ?> · <?= e($p['tier']) ?></span>
            </div>
          </div>
          <div class="rq-side">
            <div class="rq-budget"><small>Budget</small><?= money($p['budget_min']) ?> – <?= money($p['budget_max']) ?></div>
            <div class="rq-due"><small>Due</small><?= e(date('M j, g:i A', strtotime($p['deadline']))) ?></div>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    </div>

    <aside class="forum-side">
      <?php if ($me['role'] === 'client'): ?>
        <a class="btn" href="create_post.php<?= $subject ? '?s=' . e($slug) : '' ?>">New request</a>
      <?php endif; ?>
      <section class="panel">
        <h2>Subjects</h2>
        <ul class="side-list">
          <li><a href="forum.php?s=all&sort=<?= e($sort) ?>" class="<?= $slug==='all'?'on':'' ?>">All requests<span><?= (int)$totalOpen ?></span></a></li>
          <?php foreach ($subjects as $s): ?>
            <li><a href="forum.php?s=<?= e($s['slug']) ?>&sort=<?= e($sort) ?>" class="<?= $slug===$s['slug']?'on':'' ?>"><?= e($s['name']) ?><span><?= (int)$s['n'] ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </aside>
  </div>
<?php endif; ?>

</main>
</body></html>