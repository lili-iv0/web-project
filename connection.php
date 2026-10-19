<?php
// Database connection. Edit these values for your server.
mysqli_report(MYSQLI_REPORT_OFF);

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'users';

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    if (!empty($GLOBALS['JSON_RESPONSE'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    } else {
        echo 'Database connection failed. Check connection.php and make sure MySQL is running.';
    }
    exit;
}
$conn->set_charset('utf8mb4');
