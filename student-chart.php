<?php
require_once 'helpers.php';
require_student();
$pageTitle = 'Dashboard';
$active = 'student-chart.php';
include 'layout-top.php';

$rows = student_entries($conn, (int)$_SESSION['student_id']);
list($pre, $posts, $latest) = split_entries($rows);
$last = $latest ?? $pre;
$hasProgress = $latest !== null;

function result_card($title, $icon, array $r) { ?>
  <section class="card">
    <div class="card-head">
      <h2><i class="fas <?= $icon ?>"></i><?= e($title) ?></h2>
      <span class="date-chip"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></span>
    </div>
    <div class="metric-grid">
      <div class="metric"><div class="label">Height</div><div class="value"><?= e(number_format((float)$r['height_cm'], 1)) ?></div><div class="unit">cm</div></div>
      <div class="metric"><div class="label">Weight</div><div class="value"><?= e(number_format((float)$r['weight_kg'], 1)) ?></div><div class="unit">kg</div></div>
      <?php list($bl, $bc) = bmi_category((float)$r['bmi']); ?>
      <div class="metric"><div class="label">BMI</div><div class="value"><?= e(number_format((float)$r['bmi'], 1)) ?></div><span class="badge <?= $bc ?>"><?= $bl ?></span></div>
      <?php list($vl, $vc) = vo2_category((float)$r['vo2max']); ?>
      <div class="metric"><div class="label">VO₂ Max</div><div class="value"><?= e(number_format((float)$r['vo2max'], 1)) ?></div><span class="badge <?= $vc ?>"><?= $vl ?></span></div>
    </div>
  </section>
<?php } ?>

<div class="page-head">
  <div>
    <h1>Welcome back, <?= e(explode(' ', $user['name'])[0]) ?></h1>
    <p>Here’s how your fitness is progressing.</p>
  </div>
  <a class="btn" href="student-post-test.php"><i class="fas fa-plus"></i> Log new results</a>
</div>

<?php if (!$pre): ?>
  <div class="card empty">
    <i class="fas fa-clipboard-list"></i>
    <p>You haven’t completed your fitness profile yet.</p>
    <a class="btn" href="student-post-test.php">Start your pre-test</a>
  </div>
<?php else:
  list($bl, $bc) = bmi_category((float)$last['bmi']);
  list($vl, $vc) = vo2_category((float)$last['vo2max']);
  $skillFirst = skill_average($pre); $skillLast = skill_average($last);
?>
  <div class="stack">
    <div class="kpi-grid">
      <div class="kpi">
        <div class="label">BMI</div>
        <div class="value"><?= e(number_format((float)$last['bmi'], 1)) ?></div>
        <div class="foot"><span class="badge <?= $bc ?>"><?= $bl ?></span>
          <?= $hasProgress ? delta_html((float)$last['bmi'] - (float)$pre['bmi'], '', bmi_delta_tone($pre['bmi'], $last['bmi'])) : delta_html(null) ?></div>
      </div>
      <div class="kpi">
        <div class="label">VO₂ Max</div>
        <div class="value"><?= e(number_format((float)$last['vo2max'], 1)) ?><small>ml/kg/min</small></div>
        <div class="foot"><span class="badge <?= $vc ?>"><?= $vl ?></span>
          <?= $hasProgress ? delta_html((float)$last['vo2max'] - (float)$pre['vo2max']) : delta_html(null) ?></div>
      </div>
      <div class="kpi">
        <div class="label">Average skill score</div>
        <div class="value"><?= e(number_format($skillLast, 1)) ?><small>/ 10</small></div>
        <div class="foot"><?= $hasProgress ? delta_html($skillLast - $skillFirst) : delta_html(null) ?></div>
      </div>
      <div class="kpi">
        <div class="label">Entries logged</div>
        <div class="value"><?= count($rows) ?></div>
        <div class="foot">Last on <?= e(date('M j, Y', strtotime($last['created_at']))) ?></div>
      </div>
    </div>

    <div class="grid cols-2">
      <?php result_card('Pre-test', 'fa-flag', $pre); ?>
      <?php if ($latest): ?>
        <?php result_card('Latest post-test', 'fa-flag-checkered', $latest); ?>
      <?php else: ?>
        <section class="card empty"><i class="fas fa-hourglass-half"></i><p>No post-test yet. Log an update to see how you’ve improved.</p>
          <a class="btn secondary" href="student-post-test.php">Update progress</a></section>
      <?php endif; ?>
    </div>

    <div class="grid charts">
      <section class="card">
        <div class="card-head"><h2>Skill scores</h2><span class="muted">scale 1–10</span></div>
        <div class="chart-box"><canvas id="skillChart" aria-label="Skill scores, pre-test versus latest" role="img"></canvas></div>
      </section>
      <section class="card">
        <div class="card-head"><h2>Teacher remarks</h2></div>
        <div id="remarksList" class="remarks-scroll"><p class="muted">Loading…</p></div>
      </section>
    </div>

    <div class="grid cols-2">
      <section class="card">
        <div class="card-head"><h2>VO₂ Max over time</h2><span class="muted">ml/kg/min</span></div>
        <div class="chart-box short"><canvas id="vo2Chart" aria-label="VO2 max over time" role="img"></canvas></div>
      </section>
      <section class="card">
        <div class="card-head"><h2>BMI over time</h2><span class="muted">shaded = normal range</span></div>
        <div class="chart-box short"><canvas id="bmiChart" aria-label="BMI over time" role="img"></canvas></div>
      </section>
    </div>

    <?php if ($hasProgress): ?>
    <section class="card">
      <div class="card-head"><h2>Skill progress over time</h2><span class="muted">scale 1–10</span></div>
      <div class="chart-box"><canvas id="skillTrend" aria-label="Each skill over time" role="img"></canvas></div>
    </section>
    <?php endif; ?>
  </div>

<script>
(async function () {
  const T = chartTheme();
  const grid = { color: T.line };
  const noGrid = { display: false };

  try {
    const res = await fetch('student-chart-process.php', { credentials: 'same-origin' });
    const d = await res.json();
    if (!d.success) throw new Error(d.message);

    const skills = FIT.skills;
    const labels = skills.map(s => s[0].toUpperCase() + s.slice(1));
    const datasets = [{ label: 'Pre-test', data: skills.map(s => +(d.pre?.[s] ?? 0)), backgroundColor: T.neutral, borderRadius: 4, maxBarThickness: 34 }];
    if (d.latest_post) datasets.push({ label: 'Latest post-test', data: skills.map(s => +(d.latest_post[s] ?? 0)), backgroundColor: T.primary, borderRadius: 4, maxBarThickness: 34 });

    new Chart(document.getElementById('skillChart'), {
      type: 'bar', data: { labels, datasets },
      options: { responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, max: 10, ticks: { stepSize: 2 }, grid, border: { display: false } }, x: { grid: noGrid } },
        plugins: { legend: { position: 'bottom' } } }
    });

    // One point per entry, oldest first
    const pts = d.entries.map((r, i) => ({ r, label: i === 0 ? 'Pre-test' : 'Post ' + i, date: new Date(r.created_at.replace(' ', 'T')) }));
    const xLabels = pts.map(p => [p.label, p.date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })]);
    const line = (label, data, color) => ({ label, data, borderColor: color, backgroundColor: color, tension: .3, pointRadius: 4, pointHoverRadius: 6, borderWidth: 2 });

    const vo2 = pts.map(p => +p.r.vo2max);
    new Chart(document.getElementById('vo2Chart'), {
      type: 'line', data: { labels: xLabels, datasets: [line('VO₂ Max', vo2, T.primary)] },
      options: { responsive: true, maintainAspectRatio: false,
        scales: { y: { grid, border: { display: false }, suggestedMin: Math.min(...vo2) - 3, suggestedMax: Math.max(...vo2) + 3, ticks: { callback: v => (+v).toFixed(0) } }, x: { grid: noGrid } },
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` VO₂ Max: ${c.raw.toFixed(1)} (${FIT.vo2Cat(c.raw)})` } } } }
    });

    // Shaded band for the normal BMI range (18.5 – 24.9)
    const band = { id: 'bmiBand', beforeDatasetsDraw(chart) {
      const y = chart.scales.y, a = chart.chartArea; if (!y) return;
      const p1 = y.getPixelForValue(24.9), p2 = y.getPixelForValue(18.5);
      const top = Math.max(Math.min(p1, p2), a.top), bottom = Math.min(Math.max(p1, p2), a.bottom);
      if (bottom <= top) return;
      chart.ctx.save(); chart.ctx.fillStyle = T.primary + '22'; chart.ctx.fillRect(a.left, top, a.width, bottom - top); chart.ctx.restore();
    } };
    const bmi = pts.map(p => +p.r.bmi);
    new Chart(document.getElementById('bmiChart'), {
      type: 'line', data: { labels: xLabels, datasets: [line('BMI', bmi, T.accent)] }, plugins: [band],
      options: { responsive: true, maintainAspectRatio: false,
        scales: { y: { grid, border: { display: false }, suggestedMin: Math.min(16, Math.min(...bmi) - 1), suggestedMax: Math.max(27, Math.max(...bmi) + 1), ticks: { callback: v => (+v).toFixed(0) } }, x: { grid: noGrid } },
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` BMI: ${c.raw.toFixed(1)} (${FIT.bmiCat(c.raw)})` } } } }
    });

    const trend = document.getElementById('skillTrend');
    if (trend) {
      new Chart(trend, {
        type: 'line',
        data: { labels: xLabels, datasets: skills.map((s, i) => line(labels[i], pts.map(p => +p.r[s]), T.series[i])) },
        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
          scales: { y: { min: 0, max: 10, ticks: { stepSize: 2 }, grid, border: { display: false } }, x: { grid: noGrid } },
          plugins: { legend: { position: 'bottom' } } }
      });
    }
  } catch (err) {
    toast('Could not load charts: ' + err.message, 'error');
  }

  // Remarks (text is escaped to avoid XSS)
  const list = document.getElementById('remarksList');
  try {
    const r = await (await fetch('get-remarks.php', { method: 'POST', credentials: 'same-origin' })).json();
    if (r.success && r.remarks.length) {
      list.innerHTML = r.remarks.map(m => `<div class="remark"><div>${esc(m.text)}</div>
        <div class="meta">${esc(new Date(m.created_at.replace(' ', 'T')).toLocaleString())} · <b>${esc(m.teacher_username)}</b></div></div>`).join('');
    } else {
      list.innerHTML = '<p class="muted">No remarks yet.</p>';
    }
  } catch (e) { list.innerHTML = '<p class="muted">Could not load remarks.</p>'; }
})();
</script>
<?php endif; ?>
<?php include 'layout-bottom.php'; ?>
