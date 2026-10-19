<?php
require_once 'helpers.php';
require_teacher();
$pageTitle = 'Students';
$active = 'teacher-student-management.php';
include 'layout-top.php';

$students = $conn->query('SELECT s.id, s.first_name, s.last_name, s.section, s.profile_picture,
        (SELECT MAX(fp.created_at) FROM fitness_profiles fp WHERE fp.student_id = s.id) AS last_update,
        (SELECT COUNT(*) FROM fitness_profiles fp WHERE fp.student_id = s.id) AS entries
        FROM students s ORDER BY s.last_name, s.first_name')->fetch_all(MYSQLI_ASSOC);
$sections = array_values(array_unique(array_column($students, 'section')));
sort($sections);
?>
<div class="page-head">
  <div><h1>Students</h1><p><?= count($students) ?> registered</p></div>
</div>

<div class="toolbar">
  <input type="search" id="search" class="grow" placeholder="Search by name…" aria-label="Search students">
  <select id="sectionFilter" aria-label="Filter by section">
    <option value="">All sections</option>
    <?php foreach ($sections as $s): ?><option><?= e($s) ?></option><?php endforeach; ?>
  </select>
</div>

<div class="table-wrap">
  <table class="table" id="studentTable">
    <thead><tr><th>Student</th><th>Section</th><th>Status</th><th>Last updated</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($students as $s): $name = trim($s['first_name'] . ' ' . $s['last_name']); ?>
      <tr data-name="<?= e(mb_strtolower($name)) ?>" data-section="<?= e($s['section']) ?>">
        <td><div class="person"><img src="<?= e(avatar_url($s['profile_picture'])) ?>" alt="" loading="lazy"><span><?= e($name) ?></span></div></td>
        <td><?= e($s['section']) ?></td>
        <td><?php $n = (int)$s['entries']; if ($n === 0): ?><span class="badge flat">Not started</span><?php elseif ($n === 1): ?><span class="badge warn">Baseline only</span><?php else: ?><span class="badge good">Tracking · <?= $n ?></span><?php endif; ?></td>
        <td><?= $s['last_update'] ? e(date('M j, Y g:i a', strtotime($s['last_update']))) : '<span class="muted">Not started</span>' ?></td>
        <td style="text-align:right"><button class="btn small view-btn" data-id="<?= (int)$s['id'] ?>"><i class="fas fa-eye"></i> View</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p id="noMatch" class="empty" hidden>No students match your search.</p>
<?php if (!$students): ?><p class="empty">No students have registered yet.</p><?php endif; ?>

<dialog id="studentModal" class="modal" aria-labelledby="m-name">
  <div class="modal-head">
    <img id="m-avatar" src="uploads/default.jpg" alt="">
    <div class="grow"><h2 id="m-name"></h2><div id="m-section"></div></div>
    <button type="button" id="closeModal" aria-label="Close"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="modal-body">
    <section>
      <h3>Skill scores</h3>
      <div class="chart-box"><canvas id="m-chart" role="img" aria-label="Pre versus post skill scores"></canvas></div>
    </section>
    <section>
      <h3>Progress over time</h3>
      <div class="chart-box short"><canvas id="m-trend" role="img" aria-label="VO2 max and BMI over time"></canvas></div>
    </section>
    <section>
      <h3>Detailed metrics</h3>
      <div class="table-wrap"><table class="table"><thead><tr><th>Metric</th><th>Pre</th><th>Post</th><th>Change</th></tr></thead>
        <tbody id="m-metrics"></tbody></table></div>
    </section>
    <section>
      <h3>Teacher remarks</h3>
      <div class="field"><label for="remarkText">Add a remark</label>
        <textarea id="remarkText" rows="2" maxlength="1000" placeholder="Write feedback for this student…"></textarea></div>
      <button class="btn success" id="saveRemark" type="button"><i class="fas fa-paper-plane"></i> Save remark</button>
      <div id="m-remarks" class="remarks-scroll" style="margin-top:1rem"></div>
    </section>
  </div>
</dialog>

<script>
const modal = document.getElementById('studentModal');
const $ = id => document.getElementById(id);
let currentId = null, chart = null;
let trend = null;
const T = chartTheme();

// Search + section filter
function applyFilter() {
  const q = $('search').value.trim().toLowerCase(), sec = $('sectionFilter').value;
  let shown = 0;
  document.querySelectorAll('#studentTable tbody tr').forEach(tr => {
    const ok = tr.dataset.name.includes(q) && (!sec || tr.dataset.section === sec);
    tr.hidden = !ok; if (ok) shown++;
  });
  $('noMatch').hidden = shown > 0 || !document.querySelector('#studentTable tbody tr');
}
$('search').addEventListener('input', applyFilter);
$('sectionFilter').addEventListener('change', applyFilter);

document.querySelectorAll('.view-btn').forEach(b => b.addEventListener('click', () => openStudent(b.dataset.id)));
$('closeModal').addEventListener('click', () => modal.close());
modal.addEventListener('click', e => { if (e.target === modal) modal.close(); });   // click backdrop

async function openStudent(id) {
  currentId = id;
  $('remarkText').value = '';
  try {
    const d = await postForm('student-fitness.php', { id });
    if (!d.success) throw new Error(d.message);
    $('m-avatar').src = d.student.avatar;
    $('m-name').textContent = d.student.first_name + ' ' + d.student.last_name;
    $('m-section').textContent = d.student.section;
    renderFitness(d.fitness);
    modal.showModal();
    loadRemarks();
  } catch (e) { toast(e.message || 'Could not load student.', 'error'); }
}

function renderFitness(f) {
  const skills = FIT.skills;
  const labels = skills.map(s => s[0].toUpperCase() + s.slice(1));
  const sets = [{ label: 'Pre-test', data: skills.map(s => +(f.pre_test?.[s] ?? 0)), backgroundColor: T.neutral, borderRadius: 4, maxBarThickness: 32 }];
  if (f.post_test) sets.push({ label: 'Latest post-test', data: skills.map(s => +(f.post_test[s] ?? 0)), backgroundColor: T.primary, borderRadius: 4, maxBarThickness: 32 });
  if (chart) chart.destroy();
  chart = new Chart($('m-chart'), { type: 'bar', data: { labels, datasets: sets },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true, max: 10, ticks: { stepSize: 2 }, grid: { color: T.line }, border: { display: false } }, x: { grid: { display: false } } } } });

  // VO2 + BMI across every entry (two axes, since the scales differ)
  const h = f.history || [];
  const xl = h.map((r, i) => (i === 0 ? 'Pre-test' : 'Post ' + i));
  if (trend) trend.destroy();
  trend = new Chart($('m-trend'), { type: 'line',
    data: { labels: xl, datasets: [
      { label: 'VO₂ Max', data: h.map(r => +r.vo2max), borderColor: T.primary, backgroundColor: T.primary, yAxisID: 'y', tension: .3, pointRadius: 4, borderWidth: 2 },
      { label: 'BMI', data: h.map(r => +r.bmi), borderColor: T.accent, backgroundColor: T.accent, yAxisID: 'y1', tension: .3, pointRadius: 4, borderWidth: 2 } ] },
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom' } },
      scales: { x: { grid: { display: false } },
        y: { position: 'left', grid: { color: T.line }, border: { display: false }, title: { display: true, text: 'VO₂ Max' } },
        y1: { position: 'right', grid: { display: false }, border: { display: false }, title: { display: true, text: 'BMI' } } } } });

  const fmts = { resting_hr: v => v.toFixed(0), max_hr: v => v.toFixed(0) };
  const rows = [['height_cm', 'Height (cm)'], ['weight_kg', 'Weight (kg)'], ['bmi', 'BMI'], ['vo2max', 'VO₂ Max'], ['resting_hr', 'Resting HR'], ['max_hr', 'Max HR']].concat(skills.map((s, i) => [s, labels[i]]));
  $('m-metrics').innerHTML = rows.map(([k, label]) => {
    const pre = f.pre_test ? +f.pre_test[k] : null, post = f.post_test ? +f.post_test[k] : null;
    const diff = (pre !== null && post !== null) ? post - pre : null;
    const fmt = v => v === null ? '<span class="muted">—</span>' : (fmts[k] ? fmts[k](v) : v.toFixed(1));
    const cls = diff === null ? '' : Math.abs(diff) < 0.05 ? 'flat' : (k === 'bmi' ? (Math.abs(post - 21.7) < Math.abs(pre - 21.7) ? 'good' : 'bad') : diff > 0 ? 'good' : 'bad');
    return `<tr><td>${esc(label)}</td><td>${fmt(pre)}</td><td>${fmt(post)}</td><td>${diff === null ? fmt(null) : `<span class="badge ${cls}">${diff > 0 ? '+' : ''}${fmts[k] ? diff.toFixed(0) : diff.toFixed(1)}</span>`}</td></tr>`;
  }).join('');
}

async function loadRemarks() {
  const box = $('m-remarks');
  try {
    const r = await postForm('get-remarks.php', { student_id: currentId });
    box.innerHTML = r.success && r.remarks.length
      ? r.remarks.map(m => `<div class="remark"><div>${esc(m.text)}</div><div class="meta">${esc(new Date(m.created_at.replace(' ', 'T')).toLocaleString())} · <b>${esc(m.teacher_username)}</b></div></div>`).join('')
      : '<p class="muted">No remarks yet.</p>';
  } catch (e) { box.innerHTML = '<p class="muted">Could not load remarks.</p>'; }
}

$('saveRemark').addEventListener('click', async () => {
  const text = $('remarkText').value.trim();
  if (!text) { toast('Please write a remark first.', 'error'); return; }
  const btn = $('saveRemark'); btn.disabled = true;
  try {
    const r = await postForm('save-remark.php', { student_id: currentId, remark_text: text });
    if (!r.success) throw new Error(r.message);
    toast('Remark saved', 'success'); $('remarkText').value = ''; loadRemarks();
  } catch (e) { toast(e.message, 'error'); }
  btn.disabled = false;
});
</script>
<?php include 'layout-bottom.php'; ?>
