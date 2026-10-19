<?php
// Updates the logged-in user's own profile (students and teachers).
$JSON_RESPONSE = true;
require_once 'helpers.php';
require_once 'connection.php';
api_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['success' => false, 'message' => 'Invalid request'], 405);

function fail($msg, $code = 200) { json_out(['success' => false, 'message' => $msg], $code); }

$student = is_student();

// ---- Load the current row ----
if ($student) {
    $selfId = (int)$_SESSION['student_id'];
    $st = $conn->prepare('SELECT id, first_name, last_name, username, age, section, password, profile_picture FROM students WHERE id = ?');
    $st->bind_param('i', $selfId);
} else {
    $selfId = (int)($_SESSION['teacher_id'] ?? 0);
    if ($selfId > 0) {
        $st = $conn->prepare('SELECT id, first_name, username, password, profile_picture FROM teachers WHERE id = ?');
        $st->bind_param('i', $selfId);
    } else {
        $st = $conn->prepare('SELECT id, first_name, username, password, profile_picture FROM teachers WHERE username = ?');
        $st->bind_param('s', $_SESSION['username']);
    }
}
$st->execute();
$row = $st->get_result()->fetch_assoc();
if (!$row) fail('Account not found.', 404);
$selfId = (int)$row['id'];
if (!$student) $_SESSION['teacher_id'] = $selfId;

// ---- Read + validate input ----
$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$username  = trim($_POST['username'] ?? '');
if ($firstName === '' || mb_strlen($firstName) > 100) fail('Please enter your first name.');
if ($student && ($lastName === '' || mb_strlen($lastName) > 100)) fail('Please enter your last name.');
if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
    fail('Username must be 3-30 characters (letters, numbers, . _ -).');
}

$age = (int)($row['age'] ?? 0);
$section = $row['section'] ?? '';
if ($student) {
    $age = filter_var($_POST['age'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 100]]);
    if ($age === false) fail('Please enter a valid age (5-100).');
    $section = trim($_POST['section'] ?? '');
    $c = $conn->prepare('SELECT 1 FROM sections WHERE section_code = ?');
    $c->bind_param('s', $section);
    $c->execute();
    if (!$c->get_result()->fetch_row()) fail('Please choose a valid section.');
}

// Username must stay unique across students and teachers (ignoring this account)
if ($student) {
    $c = $conn->prepare('SELECT 1 FROM students WHERE username = ? AND id <> ? UNION SELECT 1 FROM teachers WHERE username = ?');
    $c->bind_param('sis', $username, $selfId, $username);
} else {
    $c = $conn->prepare('SELECT 1 FROM students WHERE username = ? UNION SELECT 1 FROM teachers WHERE username = ? AND id <> ?');
    $c->bind_param('ssi', $username, $username, $selfId);
}
$c->execute();
if ($c->get_result()->fetch_row()) fail('That username is already taken.');

// Optional password change
$passwordToStore = $row['password'];
$current = (string)($_POST['current_password'] ?? '');
$new     = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');
if ($new !== '' || $confirm !== '' || $current !== '') {
    if ($current === '') fail('Enter your current password to set a new one.');
    if (!check_password($current, $row['password'])) fail('Your current password is incorrect.');
    if (strlen($new) < 6) fail('New password must be at least 6 characters.');
    if ($new !== $confirm) fail('New passwords do not match.');
    $passwordToStore = password_hash($new, PASSWORD_DEFAULT);
}

// Optional new profile picture
$oldPicture = $row['profile_picture'];
$picture = $oldPicture;
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['profile_picture'];
    if ($f['error'] !== UPLOAD_ERR_OK) fail('Profile picture upload failed.');
    if ($f['size'] > 2 * 1024 * 1024) fail('Profile picture must be under 2 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) fail('Profile picture must be a JPG, PNG, WEBP or GIF image.');
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $name)) fail('Could not save the profile picture.');
    $picture = $name;
}

// ---- Save ----
if ($student) {
    $up = $conn->prepare('UPDATE students SET first_name = ?, last_name = ?, username = ?, age = ?, section = ?, password = ?, profile_picture = ? WHERE id = ?');
    $up->bind_param('sssisssi', $firstName, $lastName, $username, $age, $section, $passwordToStore, $picture, $selfId);
} else {
    $up = $conn->prepare('UPDATE teachers SET first_name = ?, username = ?, password = ?, profile_picture = ? WHERE id = ?');
    $up->bind_param('ssssi', $firstName, $username, $passwordToStore, $picture, $selfId);
}
if (!$up->execute()) {
    if ($picture !== $oldPicture && $picture) @unlink(__DIR__ . '/uploads/' . basename($picture));
    fail('Could not save your changes. Please try again.', 500);
}

$_SESSION['username'] = $username;

// Remove the old picture file if nobody else uses it (never the default image)
if ($picture !== $oldPicture && $oldPicture && basename($oldPicture) !== 'default.jpg') {
    $c = $conn->prepare('SELECT (SELECT COUNT(*) FROM students WHERE profile_picture = ?) + (SELECT COUNT(*) FROM teachers WHERE profile_picture = ?)');
    $c->bind_param('ss', $oldPicture, $oldPicture);
    $c->execute();
    if ((int)$c->get_result()->fetch_row()[0] === 0) @unlink(__DIR__ . '/uploads/' . basename($oldPicture));
}

json_out(['success' => true, 'message' => 'Profile updated']);
