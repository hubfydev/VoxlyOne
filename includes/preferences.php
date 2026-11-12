<?php
declare(strict_types=1);

/** Corte individual das playlists; a progressão na prática é independente. */
function playlist_score_threshold(array $user): float
{
    return (float)($user['playlist_score_threshold'] ?? 8.0);
}

/** Aceita apenas valores de 4 a 9, com no máximo uma casa decimal. */
function validate_playlist_threshold(mixed $value): ?float
{
    if (!is_string($value) || !preg_match('/^[4-9](?:[.,][0-9])?$/D', $value)) {
        return null;
    }
    $score = (float)str_replace(',', '.', $value);
    return $score <= 9.0 ? $score : null;
}
