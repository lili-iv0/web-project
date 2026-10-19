<?php
$JSON_RESPONSE = true;
require_once 'helpers.php';
require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['success' => false, 'message' => 'Invalid request'], 405);

$firstName = trim($_POST['firstName'] ?? '');
$lastName  = trim($_POST['lastName'] ?? '');
$username  = trim($_POST['username'] ?? '');
$age       = filter_var($_POST['age'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 100]]);
$section   = trim($_POST['section'] ?? '');
$password  = (string)($_POST['password'] ?? '');
$confirm   = (string)($_POST['confirmPassword'] ?? '');

if ($firstName === '' || $lastName === '' || $username === '' || $section === '' || $password === '') {
    json_out(['success' => false, 'message' => 'Please fill in all required fields.']);
}
if ($age === false) json_out(['success' => false, 'message' => 'Please enter a valid age.']);
if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
    json_out(['success' => false, 'message' => 'Username must be 3-30 characters (letters, numbers, . _ -).']);
}
if (strlen($password) < 6) json_out(['success' => false, 'message' => 'Password must be at least 6 characters.']);
if ($password !== $confirm) json_out(['success' => false, 'message' => 'Passwords do not match.']);

// Section must exist
$st = $conn->prepare('SELECT 1 FROM sections WHERE section_code = ?');
$st->bind_param('s', $section);
$st->execute();
if (!$st->get_result()->fetch_row()) json_out(['success' => false, 'message' => 'Please choose a valid section.']);

// Username must be unique across students and teachers
$st = $conn->prepare('SELECT 1 FROM students WHERE username = ? UNION SELECT 1 FROM teachers WHERE username = ?');
$st->bind_param('ss', $username, $username);
$st->execute();
if ($st->get_result()->fetch_row()) json_out(['success' => false, 'message' => 'That username is already taken.']);

// Optional profile picture (stored in uploads/ with a random name)
$profilePicture = null;
if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['profilePicture'];
    if ($f['error'] !== UPLOAD_ERR_OK) json_out(['success' => false, 'message' => 'Profile picture upload failed.']);
    if ($f['size'] > 2 * 1024 * 1024) json_out(['success' => false, 'message' => 'Profile picture must be under 2 MB.']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) json_out(['success' => false, 'message' => 'Profile picture must be a JPG, PNG, WEBP or GIF image.']);
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
    $name = bin2hex((function_exists('random_bytes') ? random_bytes(8) : openssl_random_pseudo_bytes(8))) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $name)) {
        json_out(['success' => false, 'message' => 'Could not save the profile picture.']);
    }
    $profilePicture = $name;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO students (first_name, last_name, username, age, section, password, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('sssisss', $firstName, $lastName, $username, $age, $section, $hash, $profilePicture);

if (!$stmt->execute()) json_out(['success' => false, 'message' => 'Could not create your account. Please try again.'], 500);

session_regenerate_id(true);
$_SESSION = ['username' => $username, 'user_type' => 'student', 'student_id' => (int)$conn->insert_id];
json_out(['success' => true, 'message' => 'Registration successful', 'redirect' => 'student-post-test.php']);
