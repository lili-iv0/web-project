<?php
require_once 'helpers.php';
require_once 'connection.php';
// Already signed in? Go straight to the right dashboard.
if (is_teacher()) { header('Location: teacher-class-summary.php'); exit; }
if (is_student()) { header('Location: student-chart.php'); exit; }
$sections = [];
if ($res = $conn->query('SELECT section_code FROM sections ORDER BY section_code')) {
    foreach ($res as $r) $sections[] = $r['section_code'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · Fitness Tracker</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <div class="auth">
    <aside class="brand">
      <h1>Physical Fitness Tracker</h1>
      <p id="brandText">Welcome back! Let’s keep building a healthy life.</p>
      <img id="brandImg" src="Fitness stats-rafiki.svg" alt="">
    </aside>

    <section class="panel">
      <div class="tabs" role="tablist">
        <button type="button" role="tab" id="tabIn" aria-selected="true" aria-controls="formIn">Sign in</button>
        <button type="button" role="tab" id="tabUp" aria-selected="false" aria-controls="formUp">Register</button>
      </div>

      <form id="formIn" class="form" novalidate>
        <h2>Sign in</h2>
        <p class="sub">Use your student or teacher account.</p>
        <div class="msg" id="msgIn" role="alert" hidden></div>
        <div class="field"><label for="login_username">Username</label>
          <input type="text" id="login_username" name="username" autocomplete="username" required autofocus></div>
        <div class="field"><label for="login_password">Password</label>
          <div class="pw"><input type="password" id="login_password" name="password" autocomplete="current-password" required>
            <button type="button" class="toggle-pw" aria-label="Show password"><i class="fas fa-eye"></i></button></div></div>
        <button class="btn" type="submit">Sign in</button>
      </form>

      <form id="formUp" class="form" enctype="multipart/form-data" novalidate hidden>
        <h2>Create account</h2>
        <p class="sub">Students register here. Takes less than a minute.</p>
        <div class="msg" id="msgUp" role="alert" hidden></div>
        <div class="row">
          <div class="field"><label for="reg_firstName">First name</label><input type="text" id="reg_firstName" name="firstName" autocomplete="given-name" required></div>
          <div class="field"><label for="reg_lastName">Last name</label><input type="text" id="reg_lastName" name="lastName" autocomplete="family-name" required></div>
        </div>
        <div class="field"><label for="reg_username">Username</label><input type="text" id="reg_username" name="username" autocomplete="username" minlength="3" maxlength="30" required></div>
        <div class="row">
          <div class="field"><label for="reg_age">Age</label><input type="number" id="reg_age" name="age" min="5" max="100" inputmode="numeric" required></div>
          <div class="field"><label for="reg_section">Section</label>
            <select id="reg_section" name="section" required>
              <option value="" disabled selected>Select…</option>
              <?php foreach ($sections as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="row">
          <div class="field"><label for="reg_password">Password</label>
            <div class="pw"><input type="password" id="reg_password" name="password" autocomplete="new-password" minlength="6" required>
              <button type="button" class="toggle-pw" aria-label="Show password"><i class="fas fa-eye"></i></button></div></div>
          <div class="field"><label for="reg_confirmPassword">Confirm password</label>
            <input type="password" id="reg_confirmPassword" name="confirmPassword" autocomplete="new-password" required></div>
        </div>
        <div class="field"><label for="reg_profilePicture">Profile picture <span style="color:var(--muted)">(optional)</span></label>
          <input type="file" id="reg_profilePicture" name="profilePicture" accept="image/*">
          <img class="avatar-preview" id="preview" alt="Preview"></div>
        <button class="btn" type="submit">Create account</button>
      </form>
    </section>
  </div>

<script>
const $ = id => document.getElementById(id);
const imgs = { in: ['Fitness stats-rafiki.svg', 'Welcome back! Let’s keep building a healthy life.'], up: ['Fitness tracker-rafiki.svg', 'New here? Join us and start tracking your fitness journey.'] };

function show(mode) {
  const up = mode === 'up';
  $('formIn').hidden = up; $('formUp').hidden = !up;
  $('tabIn').setAttribute('aria-selected', !up); $('tabUp').setAttribute('aria-selected', up);
  $('brandImg').src = imgs[mode][0]; $('brandText').textContent = imgs[mode][1];
  (up ? $('reg_firstName') : $('login_username')).focus();
}
$('tabIn').onclick = () => show('in');
$('tabUp').onclick = () => show('up');
if (location.hash === '#register') show('up');

document.querySelectorAll('.toggle-pw').forEach(b => b.addEventListener('click', () => {
  const input = b.parentElement.querySelector('input');
  const reveal = input.type === 'password';
  input.type = reveal ? 'text' : 'password';
  b.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
  b.innerHTML = '<i class="fas ' + (reveal ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
}));

$('reg_profilePicture').addEventListener('change', e => {
  const f = e.target.files[0], p = $('preview');
  if (f) { p.src = URL.createObjectURL(f); p.style.display = 'block'; } else p.style.display = 'none';
});

function fail(el, text) { el.textContent = text; el.hidden = false; }

async function submit(form, url, msg, btnText) {
  msg.hidden = true;
  const btn = form.querySelector('.btn'); btn.disabled = true; const old = btn.textContent; btn.textContent = btnText;
  try {
    const res = await fetch(url, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
    const d = await res.json();
    if (d.success) { location.href = d.redirect; return; }
    fail(msg, d.message || 'Something went wrong.');
  } catch (e) { fail(msg, 'Could not reach the server. Please try again.'); }
  btn.disabled = false; btn.textContent = old;
}

$('formIn').addEventListener('submit', e => {
  e.preventDefault();
  if (!$('login_username').value.trim() || !$('login_password').value) return fail($('msgIn'), 'Please enter your username and password.');
  submit(e.target, 'login-process.php', $('msgIn'), 'Signing in…');
});

$('formUp').addEventListener('submit', e => {
  e.preventDefault();
  const f = e.target, msg = $('msgUp');
  if (!f.checkValidity()) { f.reportValidity(); return; }
  if ($('reg_password').value !== $('reg_confirmPassword').value) return fail(msg, 'Passwords do not match.');
  submit(f, 'register-process.php', msg, 'Creating account…');
});
</script>
</body>
</html>
