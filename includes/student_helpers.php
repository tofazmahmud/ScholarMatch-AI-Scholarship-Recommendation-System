
<?php
// includes/student_helpers.php

require_once __DIR__ . '/../config/db.php';

function sm_h($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function sm_get(string $key, int $maxLength = 100): string
{
    $value = $_GET[$key] ?? '';

    if (!is_string($value)) {
        return '';
    }

    return substr(trim($value), 0, $maxLength);
}

function sm_has_active_column(PDO $pdo): bool
{
    static $hasColumn = null;

    if ($hasColumn === null) {
        $stmt = $pdo->query(
            "SHOW COLUMNS FROM scholarship LIKE 'is_active'"
        );

        $hasColumn = (bool)$stmt->fetch();
    }

    return $hasColumn;
}

// Conditions for public scholarship listings.
function sm_public_condition(PDO $pdo): string
{
    $condition = "s.deadline >= CURDATE()";

    if (sm_has_active_column($pdo)) {
        $condition .= " AND s.is_active = 1";
    }

    return $condition;
}

function sm_valid_url($url): bool
{
    if (!is_string($url)) {
        return false;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower(
        (string)parse_url($url, PHP_URL_SCHEME)
    );

    return in_array(
        $scheme,
        ['http', 'https'],
        true
    );
}
