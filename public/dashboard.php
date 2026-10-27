<?php
require dirname(__DIR__) . '/src/bootstrap.php';
$me  = require_login();
$uid = (int)$me['id'];
$isAdmin = $me['role'] === 'admin';
$isClient = $me['role'] === 'client';

function one($sql, $p = []){ global $pdo; $q = $pdo->prepare($sql); $q->execute($p); return $q->fetchColumn(); }

$live = "('pending_agree','active','delivered','disputed')";
if ($isAdmin) {
  $stats = [[one("SELECT COUNT(*) FROM disputes WHERE status='open'"), 'Open disputes'],
            [one("SELECT COUNT(*) FROM users WHERE role<>'admin' AND is_verified=0"), 'Unverified accounts'],
            [one("SELECT COUNT(*) FROM orders WHERE status IN $live"), 'Live orders'],
            [money(one("SELECT COALESCE(SUM(price),0) FROM orders WHERE escrow_status='held'")), 'In escrow']];
} elseif ($isClient) {
  $stats = [[one("SELECT COUNT(*) FROM posts WHERE user_id=? AND status='open'", [$uid]), 'Open requests'],
            [one("SELECT COUNT(*) FROM offers o JOIN posts p ON p.id=o.post_id WHERE p.user_id=? AND p.status='open'", [$uid]), 'Offers waiting'],
            [one("SELECT COUNT(*) FROM orders WHERE client_id=? AND status IN $live", [$uid]), 'Active orders'],
            [money(one("SELECT COALESCE(SUM(price),0) FROM orders WHERE client_id=? AND escrow_status='held'", [$uid])), 'In escrow']];
} else {
  $stats = [[one("SELECT COUNT(*) FROM offers o JOIN posts p ON p.id=o.post_id WHERE o.user_id=? AND p.status='open'", [$uid]), 'Offers sent'],
            [one("SELECT COUNT(*) FROM orders WHERE commissioner_id=? AND status IN $live", [$uid]), 'Active orders'],
            [money(one("SELECT COALESCE(SUM(price),0) FROM orders WHERE commissioner_id=? AND escrow_status='held'", [$uid])), 'To be released'],
            [money(one("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='release'", [$uid])), 'Earned']];
}

/* Active orders */
$q = $pdo->prepare("SELECT ord.id, ord.status, ord.agree_by, ord.deliver_by, ord.review_by, p.title,
    IF(ord.client_id=?, cm.username, cl.username) AS other
  FROM orders ord JOIN posts p ON p.id=ord.post_id
  JOIN users cl ON cl.id=ord.client_id JOIN users cm ON cm.id=ord.commissioner_id
  WHERE (ord.client_id=? OR ord.commissioner_id=?) AND ord.status IN $live
  ORDER BY FIELD(ord.status,'delivered','pending_agree','disputed','active'), ord.deliver_by LIMIT 5");
$q->execute([$uid, $uid, $uid]);
$orders = $q->fetchAll();
$dl = ['delivered' => ['review_by', 'Review: '], 'pending_agree' => ['agree_by', 'Reply: '], 'active' => ['deliver_by', 'Due: ']];

/* Second panel: my open requests (client) or my open offers (commissioner) */
if ($isClient) {
  $q = $pdo->prepare("SELECT p.id, p.title, p.budget_min, p.budget_max, p.deadline, s.name AS subject,
      (SELECT COUNT(*) FROM offers o WHERE o.post_id=p.id) AS n
    FROM posts p JOIN subjects s ON s.id=p.subject_id WHERE p.user_id=? AND p.status='open' ORDER BY p.created_at DESC LIMIT 5");
  $q->execute([$uid]);
} elseif (!$isAdmin) {
  $q = $pdo->prepare("SELECT p.id, p.title, p.deadline, o.price, s.name AS subject
    FROM offers o JOIN posts p ON p.id=o.post_id JOIN subjects s ON s.id=p.subject_id
    WHERE o.user_id=? AND p.status='open' ORDER BY o.created_at DESC LIMIT 5");
  $q->execute([$uid]);
}
$second = $isAdmin ? [] : $q->fetchAll();

/* Needs attention */
$att = [];
if ($isAdmin) {
  $n = (int)$stats[0][0];
  if ($n) $att[] = [plural($n, 'dispute', 'disputes') . ' to review', 'Payment stays in escrow until you decide', 'admin/'];
} else {
  if ($oid = unrated_order($uid)) $att[] = ['Rate your last order', 'Required before you can post or send offers', 'order.php?id=' . $oid];
  if ($m = messages_unread($uid)) $att[] = [plural($m, 'unread message', 'unread messages'), 'In your order chats', 'messages.php'];
  $labels = ['offer' => 'New offers on', 'accepted' => 'Offer accepted on', 'agreed' => 'Order started:', 'declined' => 'Order declined:',
             'expired' => 'No response on', 'delivered' => 'Work delivered:', 'approved' => 'Work approved:',
             'disputed' => 'Dispute opened on', 'released' => 'Payment released:', 'resolved' => 'Dispute resolved:'];
  $q = $pdo->prepare('SELECT n.type, n.order_id, p.id AS pid, p.title FROM notifications n JOIN posts p ON p.id=n.post_id
    WHERE n.user_id=? AND n.is_read=0 ORDER BY n.updated_at DESC LIMIT 4');
  $q->execute([$uid]);
  foreach ($q->fetchAll() as $r) {
    $link = in_array($r['type'], ['offer', 'declined', 'expired'], true) || !$r['order_id'] ? 'post.php?id=' . $r['pid'] : 'order.php?id=' . $r['order_id'];
    $att[] = [($labels[$r['type']] ?? 'Update on') . ' “' . $r['title'] . '”', '', $link];
  }
  $att = array_slice($att, 0, 4);
}

$s = $isAdmin ? null : user_stats($uid);
?>
<!doctype html>
<html lang="en"><head><title>Dashboard · Academic Commission</title><?php include TEMPLATE_PATH . 'head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dash.css"></head>
<body>
<?php include TEMPLATE_PATH . 'nav.php'; ?>
<main class="wrap">
  <div class="d-head">
    <div class="who"><h1>Hi, <?= e($me['username']) ?></h1>
      <span class="muted"><?= $isAdmin ? 'Admin' : ($isClient ? 'Client' : 'Commissioner') ?><?= $orders ? ' · ' . e(plural(count($orders), 'live order', 'live orders')) : '' ?></span></div>
    <?php if ($isClient): ?><a class="btn" href="create_post.php">New request</a>
    <?php elseif ($isAdmin): ?><a class="btn" href="admin/">Admin panel</a>
    <?php else: ?><a class="btn" href="forum.php?s=all">Browse requests</a><?php endif; ?>
  </div>

  <div class="stats">
    <?php foreach ($stats as [$val, $label]): ?><div class="stat"><b><?= e($val) ?></b><span><?= e($label) ?></span></div><?php endforeach; ?>
  </div>

  <div class="d-cols">
    <div class="d-main">
      <section class="d-card" aria-labelledby="h-orders">
        <div class="d-ch"><h2 id="h-orders">Active orders</h2><a href="orders.php">All orders</a></div>
        <?php if (!$orders): ?><p class="d-empty">No live orders.</p><?php else: ?>
        <ul class="d-list"><?php foreach ($orders as $o): ?>
          <li class="d-row">
            <div class="d-t"><?= e($o['title']) ?><small>with <?= e($o['other']) ?></small></div>
            <?= status_tag($o['status']) ?>
            <?php if (isset($dl[$o['status']])): [$col, $pre] = $dl[$o['status']]; ?>
              <span class="d-due" data-prefix="<?= e($pre) ?>" data-deadline="<?= e(date('c', strtotime($o[$col]))) ?>"></span>
            <?php else: ?><span class="d-due">Admin review</span><?php endif; ?>
            <a class="btn ghost" href="order.php?id=<?= (int)$o['id'] ?>">Open</a>
          </li>
        <?php endforeach; ?></ul><?php endif; ?>
      </section>

      <?php if (!$isAdmin): ?>
      <section class="d-card" aria-labelledby="h-second">
        <div class="d-ch"><h2 id="h-second"><?= $isClient ? 'Your open requests' : 'Your open offers' ?></h2><a href="forum.php?s=all">Forum</a></div>
        <?php if (!$second): ?><p class="d-empty"><?= $isClient ? 'No open requests. Post one to get offers.' : 'No open offers. Browse the forum to find work.' ?></p><?php else: ?>
        <ul class="d-list"><?php foreach ($second as $r): ?>
          <li class="d-row">
            <div class="d-t"><?= e($r['title']) ?><small><?= e($r['subject']) ?><?= $isClient ? ' · ' . e(money($r['budget_min'])) . ' – ' . e(money($r['budget_max'])) : '' ?></small></div>
            <?php if ($isClient): ?><span class="tag <?= $r['n'] ? 'ok' : '' ?>"><?= e(plural((int)$r['n'], 'offer', 'offers')) ?></span>
            <?php else: ?><span class="tag"><?= e(money($r['price'])) ?></span><?php endif; ?>
            <span class="d-due">Due <?= e(date('M j', strtotime($r['deadline']))) ?></span>
            <a class="btn ghost" href="post.php?id=<?= (int)$r['id'] ?>">View</a>
          </li>
        <?php endforeach; ?></ul><?php endif; ?>
      </section>
      <?php endif; ?>
    </div>

    <aside class="d-side">
      <section class="d-card" aria-labelledby="h-att">
        <div class="d-ch"><h2 id="h-att">Needs attention</h2><a href="inbox.php">Notifications</a></div>
        <?php if (!$att): ?><p class="d-empty">You're all caught up.</p><?php else: ?>
        <ul class="d-list"><?php foreach ($att as [$t, $d, $href]): ?>
          <li class="d-row"><span class="d-dot"></span>
            <a class="d-t" href="<?= e($href) ?>" style="color:inherit;text-decoration:none"><?= e($t) ?><?php if ($d): ?><small><?= e($d) ?></small><?php endif; ?></a></li>
        <?php endforeach; ?></ul><?php endif; ?>
      </section>

      <?php if ($s): ?>
      <section class="d-card" aria-labelledby="h-prof">
        <div class="d-ch"><h2 id="h-prof">Your profile</h2><a href="profile.php?u=<?= urlencode($me['username']) ?>">View</a></div>
        <div class="d-kv"><span>Tier</span><b><?= e($me['tier']) ?></b></div>
        <div class="d-kv"><span>Trust score</span><b><?= trust_score($s) ?> / 100</b></div>
        <div class="d-kv"><span>Rating</span><b><?= $s['rating_n'] ? number_format($s['rating'], 1) . ' (' . $s['rating_n'] . ')' : 'No ratings yet' ?></b></div>
        <div class="d-chips"><?php foreach (user_badges($s) as $b): ?><span class="chip"><?= e($b) ?></span><?php endforeach; ?></div>
      </section>
      <?php endif; ?>
    </aside>
  </div>
</main>
</body></html>