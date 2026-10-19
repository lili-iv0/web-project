<?php
$JSON_RESPONSE = true;
require_once 'helpers.php';
require_once 'connection.php';
api_require_teacher();

$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$studentId = (int)($_POST['student_id'] ?? 0);
$text      = trim($_POST['remark_text'] ?? '');

if ($studentId <= 0 || $text === '') json_out(['success' => false, 'message' => 'Please write a remark first.']);
if (mb_strlen($text) > 1000) json_out(['success' => false, 'message' => 'Remark is too long (max 1000 characters).']);

$chk = $conn->prepare('SELECT 1 FROM students WHERE id = ?');
$chk->bind_param('i', $studentId);
$chk->execute();
if (!$chk->get_result()->fetch_row()) json_out(['success' => false, 'message' => 'Student not found.'], 404);

$stmt = $conn->prepare('INSERT INTO student_remarks (student_id, teacher_id, text) VALUES (?, ?, ?)');
$stmt->bind_param('iis', $studentId, $teacherId, $text);
if ($stmt->execute()) json_out(['success' => true, 'message' => 'Remark saved']);
json_out(['success' => false, 'message' => 'Failed to save remark'], 500);
