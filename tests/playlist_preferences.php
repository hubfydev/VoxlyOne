<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/preferences.php';
require_once __DIR__ . '/../includes/recordings.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (['4' => 4.0, '4.1' => 4.1, '8,5' => 8.5, '9.0' => 9.0] as $input => $expected) {
    check(validate_playlist_threshold((string)$input) === $expected, 'Valid threshold: ' . $input);
}
foreach ([null, [], 4, '', '3.9', '9.1', '10', '8.01', 'NaN', 'INF', '8e0', "8\n", '4foo'] as $input) {
    check(validate_playlist_threshold($input) === null, 'Invalid threshold accepted');
}
check(playlist_score_threshold([]) === 8.0, 'Default threshold');
check(playlist_score_threshold(['playlist_score_threshold' => '4.0']) === 4.0, 'User threshold');
foreach ([4.0, 8.0, 9.0] as $threshold) {
    check(!recording_is_approved($threshold - 0.1, $threshold), 'Below threshold');
    check(!recording_is_approved($threshold, $threshold), 'Strict boundary');
    check(recording_is_approved($threshold + 0.1, $threshold), 'Above threshold');
}
check(recording_is_approved(7.0, 4.0) && !recording_is_approved(7.0, 9.0), 'Independent user cuts');
echo "Playlist preference checks passed.\n";
