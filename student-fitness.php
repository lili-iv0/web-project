<?php
$JSON_RESPONSE = true;
require_once 'helpers.php';
require_once 'connection.php';
api_require_teacher();

$id = (int)($_POST['id'] ?? 0);
$stmt = $conn->prepare('SELECT id, first_name, last_name, section, age, profile_picture FROM students WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) json_out(['success' => false, 'message' => 'Student not found'], 404);
$student['avatar'] = avatar_url($student['profile_picture']);
unset($student['profile_picture']);

// First entry = pre-test, latest later entry = post-test (same rule as the student pages and class summary)
$entries = student_entries($conn, $id);
list($pre, $posts, $latest) = split_entries($entries);
json_out(['success' => true, 'student' => $student, 'fitness' => ['pre_test' => $pre, 'post_test' => $latest, 'history' => $entries]]);
