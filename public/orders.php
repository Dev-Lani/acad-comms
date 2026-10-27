<?php
require dirname(__DIR__) . '/src/bootstrap.php';
$me = require_login();
$q = $pdo->prepare("SELECT ord.id, ord.status, ord.price, ord.deliver_by, p.title,
    cl.username AS client_name, cm.username AS comm_name
  FROM orders ord
  JOIN posts p ON p.id=ord.post_id
  JOIN users cl ON cl.id=ord.client_id
  JOIN users cm ON cm.id=ord.commissioner_id
  WHERE ord.client_id=? OR ord.commissioner_id=?
  ORDER BY FIELD(ord.status,'pending_agree','active') DESC, ord.accepted_at DESC");
$q->execute([$me['id'], $me['id']]);
$rows = $q->fetchAll();
?>
<!doctype html>
<html lang="en"><head><title>My orders · Academic Commission</title><?php include TEMPLATE_PATH . 'head.php'; ?></head>
<body>
<?php include TEMPLATE_PATH . 'nav.php'; ?>
<main class="wrap">
  <div class="page-head"><h1>My orders</h1><p>Accepted offers, from confirmation to delivery.</p></div>
  <section class="panel">
    <?php if (!$rows): ?>
      <p class="empty"><?= $me['role']==='client' ? 'No orders yet. Accept an offer on one of your requests to start one.'
                                                   : 'No orders yet. They appear here when a client accepts your offer.' ?></p>
    <?php else: ?>
    <ul class="rows">
      <?php foreach ($rows as $r): ?>
        <li>
          <div>
            <b><?= e($r['title']) ?></b>
            <small><?= $me['role']==='client' ? 'With ' . e($r['comm_name']) : 'For ' . e($r['client_name']) ?>.
              <?= money($r['price']) ?>, deliver by <?= e(date('M j, g:i A', strtotime($r['deliver_by']))) ?>.</small>
          </div>
          <div class="row-end"><?= status_tag($r['status']) ?><a class="btn ghost" href="order.php?id=<?= (int)$r['id'] ?>">Open</a></div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</main>
</body></html>