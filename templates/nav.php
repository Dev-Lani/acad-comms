<?php
$unread = unread_count($me['id']);
$unreadMsgs = messages_unread($me['id']);
?>
<header class="nav"><div class="nav-in">
  <a class="brand" href="<?= BASE_URL ?>dashboard.php">Academic Commission</a>
  <nav class="nav-links" aria-label="Main">
    <a href="<?= BASE_URL ?>forum.php">Forum</a>
    <?php if ($me['role'] === 'admin'): ?><a href="<?= BASE_URL ?>admin/">Admin</a><?php endif; ?>
    <?php if ($me['role'] === 'client'): ?><a href="<?= BASE_URL ?>create_post.php">New request</a><?php endif; ?>
    <a href="<?= BASE_URL ?>orders.php">My orders</a>
    <a href="<?= BASE_URL ?>transactions.php">Transactions</a>
    <a href="<?= BASE_URL ?>messages.php">Messages<?php if ($unreadMsgs): ?><span class="badge"><?= $unreadMsgs ?></span><?php endif; ?></a>
    <a href="<?= BASE_URL ?>inbox.php">Notifications<?php if ($unread): ?><span class="badge"><?= $unread ?></span><?php endif; ?></a>
  </nav>
  <div class="nav-user"><a href="<?= BASE_URL ?>profile.php?u=<?= urlencode($me['username']) ?>"><?= e($me['username']) ?></a><a class="btn ghost" href="<?= BASE_URL ?>logout.php">Log out</a></div>
</div></header>
<style>@media (max-width:820px){.nav-links{flex-wrap:wrap;gap:.4rem 1.1rem}}</style>
<script src="<?= BASE_URL ?>assets/js/countdown.js" defer></script>