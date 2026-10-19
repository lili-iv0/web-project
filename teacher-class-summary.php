<?php
require_once 'helpers.php';
require_teacher();
$pageTitle = 'Class Summary';
$active = 'teacher-class-summary.php';
include 'layout-top.php';

// ---- Filters ----
$sections = [];
foreach ($conn->query('SELECT section_code FROM sections ORDER BY section_code') as $r) $sections[] = $r['section_code'];

$classId   = $_GET['class_id'] ?? ($sections[0] ?? '');
if ($sections && !in_array($classId, $sections, true)) $classId = $sections[0];
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';
$view      = in_array($_GET['test_type'] ?? '', ['both', 'bmi', 'vo2', 'skills'], true) ? $_GET['test_type'] : 'both';
function valid_date($d) { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); }
if (!valid_date($startDate) || !valid_date($endDate)) { $startDate = $endDate = ''; }
$showBmi = in_array($view, ['both', 'bmi'], true);
$showVo2 = in_array($view, ['both', 'vo2'], true);
$showSkills = in_array($view, ['both', 'skills'], true);

// ---- Data: every student in the section + their entries (inside the date range) ----
$stmt = $conn->prepare('SELECT id, first_name, last_name FROM students WHERE section = ? ORDER BY last_name, first_name');
$stmt->bind_param('s', $classId);
$stmt->execute();
$studs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sql = 'SELECT fp.student_id, fp.bmi, fp.vo2max, fp.flexibility, fp.strength, fp.agility, fp.speed, fp.endurance, fp.created_at
        FROM fitness_profiles fp JOIN students s ON s.id = fp.student_id WHERE s.section = ?';
$types = 's'; $params = [$classId];
if ($startDate && $endDate) { $sql .= ' AND fp.created_at BETWEEN ? AND ?'; $types .= 'ss'; $params[] = "$startDate 00:00:00"; $params[] = "$endDate 23:59:59"; }
$sql .= ' ORDER BY fp.student_id, fp.created_at, fp.id';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$by = [];
foreach ($stmt->get_result() as $r) $by[(int)$r['student_id']][] = $r;

// ---- Computations ----
$skillKeys = ['flexibility', 'strength', 'agility', 'speed', 'endurance'];
$total = count($studs);
$started = 0;      // students with at least one entry in range
$compared = 0;     // students with two or more entries (first vs latest can be compared)
$sumFirst = ['bmi' => 0.0, 'vo2max' => 0.0, 'skill' => 0.0];
$sumLast  = ['bmi' => 0.0, 'vo2max' => 0.0, 'skill' => 0.0];
$sumLatestAll = ['bmi' => 0.0, 'vo2max' => 0.0];   // latest values over every student who has data
$perSkillFirst = array_fill_keys($skillKeys, 0.0);
$perSkillLast  = array_fill_keys($skillKeys, 0.0);
$bmiDist = ['Underweight' => 0, 'Normal weight' => 0, 'Overweight' => 0, 'Obese' => 0];
$vo2Dist = ['Poor' => 0, 'Fair' => 0, 'Good' => 0, 'Very good' => 0, 'Excellent' => 0];
$table = [];
$changeByStudent = [];

foreach ($studs as $s) {
    $id = (int)$s['id'];
    $es = $by[$id] ?? [];
    $n = count($es);
    $first = $n ? $es[0] : null;
    $last  = $n ? $es[$n - 1] : null;
    $name = trim($s['last_name'] . ', ' . $s['first_name']);
    $row = ['name' => $name, 'n' => $n, 'bmi' => null, 'vo2' => null, 'dBmi' => null, 'dVo2' => null, 'dSkill' => null, 'bmiTone' => null];

    if ($n >= 1) {
        $started++;
        $sumLatestAll['bmi'] += (float)$last['bmi'];
        $sumLatestAll['vo2max'] += (float)$last['vo2max'];
        $bmiDist[bmi_category((float)$last['bmi'])[0]]++;
        $vo2Dist[vo2_category((float)$last['vo2max'])[0]]++;
        $row['bmi'] = (float)$last['bmi'];
        $row['vo2'] = (float)$last['vo2max'];
    }
    if ($n >= 2) {
        $compared++;
        $sumFirst['bmi'] += (float)$first['bmi'];       $sumLast['bmi'] += (float)$last['bmi'];
        $sumFirst['vo2max'] += (float)$first['vo2max']; $sumLast['vo2max'] += (float)$last['vo2max'];
        $sumFirst['skill'] += skill_average($first);    $sumLast['skill'] += skill_average($last);
        foreach ($skillKeys as $k) { $perSkillFirst[$k] += (float)$first[$k]; $perSkillLast[$k] += (float)$last[$k]; }
        $row['dBmi'] = (float)$last['bmi'] - (float)$first['bmi'];
        $row['dVo2'] = (float)$last['vo2max'] - (float)$first['vo2max'];
        $row['dSkill'] = skill_average($last) - skill_average($first);
        $row['bmiTone'] = bmi_delta_tone($first['bmi'], $last['bmi']);
        $changeByStudent[] = ['name' => trim($s['first_name'] . ' ' . mb_substr($s['last_name'], 0, 1) . '.'), 'v' => round($row['dVo2'], 1)];
    }
    $table[] = $row;
}

function pct($a, $b) { return $b > 0 ? (int)round($a / $b * 100) : 0; }
$avgLatestBmi = $started ? $sumLatestAll['bmi'] / $started : null;
$avgLatestVo2 = $started ? $sumLatestAll['vo2max'] / $started : null;
$avg = function ($arr, $k) use ($compared) { return $compared ? $arr[$k] / $compared : null; };
$dBmi   = $compared ? ($sumLast['bmi'] - $sumFirst['bmi']) / $compared : null;
$dVo2   = $compared ? ($sumLast['vo2max'] - $sumFirst['vo2max']) / $compared : null;
$dSkill = $compared ? ($sumLast['skill'] - $sumFirst['skill']) / $compared : null;
usort($changeByStudent, function ($a, $b) { return $b['v'] <=> $a['v']; });
$changeByStudent = array_slice($changeByStudent, 0, 12);

// ---- Chart payload ----
$skillLabels = array_map('ucfirst', $skillKeys);
$bodyLabels = []; $bodyFirst = []; $bodyLast = [];
if ($showBmi) { $bodyLabels[] = 'BMI';     $bodyFirst[] = round($avg($sumFirst, 'bmi') ?? 0, 1);    $bodyLast[] = round($avg($sumLast, 'bmi') ?? 0, 1); }
if ($showVo2) { $bodyLabels[] = 'VO₂ Max'; $bodyFirst[] = round($avg($sumFirst, 'vo2max') ?? 0, 1); $bodyLast[] = round($avg($sumLast, 'vo2max') ?? 0, 1); }
$chartData = [
    'skills' => ['labels' => $skillLabels,
        'first' => array_map(function ($k) use ($perSkillFirst, $compared) { return $compared ? round($perSkillFirst[$k] / $compared, 2) : 0; }, $skillKeys),
        'last'  => array_map(function ($k) use ($perSkillLast, $compared) { return $compared ? round($perSkillLast[$k] / $compared, 2) : 0; }, $skillKeys)],
    'body' => ['labels' => $bodyLabels, 'first' => $bodyFirst, 'last' => $bodyLast],
    'bmiDist' => ['labels' => array_keys($bmiDist), 'values' => array_values($bmiDist)],
    'vo2Dist' => ['labels' => array_keys($vo2Dist), 'values' => array_values($vo2Dist)],
    'change' => ['labels' => array_column($changeByStudent, 'name'), 'values' => array_column($changeByStudent, 'v')],
];

function delta_cell($d, $tone = null, $dec = 1) {
    if ($d === null) return '<span class="muted">—</span>';
    $d = round($d, $dec);
    if ($d == 0) return '<span class="delta flat">0.0</span>';
    $cls = $tone ?? ($d > 0 ? 'good' : 'bad');
    return '<span class="delta ' . $cls . '">' . ($d > 0 ? '+' : '−') . number_format(abs($d), $dec) . '</span>';
}
?>
<div class="page-head">
  <div><h1>Class summary</h1><p>How <?= e($classId ?: 'your class') ?> is doing, comparing each student’s first and latest entry.</p></div>
</div>

<form method="get" class="card" style="margin-bottom:1.25rem">
  <div class="grid cols-4" style="align-items:end">
    <div class="field"><label for="class_id">Section</label>
      <select id="class_id" name="class_id">
        <?php foreach ($sections as $sec): ?><option value="<?= e($sec) ?>" <?= $sec === $classId ? 'selected' : '' ?>><?= e($sec) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label for="start_date">Start date</label><input type="date" id="start_date" name="start_date" value="<?= e($startDate) ?>"></div>
    <div class="field"><label for="end_date">End date</label><input type="date" id="end_date" name="end_date" value="<?= e($endDate) ?>"></div>
    <div class="field"><label for="test_type">Show</label>
      <select id="test_type" name="test_type">
        <option value="both" <?= $view === 'both' ? 'selected' : '' ?>>All metrics</option>
        <option value="bmi" <?= $view === 'bmi' ? 'selected' : '' ?>>BMI only</option>
        <option value="vo2" <?= $view === 'vo2' ? 'selected' : '' ?>>VO₂ Max only</option>
        <option value="skills" <?= $view === 'skills' ? 'selected' : '' ?>>Skill scores only</option>
      </select></div>
  </div>
  <button class="btn" type="submit"><i class="fas fa-filter"></i> Apply filters</button>
  <a class="btn secondary" href="teacher-class-summary.php">Reset</a>
  <?php if ($startDate): ?><div class="hint" style="margin-top:.6rem">Only entries from <?= e($startDate) ?> to <?= e($endDate) ?> are counted; “first” and “latest” are taken inside that range.</div><?php endif; ?>
</form>

<?php if ($total === 0): ?>
  <div class="card empty"><i class="fas fa-users"></i><p>No students are registered in <b><?= e($classId) ?></b> yet.</p></div>
<?php elseif ($started === 0): ?>
  <div class="card empty"><i class="fas fa-chart-simple"></i>
    <p>None of the <?= $total ?> students in <b><?= e($classId) ?></b> have fitness entries<?= $startDate ? ' in this date range' : '' ?> yet.</p></div>
<?php else: ?>
<div class="stack">
  <div class="kpi-grid">
    <div class="kpi"><div class="label">Students</div><div class="value"><?= $total ?></div>
      <div class="foot"><?= $started ?> with entries (<?= pct($started, $total) ?>%)</div></div>
    <div class="kpi"><div class="label">Tracking progress</div><div class="value"><?= $compared ?><small>of <?= $total ?></small></div>
      <div class="foot">2+ entries · <?= pct($compared, $total) ?>% of class</div></div>
    <?php if ($showVo2): list($vl, $vc) = vo2_category((float)$avgLatestVo2); ?>
    <div class="kpi"><div class="label">Avg VO₂ Max (latest)</div><div class="value"><?= e(number_format($avgLatestVo2, 1)) ?></div>
      <div class="foot"><span class="badge <?= $vc ?>"><?= $vl ?></span><?= $compared ? delta_html($dVo2, '', null, 1, 'avg change') : '' ?></div></div>
    <?php endif; ?>
    <?php if ($showBmi): list($bl, $bc) = bmi_category((float)$avgLatestBmi); ?>
    <div class="kpi"><div class="label">Avg BMI (latest)</div><div class="value"><?= e(number_format($avgLatestBmi, 1)) ?></div>
      <div class="foot"><span class="badge <?= $bc ?>"><?= $bl ?></span><?= $compared ? delta_html($dBmi, '', bmi_delta_tone($sumFirst['bmi'] / $compared, $sumLast['bmi'] / $compared), 1, 'avg change') : '' ?></div></div>
    <?php endif; ?>
    <?php if ($showSkills && $compared): ?>
    <div class="kpi"><div class="label">Avg skill score (latest)</div><div class="value"><?= e(number_format($sumLast['skill'] / $compared, 1)) ?><small>/ 10</small></div>
      <div class="foot"><?= delta_html($dSkill, '', null, 1, 'avg change') ?></div></div>
    <?php endif; ?>
  </div>

  <?php if ($compared === 0): ?>
    <div class="alert info">No student has two or more entries<?= $startDate ? ' in this date range' : '' ?> yet, so progress charts will appear once students log a post-test. Current-status charts are shown below.</div>
  <?php else: ?>
  <div class="grid cols-2">
    <?php if ($showSkills): ?>
    <section class="card">
      <div class="card-head"><h2>Skill scores</h2><span class="muted">class average · scale 1–10</span></div>
      <div class="chart-box"><canvas id="skillsChart" role="img" aria-label="Average skill scores, first versus latest entry"></canvas></div>
    </section>
    <?php endif; ?>
    <?php if ($showBmi || $showVo2): ?>
    <section class="card">
      <div class="card-head"><h2><?= $showBmi && $showVo2 ? 'BMI and VO₂ Max' : ($showBmi ? 'BMI' : 'VO₂ Max') ?></h2><span class="muted">class average</span></div>
      <div class="chart-box"><canvas id="bodyChart" role="img" aria-label="Average BMI and VO2 max, first versus latest entry"></canvas></div>
    </section>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="grid cols-2">
    <?php if ($showBmi): ?>
    <section class="card">
      <div class="card-head"><h2>BMI categories</h2><span class="muted">latest entry per student</span></div>
      <div class="chart-box short"><canvas id="bmiDist" role="img" aria-label="Students by BMI category"></canvas></div>
    </section>
    <?php endif; ?>
    <?php if ($showVo2): ?>
    <section class="card">
      <div class="card-head"><h2>VO₂ Max fitness levels</h2><span class="muted">latest entry per student</span></div>
      <div class="chart-box short"><canvas id="vo2Dist" role="img" aria-label="Students by VO2 max fitness level"></canvas></div>
    </section>
    <?php endif; ?>
  </div>

  <?php if ($showVo2 && $compared > 0): ?>
  <section class="card">
    <div class="card-head"><h2>VO₂ Max change by student</h2><span class="muted">top <?= count($changeByStudent) ?> · first → latest</span></div>
    <div class="chart-box" id="changeBox" style="height:<?= max(200, 30 * count($changeByStudent) + 70) ?>px"><canvas id="changeChart" role="img" aria-label="VO2 max change per student"></canvas></div>
  </section>
  <?php endif; ?>

  <section class="card">
    <div class="card-head"><h2>Students</h2><span class="muted">Δ = change since first entry</span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Student</th><th>Status</th><th class="num">Entries</th>
        <?php if ($showBmi): ?><th class="num">BMI</th><th class="num">Δ BMI</th><?php endif; ?>
        <?php if ($showVo2): ?><th class="num">VO₂ Max</th><th class="num">Δ VO₂</th><?php endif; ?>
        <?php if ($showSkills): ?><th class="num">Δ Skills</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($table as $t): ?>
        <tr><td><?= e($t['name']) ?></td>
          <td><?php if ($t['n'] === 0): ?><span class="badge flat">Not started</span><?php elseif ($t['n'] === 1): ?><span class="badge warn">Baseline only</span><?php else: ?><span class="badge good">Tracking</span><?php endif; ?></td>
          <td class="num"><?= (int)$t['n'] ?></td>
          <?php if ($showBmi): ?><td class="num"><?= $t['bmi'] === null ? '<span class="muted">—</span>' : e(number_format($t['bmi'], 1)) ?></td>
            <td class="num"><?= delta_cell($t['dBmi'], $t['bmiTone']) ?></td><?php endif; ?>
          <?php if ($showVo2): ?><td class="num"><?= $t['vo2'] === null ? '<span class="muted">—</span>' : e(number_format($t['vo2'], 1)) ?></td>
            <td class="num"><?= delta_cell($t['dVo2']) ?></td><?php endif; ?>
          <?php if ($showSkills): ?><td class="num"><?= delta_cell($t['dSkill']) ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </section>
</div>

<script>
(function () {
  const D = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const T = chartTheme();
  const grid = { color: T.line }, noGrid = { display: false };
  const el = id => document.getElementById(id);
  const grouped = (labels, a, b) => ({ labels, datasets: [
    { label: 'First entry', data: a, backgroundColor: T.neutral, borderRadius: 4, maxBarThickness: 38 },
    { label: 'Latest entry', data: b, backgroundColor: T.primary, borderRadius: 4, maxBarThickness: 38 } ] });

  if (el('skillsChart')) new Chart(el('skillsChart'), { type: 'bar', data: grouped(D.skills.labels, D.skills.first, D.skills.last),
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true, max: 10, ticks: { stepSize: 2 }, grid, border: { display: false } }, x: { grid: noGrid } } } });

  if (el('bodyChart')) new Chart(el('bodyChart'), { type: 'bar', data: grouped(D.body.labels, D.body.first, D.body.last),
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true, grid, border: { display: false } }, x: { grid: noGrid } } } });

  if (el('bmiDist')) new Chart(el('bmiDist'), { type: 'doughnut',
    data: { labels: D.bmiDist.labels, datasets: [{ data: D.bmiDist.values, backgroundColor: [T.series[3], T.primary, T.series[2], T.accent], borderColor: T.surface, borderWidth: 2 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'right' } } } });

  if (el('vo2Dist')) new Chart(el('vo2Dist'), { type: 'bar',
    data: { labels: D.vo2Dist.labels, datasets: [{ label: 'Students', data: D.vo2Dist.values, backgroundColor: T.primary, borderRadius: 4, maxBarThickness: 40 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid, border: { display: false } }, x: { grid: noGrid } } } });

  if (el('changeChart')) new Chart(el('changeChart'), { type: 'bar',
    data: { labels: D.change.labels, datasets: [{ label: 'VO₂ Max change', data: D.change.values, backgroundColor: D.change.values.map(v => v >= 0 ? T.primary : T.accent), borderRadius: 4 }] },
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
      scales: { x: { grid, border: { display: false } }, y: { grid: noGrid } } } });
})();
</script>
<?php endif; ?>
<?php include 'layout-bottom.php'; ?>
