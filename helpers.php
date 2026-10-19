<?php
// Shared helpers: session, auth guards, escaping, categories, avatars, profile data.

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

function e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function json_out(array $data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function is_teacher() { return ($_SESSION['user_type'] ?? '') === 'teachers'; }
function is_student() { return ($_SESSION['user_type'] ?? '') === 'student' && !empty($_SESSION['student_id']); }

// Page guards (redirect to login)
function require_student() {
    if (!is_student()) { header('Location: login.php'); exit; }
}
function require_teacher() {
    if (!is_teacher()) { header('Location: login.php'); exit; }
}
// API guards (JSON error)
function api_require_student() {
    if (!is_student()) json_out(['success' => false, 'message' => 'Not logged in'], 401);
}
function api_require_teacher() {
    if (!is_teacher()) json_out(['success' => false, 'message' => 'Teacher access only'], 403);
}
function api_require_login() {
    if (!is_student() && !is_teacher()) json_out(['success' => false, 'message' => 'Not logged in'], 401);
}

function bmi_category($bmi) {
    if ($bmi < 18.5) return ['Underweight', 'warn'];
    if ($bmi < 25)   return ['Normal weight', 'good'];
    if ($bmi < 30)   return ['Overweight', 'warn'];
    return ['Obese', 'bad'];
}
function vo2_category($v) {
    if ($v >= 60) return ['Excellent', 'good'];
    if ($v >= 50) return ['Very good', 'good'];
    if ($v >= 40) return ['Good', 'info'];
    if ($v >= 30) return ['Fair', 'warn'];
    return ['Poor', 'bad'];
}

// Average of the five 1-10 skill scores in one fitness_profiles row.
function skill_average(array $r) {
    $keys = ['flexibility', 'strength', 'agility', 'speed', 'endurance'];
    $sum = 0.0;
    foreach ($keys as $k) $sum += (float)($r[$k] ?? 0);
    return $sum / count($keys);
}

// Returns a safe URL for a stored profile picture file name.
function avatar_url($file) {
    $file = basename((string)$file);
    if ($file !== '' && is_file(__DIR__ . '/uploads/' . $file)) {
        return 'uploads/' . rawurlencode($file);
    }
    return 'uploads/default.jpg';
}

// Checks a password against a stored value. Supports bcrypt hashes and
// legacy plain-text passwords (which can be upgraded to a hash on success).
function check_password($input, $stored, &$newHash = null) {
    $newHash = null;
    $info = password_get_info($stored);
    if ($info['algo'] !== null && $info['algo'] !== 0) {
        if (!password_verify($input, $stored)) return false;
        if (password_needs_rehash($stored, PASSWORD_DEFAULT)) $newHash = password_hash($input, PASSWORD_DEFAULT);
        return true;
    }
    if (hash_equals((string)$stored, (string)$input)) { $newHash = password_hash($input, PASSWORD_DEFAULT); return true; }
    return false;
}

// Cached info about whoever is logged in (sidebar + profile dialog).
function current_user(mysqli $conn) {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = ['id' => 0, 'name' => 'User', 'sub' => '', 'avatar' => 'uploads/default.jpg', 'role' => '',
              'first_name' => '', 'last_name' => '', 'username' => '', 'age' => '', 'section' => ''];
    if (is_student()) {
        $st = $conn->prepare('SELECT id, first_name, last_name, username, age, section, profile_picture FROM students WHERE id = ?');
        $st->bind_param('i', $_SESSION['student_id']);
        $st->execute();
        if ($r = $st->get_result()->fetch_assoc()) {
            $cache = ['id' => (int)$r['id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'sub' => $r['section'],
                      'avatar' => avatar_url($r['profile_picture']), 'role' => 'student',
                      'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'username' => $r['username'],
                      'age' => $r['age'], 'section' => $r['section']];
        }
    } elseif (is_teacher()) {
        $tid = (int)($_SESSION['teacher_id'] ?? 0);
        if ($tid > 0) {
            $st = $conn->prepare('SELECT id, first_name, username, profile_picture FROM teachers WHERE id = ?');
            $st->bind_param('i', $tid);
        } else {
            $st = $conn->prepare('SELECT id, first_name, username, profile_picture FROM teachers WHERE username = ?');
            $st->bind_param('s', $_SESSION['username']);
        }
        $st->execute();
        $r = $st->get_result()->fetch_assoc() ?: [];
        $display = trim((string)($r['first_name'] ?? '')) !== '' ? $r['first_name'] : ($r['username'] ?? $_SESSION['username']);
        $cache = ['id' => (int)($r['id'] ?? 0), 'name' => $display, 'sub' => 'Teacher',
                  'avatar' => avatar_url($r['profile_picture'] ?? ''), 'role' => 'teacher',
                  'first_name' => $r['first_name'] ?? '', 'last_name' => '', 'username' => $r['username'] ?? $_SESSION['username'],
                  'age' => '', 'section' => ''];
    }
    return $cache;
}

// ---- Fitness entries ----
// All entries for a student, oldest first. The first one is the baseline (pre-test);
// every later one is a progress entry (post-test). Using position (not test_id) keeps
// the student pages, teacher modal and class summary all computing the same thing.
function student_entries(mysqli $conn, $studentId) {
    $studentId = (int)$studentId;
    $st = $conn->prepare('SELECT id, test_id, height_cm, weight_kg, bmi, vo2max, flexibility, strength, agility, speed, endurance, resting_hr, max_hr, age, created_at
        FROM fitness_profiles WHERE student_id = ? ORDER BY created_at ASC, id ASC');
    $st->bind_param('i', $studentId);
    $st->execute();
    return $st->get_result()->fetch_all(MYSQLI_ASSOC);
}
function split_entries(array $rows) {
    $pre = $rows[0] ?? null;
    $posts = array_slice($rows, 1);
    return [$pre, $posts, $posts ? end($posts) : null];
}

// Small "change since first entry" label. $tone: 'good' | 'bad' | null (null = up is good).
function delta_html($d, $unit = '', $tone = null, $dec = 1, $suffix = 'since first entry') {
    if ($d === null) return '<span>Baseline entry</span>';
    $d = round((float)$d, $dec);
    if ($d == 0) return '<span class="delta flat">No change</span><span>' . e($suffix) . '</span>';
    $cls = $tone ?? ($d > 0 ? 'good' : 'bad');
    $icon = $d > 0 ? 'fa-arrow-up' : 'fa-arrow-down';
    return '<span class="delta ' . $cls . '"><i class="fas ' . $icon . '"></i> ' . number_format(abs($d), $dec) . e($unit) . '</span><span>' . e($suffix) . '</span>';
}
// BMI is "good" when it moves toward the middle of the normal range (about 21.7).
function bmi_delta_tone($first, $last) {
    $mid = 21.7;
    $a = abs((float)$first - $mid); $b = abs((float)$last - $mid);
    if (abs($a - $b) < 0.05) return 'flat';
    return $b < $a ? 'good' : 'bad';
}
