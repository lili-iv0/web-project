<?php
$JSON_RESPONSE = true;
require_once 'helpers.php';
require_once 'connection.php';
api_require_student();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['success' => false, 'message' => 'Invalid request'], 405);

$sid = (int)$_SESSION['student_id'];

function num($key, $min, $max, $label) {
    $v = $_POST[$key] ?? '';
    if ($v === '' || !is_numeric($v) || $v < $min || $v > $max) {
        json_out(['success' => false, 'message' => "$label must be between $min and $max."]);
    }
    return (float)$v;
}

$height    = num('height_cm', 50, 250, 'Height (cm)');
$weight    = num('weight_kg', 10, 300, 'Weight (kg)');
$age       = (int)num('age', 5, 100, 'Age');
$restingHr = (int)num('resting_hr', 30, 150, 'Resting heart rate');
$maxHr     = (int)num('max_hr', 80, 250, 'Maximum heart rate');
if ($maxHr <= $restingHr) json_out(['success' => false, 'message' => 'Maximum heart rate must be higher than resting heart rate.']);

$flex      = num('flexibility', 1, 10, 'Flexibility');
$strength  = num('strength', 1, 10, 'Strength');
$agility   = num('agility', 1, 10, 'Agility');
$speed     = num('speed', 1, 10, 'Speed');
$endurance = num('endurance', 1, 10, 'Endurance');

// Calculated on the server so values can't be tampered with
$bmi    = round($weight / pow($height / 100, 2), 1);
$vo2max = round(15.3 * ($maxHr / $restingHr), 1);

// First entry is the pre-test (1); every later entry is a post-test (2)
$st = $conn->prepare('SELECT COUNT(*) FROM fitness_profiles WHERE student_id = ?');
$st->bind_param('i', $sid);
$st->execute();
$testId = ((int)$st->get_result()->fetch_row()[0] === 0) ? 1 : 2;

$stmt = $conn->prepare('INSERT INTO fitness_profiles
    (student_id, test_id, height_cm, weight_kg, bmi, vo2max, flexibility, strength, agility, speed, endurance, created_at, age, resting_hr, max_hr, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW())');
$stmt->bind_param('iiddddddddd' . 'iii', $sid, $testId, $height, $weight, $bmi, $vo2max, $flex, $strength, $agility, $speed, $endurance, $age, $restingHr, $maxHr);

if ($stmt->execute()) {
    json_out(['success' => true, 'test_id' => $testId, 'bmi' => $bmi, 'vo2max' => $vo2max]);
}
json_out(['success' => false, 'message' => 'Could not save your profile. Please try again.'], 500);
