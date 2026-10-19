<?php
require_once 'helpers.php';
require_student();
$pageTitle = 'My Progress';
$active = 'student-post-result.php';
include 'layout-top.php';

$entries = student_entries($conn, (int)$_SESSION['student_id']);   // oldest first
list($pre, $posts, $latest) = split_entries($entries);
$p = $latest ?? $pre;                        // what we show as "current"
$history = array_reverse($entries, true);    // newest first, keeping each entry's index for its label
?>
<div class="page-head">
  <div><h1>My progress</h1><p>Your latest results and full history.</p></div>
  <a class="btn" href="student-post-test.php"><i class="fas fa-plus"></i> Update progress</a>
</div>

<?php if (!$p): ?>
  <div class="card empty"><i class="fas fa-clipboard-list"></i><p>No results yet.</p>
    <a class="btn" href="student-post-test.php">Create your fitness profile</a></div>
<?php else:
  list($bl, $bc) = bmi_category((float)$p['bmi']);
  list($vl, $vc) = vo2_category((float)$p['vo2max']);
  $hasProgress = $latest !== null;
?>
  <div class="stack">
    <div class="grid cols-3">
      <section class="card stat-big"><h2>BMI</h2><div class="value"><?= e(number_format((float)$p['bmi'], 1)) ?></div>
        <div class="muted">Body Mass Index</div>
        <div class="foot" style="margin-top:.5rem"><span class="badge <?= $bc ?>"><?= $bl ?></span>
          <?= $hasProgress ? delta_html((float)$p['bmi'] - (float)$pre['bmi'], '', bmi_delta_tone($pre['bmi'], $p['bmi'])) : '' ?></div></section>
      <section class="card stat-big"><h2>VO₂ Max</h2><div class="value"><?= e(number_format((float)$p['vo2max'], 1)) ?></div>
        <div class="muted">Cardiovascular fitness (ml/kg/min)</div>
        <div class="foot" style="margin-top:.5rem"><span class="badge <?= $vc ?>"><?= $vl ?></span>
          <?= $hasProgress ? delta_html((float)$p['vo2max'] - (float)$pre['vo2max']) : '' ?></div></section>
      <section class="card">
        <h2>Fitness assessment</h2>
        <?php foreach (['flexibility', 'strength', 'agility', 'speed', 'endurance'] as $k):
          $v = (float)$p[$k]; $d = $hasProgress ? $v - (float)$pre[$k] : null; ?>
          <div class="score-row"><span><?= ucfirst($k) ?></span>
            <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="10" aria-valuenow="<?= e($v) ?>"><span style="width:<?= max(0, min(100, $v * 10)) ?>%"></span></div>
            <span style="text-align:right"><b><?= e(rtrim(rtrim(number_format($v, 1), '0'), '.')) ?></b><?php if ($d !== null && round($d, 1) != 0): ?><span class="d delta <?= $d > 0 ? 'good' : 'bad' ?>"><?= ($d > 0 ? '+' : '−') . e(rtrim(rtrim(number_format(abs($d), 1), '0'), '.')) ?></span><?php endif; ?></span></div>
        <?php endforeach; ?>
        <?php if ($hasProgress): ?><div class="hint">Small figures show change since your first entry.</div><?php endif; ?>
      </section>
    </div>

    <section class="card">
      <div class="card-head"><h2>History</h2><span class="muted"><?= count($entries) ?> <?= count($entries) === 1 ? 'entry' : 'entries' ?></span></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Entry</th><th class="num">Height</th><th class="num">Weight</th><th class="num">BMI</th><th class="num">VO₂ Max</th><th class="num">Avg skill</th></tr></thead>
        <tbody>
        <?php foreach ($history as $i => $r): ?>
          <tr><td><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
              <td><?= $i === 0 ? 'Pre-test' : 'Post-test ' . (int)$i ?></td>
              <td class="num"><?= e(number_format((float)$r['height_cm'], 1)) ?> cm</td>
              <td class="num"><?= e(number_format((float)$r['weight_kg'], 1)) ?> kg</td>
              <td class="num"><?= e(number_format((float)$r['bmi'], 1)) ?></td>
              <td class="num"><?= e(number_format((float)$r['vo2max'], 1)) ?></td>
              <td class="num"><?= e(number_format(skill_average($r), 1)) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
  </div>
<?php endif; ?>
<?php include 'layout-bottom.php'; ?>
