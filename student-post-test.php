<?php
require_once 'helpers.php';
require_student();
$pageTitle = 'Update Progress';
$active = 'student-post-test.php';
include 'layout-top.php';

$sid = (int)$_SESSION['student_id'];
$stmt = $conn->prepare('SELECT * FROM fitness_profiles WHERE student_id = ? ORDER BY created_at DESC, id DESC LIMIT 1');
$stmt->bind_param('i', $sid);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$isUpdate = $p !== null;

// Default age to the age on the student's profile (editable from the profile icon)
$a = $conn->prepare('SELECT age FROM students WHERE id = ?');
$a->bind_param('i', $sid);
$a->execute();
$ageDefault = $a->get_result()->fetch_row()[0] ?? ($p['age'] ?? '');
function val($p, $k) { return e($p[$k] ?? ''); }
?>
<div class="page-head">
  <div>
    <h1><?= $isUpdate ? 'Update your progress' : 'Create your fitness profile' ?></h1>
    <p><?= $isUpdate ? 'Enter your latest measurements – we’ll keep your history so you can see how you improve.' : 'Enter your starting measurements. This becomes your pre-test.' ?></p>
  </div>
</div>

<form id="fitnessForm" novalidate>
  <div class="grid cols-2">
    <section class="card">
      <h2><i class="fas fa-weight-scale"></i> Body measurements</h2>
      <div class="form-grid">
        <div class="field"><label for="height_cm">Height (cm)</label>
          <input type="number" inputmode="decimal" step="0.1" min="50" max="250" id="height_cm" name="height_cm" value="<?= val($p, 'height_cm') ?>" required></div>
        <div class="field"><label for="weight_kg">Weight (kg)</label>
          <input type="number" inputmode="decimal" step="0.1" min="10" max="300" id="weight_kg" name="weight_kg" value="<?= val($p, 'weight_kg') ?>" required></div>
      </div>
      <div class="result-box" id="bmiResult" aria-live="polite"></div>
    </section>

    <section class="card">
      <h2><i class="fas fa-heart-pulse"></i> Heart rate (VO₂ Max)</h2>
      <div class="form-grid">
        <div class="field"><label for="age">Age</label>
          <input type="number" inputmode="numeric" min="5" max="100" id="age" name="age" value="<?= e($ageDefault) ?>" required></div>
        <div class="field"><label for="resting_hr">Resting HR (bpm)</label>
          <input type="number" inputmode="numeric" min="30" max="150" id="resting_hr" name="resting_hr" value="<?= val($p, 'resting_hr') ?>" required></div>
        <div class="field"><label for="max_hr">Maximum HR (bpm)</label>
          <input type="number" inputmode="numeric" min="80" max="250" id="max_hr" name="max_hr" value="<?= val($p, 'max_hr') ?>" required>
          <div class="hint" id="hrHint"></div></div>
      </div>
      <div class="result-box" id="vo2Result" aria-live="polite"></div>
    </section>
  </div>

  <section class="card" style="margin-top:1.25rem">
    <h2><i class="fas fa-dumbbell"></i> Fitness assessment</h2>
    <p class="muted">Rate each area from 1 (low) to 10 (high).</p>
    <div class="grid cols-3" style="gap:0 1.25rem">
      <?php foreach (['flexibility' => 'Flexibility', 'strength' => 'Strength', 'agility' => 'Agility', 'speed' => 'Speed', 'endurance' => 'Endurance'] as $k => $label): ?>
        <div class="field"><label for="<?= $k ?>"><?= $label ?></label>
          <input type="number" inputmode="numeric" min="1" max="10" step="1" id="<?= $k ?>" name="<?= $k ?>" value="<?= val($p, $k) ?>" required></div>
      <?php endforeach; ?>
    </div>
    <div id="formError" class="alert error" role="alert" hidden></div>
    <button type="submit" class="btn" id="saveBtn"><i class="fas fa-floppy-disk"></i> <?= $isUpdate ? 'Save update' : 'Save fitness profile' ?></button>
  </section>
</form>

<script>
const f = document.getElementById('fitnessForm');
const $ = id => document.getElementById(id);

function preview() {
  const h = parseFloat($('height_cm').value), w = parseFloat($('weight_kg').value);
  $('bmiResult').innerHTML = (h > 0 && w > 0)
    ? `BMI <b>${FIT.bmi(w, h).toFixed(1)}</b> · ${FIT.bmiCat(FIT.bmi(w, h))}` : '';
  const r = parseInt($('resting_hr').value), m = parseInt($('max_hr').value);
  const a = parseInt($('age').value);
  $('hrHint').textContent = a > 0 ? 'Age-based estimate: about ' + (220 - a) + ' bpm' : '';
  $('vo2Result').innerHTML = (r > 0 && m > 0)
    ? `Estimated VO₂ Max <b>${FIT.vo2(r, m).toFixed(1)}</b> ml/kg/min · ${FIT.vo2Cat(FIT.vo2(r, m))}` : '';
}
f.addEventListener('input', preview);
preview();

f.addEventListener('submit', async e => {
  e.preventDefault();
  const err = $('formError'); err.hidden = true;
  if (!f.checkValidity()) { f.reportValidity(); return; }
  if (parseInt($('max_hr').value) <= parseInt($('resting_hr').value)) {
    err.textContent = 'Maximum heart rate must be higher than resting heart rate.'; err.hidden = false; return;
  }
  const btn = $('saveBtn'); btn.disabled = true;
  try {
    const d = await postForm('save-fitness-profile.php', new FormData(f));
    if (!d.success) throw new Error(d.message);
    toast('Saved! Redirecting…', 'success');
    setTimeout(() => location.href = 'student-chart.php', 700);
  } catch (ex) {
    err.textContent = ex.message; err.hidden = false; btn.disabled = false;
  }
});
</script>
<?php include 'layout-bottom.php'; ?>
