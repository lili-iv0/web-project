<?php
// Shared page shell. Set $pageTitle and $active before including.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connection.php';
$user = current_user($conn);
$isTeacher = is_teacher();
$nav = $isTeacher
    ? [['teacher-class-summary.php', 'fa-chart-column', 'Class Summary'], ['teacher-student-management.php', 'fa-users', 'Students']]
    : [['student-chart.php', 'fa-chart-line', 'Dashboard'], ['student-post-result.php', 'fa-clipboard-check', 'My Progress'], ['student-post-test.php', 'fa-pen-to-square', 'Update Progress']];

$profileSections = [];
if (!$isTeacher) {
    if ($res = $conn->query('SELECT section_code FROM sections ORDER BY section_code')) {
        foreach ($res as $r) $profileSections[] = $r['section_code'];
    }
    if ($user['section'] !== '' && !in_array($user['section'], $profileSections, true)) $profileSections[] = $user['section'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Fitness Tracker') ?> · Fitness Tracker</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="app.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body data-role="<?= e($user['role']) ?>">
  <header class="topbar">
    <button type="button" id="navToggle" class="icon-btn" aria-label="Open menu"><i class="fas fa-bars"></i></button>
    <span class="t-title"><?= e($pageTitle ?? 'Fitness Tracker') ?></span>
    <button type="button" class="avatar-btn js-open-profile" aria-label="Edit profile">
      <img src="<?= e($user['avatar']) ?>" alt="" onerror="this.onerror=null;this.src='uploads/default.jpg'">
    </button>
  </header>
  <div class="scrim" id="scrim"></div>

  <aside class="sidebar" aria-label="Main navigation">
    <div class="brand-mark"><i class="fas fa-heart-pulse"></i><span>Fitness Tracker</span></div>

    <button type="button" class="profile-section js-open-profile" aria-label="Edit profile">
      <img src="<?= e($user['avatar']) ?>" alt="" id="sideAvatar" onerror="this.onerror=null;this.src='uploads/default.jpg'">
      <span class="who">
        <span class="profile-name"><?= e($user['name']) ?></span>
        <span class="profile-sub"><?= e($user['sub']) ?></span>
      </span>
      <i class="fas fa-pen edit-ico" aria-hidden="true"></i>
    </button>

    <nav class="nav">
      <?php foreach ($nav as $item): list($href, $icon, $label) = $item; ?>
        <a href="<?= $href ?>" class="<?= ($active ?? '') === $href ? 'active' : '' ?>" <?= ($active ?? '') === $href ? 'aria-current="page"' : '' ?>>
          <i class="fas <?= $icon ?>"></i><?= $label ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="signout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i>Sign out</a></div>
  </aside>

  <!-- Profile editor (opens from the sidebar / top-bar avatar) -->
  <dialog id="profileModal" class="modal profile-modal" aria-labelledby="pf-title">
    <form id="profileForm" enctype="multipart/form-data" novalidate>
      <div class="modal-head">
        <h2 id="pf-title">Edit profile</h2>
        <button type="button" class="icon-btn" id="pfClose" aria-label="Close"><i class="fas fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="pf-photo">
          <img id="pfPreview" src="<?= e($user['avatar']) ?>" alt="Profile picture preview" onerror="this.onerror=null;this.src='uploads/default.jpg'">
          <div>
            <label class="btn secondary small" for="pfPicture"><i class="fas fa-camera"></i> Change photo</label>
            <input type="file" id="pfPicture" name="profile_picture" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
            <div class="hint">JPG, PNG, WEBP or GIF · up to 2 MB</div>
          </div>
        </div>

        <div id="pfError" class="alert error" role="alert" hidden></div>

        <div class="form-grid">
          <div class="field"><label for="pf_first_name"><?= $isTeacher ? 'Display name' : 'First name' ?></label>
            <input type="text" id="pf_first_name" name="first_name" value="<?= e($user['first_name']) ?>" maxlength="100" autocomplete="given-name" required></div>
          <?php if (!$isTeacher): ?>
            <div class="field"><label for="pf_last_name">Last name</label>
              <input type="text" id="pf_last_name" name="last_name" value="<?= e($user['last_name']) ?>" maxlength="100" autocomplete="family-name" required></div>
          <?php endif; ?>
          <div class="field"><label for="pf_username">Username</label>
            <input type="text" id="pf_username" name="username" value="<?= e($user['username']) ?>" minlength="3" maxlength="30" autocomplete="username" required></div>
          <?php if (!$isTeacher): ?>
            <div class="field"><label for="pf_age">Age</label>
              <input type="number" id="pf_age" name="age" value="<?= e($user['age']) ?>" min="5" max="100" inputmode="numeric" required></div>
            <div class="field"><label for="pf_section">Section</label>
              <select id="pf_section" name="section" required>
                <?php foreach ($profileSections as $s): ?><option value="<?= e($s) ?>" <?= $s === $user['section'] ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
              </select></div>
          <?php endif; ?>
        </div>

        <details class="pf-password">
          <summary>Change password</summary>
          <div class="form-grid" style="margin-top:.9rem">
            <div class="field" style="grid-column:1/-1"><label for="pf_current">Current password</label>
              <input type="password" id="pf_current" name="current_password" autocomplete="current-password"></div>
            <div class="field"><label for="pf_new">New password</label>
              <input type="password" id="pf_new" name="new_password" minlength="6" autocomplete="new-password"></div>
            <div class="field"><label for="pf_confirm">Confirm new password</label>
              <input type="password" id="pf_confirm" name="confirm_password" autocomplete="new-password"></div>
          </div>
          <div class="hint">Leave these blank to keep your current password.</div>
        </details>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn secondary" id="pfCancel">Cancel</button>
        <button type="submit" class="btn" id="pfSave"><i class="fas fa-floppy-disk"></i> Save changes</button>
      </div>
    </form>
  </dialog>

  <script src="app.js"></script>

  <main class="main">
