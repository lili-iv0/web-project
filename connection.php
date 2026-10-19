
<?php
mysqli_report(MYSQLI_REPORT_OFF);

$DB_HOST = 'sql209.infinityfree.com';
$DB_USER = 'if0_43215197';
$DB_PASS = 'KLyk5aiP1S0qMXs';
$DB_NAME = 'if0_43215197_fitnessTracker';

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);

    if (!empty($GLOBALS['JSON_RESPONSE'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
    } else {
        echo 'Database connection failed. Check your hosting database settings.';
    }

    exit;
}

$conn->set_charset('utf8mb4');
?>
