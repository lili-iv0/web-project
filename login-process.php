<?php
$JSON_RESPONSE = true;
require_once 'helpers.php';
require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['success' => false, 'message' => 'Invalid request'], 405);

$username = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');
if ($username === '' || $password === '') json_out(['success' => false, 'message' => 'Please fill in all fields.']);

// check_password() lives in helpers.php

// 1) Teacher / admin
$stmt = $conn->prepare('SELECT id, username, password FROM teachers WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
if ($t = $stmt->get_result()->fetch_assoc()) {
    if (check_password($password, $t['password'], $newHash)) {
        if ($newHash) {
            $up = $conn->prepare('UPDATE teachers SET password = ? WHERE id = ?');
            $up->bind_param('si', $newHash, $t['id']);
            $up->execute();
        }
        session_regenerate_id(true);
        $_SESSION = ['username' => $t['username'], 'user_type' => 'teachers', 'teacher_id' => (int)$t['id']];
        json_out(['success' => true, 'role' => 'teachers', 'redirect' => 'teacher-class-summary.php']);
    }
}

// 2) Student
$stmt = $conn->prepare('SELECT id, username, password FROM students WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
if ($s = $stmt->get_result()->fetch_assoc()) {
    if (check_password($password, $s['password'], $newHash)) {
        if ($newHash) {
            $up = $conn->prepare('UPDATE students SET password = ? WHERE id = ?');
            $up->bind_param('si', $newHash, $s['id']);
            $up->execute();
        }
        session_regenerate_id(true);
        $_SESSION = ['username' => $s['username'], 'user_type' => 'student', 'student_id' => (int)$s['id']];
        json_out(['success' => true, 'role' => 'student', 'redirect' => 'student-chart.php']);
    }
}

json_out(['success' => false, 'message' => 'Invalid username or password.']);
