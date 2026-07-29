<?php
$file = __DIR__ . '/public_booking.php';
$content = file_get_contents($file);
// Fix the double semicolon introduced by the edit
$content = str_replace("'online','unpaid',?,?)\");;", "'online','unpaid',?,?)\");", $content);
file_put_contents($file, $content);
echo "Fixed! Occurrences replaced: " . substr_count($content, "'online','unpaid',?,?)\";") . "\n";
echo "Remaining double ;; : " . substr_count($content, ";;") . "\n";
