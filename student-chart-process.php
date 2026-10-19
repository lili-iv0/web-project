<?php
$JSON_RESPONSE = true;
require_once 'helpers.php';
require_once 'connection.php';
api_require_student();

$rows = student_entries($conn, (int)$_SESSION['student_id']);
list($pre, $posts, $latest) = split_entries($rows);
json_out(['success' => true, 'pre' => $pre, 'posts' => $posts, 'latest_post' => $latest, 'entries' => $rows]);
