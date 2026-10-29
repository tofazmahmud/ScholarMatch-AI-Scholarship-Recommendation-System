
<?php
// includes/matching_engine.php

// Normalize text for consistent comparisons.
function sm_normalize($value): string
{
    $value = trim((string)($value ?? ''));

    $compact = preg_replace('/\s+/u', ' ', $value);

    return strtolower($compact ?? $value);
}

// Normalize common degree names.
function sm_degree_normalize($value): string
{
    $degree = sm_normalize($value);

    $aliases = [
        "bachelor's" => 'bachelor',
        'bachelors'   => 'bachelor',
        "master's"   => 'master',
        'masters'     => 'master',
        'ph.d.'       => 'phd',
        'doctorate'   => 'phd'
    ];

    return $aliases[$degree] ?? $degree;
}

// Split comma-separated values.
function sm_csv_options(string $value): array
{
    return array_values(array_filter(
        array_map('trim', explode(',', $value)),
        static fn($item) => $item !== ''
    ));
}

// Evaluate one requirement.
//
// Possible outcomes:
// pass, fail, unknown, manual
function sm_check_requirement(
    array $rule,
    array $profile
): string {

    $certainty = sm_normalize(
        $rule['certainty'] ?? ''
    );

    // Ambiguous requirements cannot be verified
    // automatically.
    if ($certainty !== 'explicit') {
        return 'manual';
    }

    $type = sm_normalize(
        $rule['requirement_type'] ?? ''
    );

    $operator = strtoupper(trim(
        (string)($rule['comparison_operator'] ?? '')
    ));

    $needed = trim(
        (string)($rule['value'] ?? '')
    );

    if ($type === 'other') {
        return 'manual';
    }

    if ($needed === '') {
        return 'unknown';
    }

    // GPA REQUIREMENT
    if ($type === 'gpa') {

        $actual = $profile['gpa'] ?? null;

        if (
            $actual === null ||
            $actual === '' ||
            !is_numeric($actual) ||
            !is_numeric($needed) ||
            (float)$needed < 0 ||
            (float)$needed > 4
        ) {
            return 'unknown';
        }

        $a = (float)$actual;
        $b = (float)$needed;

        $matches = match ($operator) {
            '='  => abs($a - $b) < 0.00001,
            '!=' => abs($a - $b) >= 0.00001,
            '>'  => $a > $b,
            '>=' => $a >= $b,
            '<'  => $a < $b,
            '<=' => $a <= $b,
            default => null
        };

        return $matches === null
            ? 'unknown'
            : ($matches ? 'pass' : 'fail');
    }

    // SKILL REQUIREMENT
    if ($type === 'skills') {

        if ($operator !== 'CONTAINS') {
            return 'unknown';
        }

        $skills = trim(
            (string)($profile['skills'] ?? '')
        );

        if ($skills === '') {
            return 'unknown';
        }

        $items = array_map(
            'sm_normalize',
            sm_csv_options($skills)
        );

        return in_array(
            sm_normalize($needed),
            $items,
            true
        ) ? 'pass' : 'fail';
    }

    // DEGREE OR FIELD REQUIREMENT
    if ($type === 'degree' || $type === 'field') {

        $actual = trim(
            (string)($profile[$type] ?? '')
        );

        if ($actual === '') {
            return 'unknown';
        }

        $normalize = $type === 'degree'
            ? 'sm_degree_normalize'
            : 'sm_normalize';

        $current = $normalize($actual);

        $wanted = array_map(
            $normalize,
            sm_csv_options($needed)
        );

        if (!$wanted) {
            return 'unknown';
        }

        $matches = match ($operator) {

            '=' => count($wanted) === 1
                ? $current === $wanted[0]
                : null,

            '!=' => count($wanted) === 1
                ? $current !== $wanted[0]
                : null,

            'IN' => in_array(
                $current,
                $wanted,
                true
            ),

            'NOT IN' => !in_array(
                $current,
                $wanted,
                true
            ),

            default => null
        };

        return $matches === null
            ? 'unknown'
            : ($matches ? 'pass' : 'fail');
    }

    // Unsupported requirements need manual checking.
    return 'unknown';
}

// Determine final eligibility status.
function sm_eligibility_status(
    array $rules,
    array $profile
): string {

    if (!$rules) {
        return 'Unknown/Manual Check';
    }

    $hasUnknown = false;
    $hasManual = false;
    $passed = 0;

    foreach ($rules as $rule) {

        $outcome = sm_check_requirement(
            $rule,
            $profile
        );

        if ($outcome === 'fail') {
            return 'Not Eligible';
        }

        if ($outcome === 'unknown') {
            $hasUnknown = true;

        } elseif ($outcome === 'manual') {
            $hasManual = true;

        } elseif ($outcome === 'pass') {
            $passed++;
        }
    }

    if ($hasUnknown) {
        return 'Unknown/Manual Check';
    }

    if ($hasManual) {
        return $passed > 0
            ? 'Potentially Eligible'
            : 'Unknown/Manual Check';
    }

    return $passed > 0
        ? 'Eligible'
        : 'Unknown/Manual Check';
}

// Calculate preference score, separately from eligibility.
//
// Score is based on the preferences actually supplied.
// Empty preferences are ignored.
function sm_preference_score(
    array $preference,
    array $scholarship
): array {

    $weights = [
        'degree_level' => 25,
        'field' => 20,
        'country' => 25,
        'funding_type' => 20
    ];

    $possible = 0;
    $earned = 0;

    foreach ($weights as $field => $weight) {

        $desired = trim(
            (string)($preference[$field] ?? '')
        );

        if (
            $desired === '' ||
            sm_normalize($desired) === 'any'
        ) {
            continue;
        }

        $possible += $weight;

        $offered = trim(
            (string)($scholarship[$field] ?? '')
        );

        $normalize = $field === 'degree_level'
            ? 'sm_degree_normalize'
            : 'sm_normalize';

        $options = array_map(
            $normalize,
            sm_csv_options($offered)
        );

        if (
            in_array('any', $options, true) ||
            in_array('all', $options, true) ||
            in_array($normalize($desired), $options, true)
        ) {
            $earned += $weight;
        }
    }

    // Deadline preference has a maximum weight of 10.
    $from = trim(
        (string)($preference['deadline_from'] ?? '')
    );

    $to = trim(
        (string)($preference['deadline_to'] ?? '')
    );

    if ($from !== '' || $to !== '') {

        $possible += 10;

        $deadline = (string)(
            $scholarship['deadline'] ?? ''
        );

        if (
            $deadline !== '' &&
            ($from === '' || $deadline >= $from) &&
            ($to === '' || $deadline <= $to)
        ) {
            $earned += 10;
        }
    }

    if ($possible === 0) {
        return [null, 'No Preferences Set'];
    }

    $score = round(
        $earned / $possible * 100,
        2
    );

    if ($score >= 75) {
        $label = 'Strong Preference Fit';

    } elseif ($score >= 50) {
        $label = 'Moderate Preference Fit';

    } else {
        $label = 'Low Preference Fit';
    }

    return [$score, $label];
}
