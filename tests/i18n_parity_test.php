<?php
/**
 * Zero-dependency test verifying language parity between English and Arabic.
 */

define('MOODLE_INTERNAL', true);

$en_file = __DIR__ . '/../lang/en/liveclass.php';
$ar_file = __DIR__ . '/../lang/ar/liveclass.php';

if (!file_exists($en_file) || !file_exists($ar_file)) {
    echo "FAIL: Language files missing\n";
    exit(1);
}

function load_strings($path) {
    $string = [];
    include($path);
    return $string;
}

$en = load_strings($en_file);
$ar = load_strings($ar_file);

$missing_in_ar = array_diff_key($en, $ar);
$missing_in_en = array_diff_key($ar, $en);

$errors = 0;
if (!empty($missing_in_ar)) {
    echo "ERROR: Strings missing in Arabic:\n";
    foreach ($missing_in_ar as $k => $v) {
        echo "  - $k\n";
    }
    $errors++;
}

if (!empty($missing_in_en)) {
    echo "ERROR: Strings missing in English:\n";
    foreach ($missing_in_en as $k => $v) {
        echo "  - $k\n";
    }
    $errors++;
}

if ($errors === 0) {
    echo "PASS: i18n parity verified (" . count($en) . " keys aligned perfectly in EN & AR).\n";
    exit(0);
} else {
    exit(1);
}
