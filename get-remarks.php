<?php
$JSON_RESPONSE = true;
require_once 'helpers.php';
require_once 'connection.php';

if (!is_teacher() && !is_student()) json_out(['success' => false, 'message' => 'Not logged in'], 401);

// Students can only read their own remarks
$studentId = is_student() ? (int)$_SESSION['student_id'] : (int)($_POST['student_id'] ?? 0);
if ($studentId <= 0) json_out(['success' => false, 'message' => 'Invalid student ID'], 400);

$stmt = $conn->prepare('SELECT sr.id, sr.text, sr.created_at, t.username AS teacher_username
    FROM student_remarks sr JOIN teachers t ON sr.teacher_id = t.id
    WHERE sr.student_id = ? ORDER BY sr.created_at DESC');
$stmt->bind_param('i', $studentId);
$stmt->execute();
json_out(['success' => true, 'remarks' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
