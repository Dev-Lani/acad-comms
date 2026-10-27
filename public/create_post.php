<?php
require dirname(__DIR__) . '/src/bootstrap.php';
$me = require_login();
if ($me['role'] !== 'client') { header('Location: forum.php'); exit; }

$subjects = $pdo->query('SELECT id, name, slug FROM subjects ORDER BY name')->fetchAll();
$types = post_types();
$err = '';
$old = ['subject_id'=>'','title'=>'','type'=>'','pages'=>'','budget_min'=>'','budget_max'=>'','deadline'=>'','description'=>''];

if (isset($_GET['s'])) {
  foreach ($subjects as $s) if ($s['slug'] === $_GET['s']) $old['subject_id'] = $s['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');
  $dl  = DateTime::createFromFormat('Y-m-d\TH:i', $old['deadline']);
  $sid = (int)$old['subject_id'];
  $min = (int)$old['budget_min'];
  $max = (int)$old['budget_max'];

  if (!csrf_ok())                                         $err = 'Your session expired. Reload the page and try again.';
  elseif (unrated_order($me['id']))                       $err = 'Rate your completed order first (My orders), then post a new request.';
  elseif (!in_array($sid, array_column($subjects, 'id'))) $err = 'Choose a subject.';
  elseif (strlen($old['title']) < 5 || strlen($old['title']) > 120) $err = 'Title must be 5 to 120 characters.';
  elseif (!in_array($old['type'], $types, true))          $err = 'Choose the type of work.';
  elseif ((int)$old['pages'] < 1 || (int)$old['pages'] > 500) $err = 'Pages must be between 1 and 500.';
  elseif ($min < 1 || $max < $min)                        $err = 'Enter a valid budget. The maximum can\'t be lower than the minimum.';
  elseif (!$dl || $dl->getTimestamp() <= time())          $err = 'Pick a deadline in the future.';
  elseif (strlen($old['description']) < 20)               $err = 'Describe the work in at least 20 characters so commissioners know what to offer.';
  else {
    $pdo->prepare('INSERT INTO posts (user_id, subject_id, title, type, pages, budget_min, budget_max, deadline, description)
                   VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$me['id'], $sid, $old['title'], $old['type'], (int)$old['pages'], $min, $max,
                   $dl->format('Y-m-d H:i:s'), $old['description']]);
    header('Location: post.php?id=' . $pdo->lastInsertId());
    exit;
  }
}
?>
<!doctype html>
<html lang="en"><head><title>New request · Academic Commission</title><?php include TEMPLATE_PATH . 'head.php'; ?></head>
<body>
<?php include TEMPLATE_PATH . 'nav.php'; ?>
<main class="wrap">
  <div class="page-head">
    <h1>New request</h1>
    <p>The clearer your request, the better the offers you get.</p>
  </div>
  <div class="panel form-panel">
    <?php if ($err): ?><div class="alert err" role="alert"><?= e($err) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

      <div class="field"><label class="lbl" for="title">Title</label>
        <input type="text" id="title" name="title" maxlength="120" value="<?= e($old['title']) ?>" required>
        <p class="hint">Example: 5-page reflection paper on Rizal's novels</p></div>

      <div class="form-grid">
        <div class="field"><label class="lbl" for="subject_id">Subject</label>
          <select id="subject_id" name="subject_id" required>
            <option value="">Choose a subject</option>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $old['subject_id']==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label class="lbl" for="type">Type of work</label>
          <select id="type" name="type" required>
            <option value="">Choose a type</option>
            <?php foreach ($types as $t): ?>
              <option <?= $old['type']===$t?'selected':'' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>

      <div class="form-grid">
        <div class="field"><label class="lbl" for="pages">Pages</label>
          <input type="number" id="pages" name="pages" min="1" max="500" value="<?= e($old['pages']) ?>" required></div>
        <div class="field"><label class="lbl" for="deadline">Deadline</label>
          <input type="datetime-local" id="deadline" name="deadline" min="<?= date('Y-m-d\TH:i') ?>" value="<?= e($old['deadline']) ?>" required></div>
      </div>

      <div class="form-grid">
        <div class="field"><label class="lbl" for="budget_min">Budget minimum (₱)</label>
          <input type="number" id="budget_min" name="budget_min" min="1" value="<?= e($old['budget_min']) ?>" required></div>
        <div class="field"><label class="lbl" for="budget_max">Budget maximum (₱)</label>
          <input type="number" id="budget_max" name="budget_max" min="1" value="<?= e($old['budget_max']) ?>" required></div>
      </div>

      <div class="field"><label class="lbl" for="description">Description</label>
        <textarea id="description" name="description" required><?= e($old['description']) ?></textarea>
        <p class="hint">Include requirements, format (APA, MLA), and any sources you need.</p></div>

      <button class="btn">Post request</button>
    </form>
  </div>
</main>
</body></html>