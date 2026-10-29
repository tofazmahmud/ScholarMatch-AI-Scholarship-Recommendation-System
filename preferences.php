
<?php
require_once __DIR__ . '/includes/student_session.php';
require_once __DIR__ . '/includes/student_helpers.php';

function pref_input(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

function valid_preference_date(string $date): bool
{
    if ($date === '') {
        return true;
    }

    $parsed = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date;
}

// Load profile belonging to this browser session.
$stmt = $pdo->prepare(
    "SELECT id, degree, field
     FROM profile
     WHERE session_id = ?
     LIMIT 1"
);

$stmt->execute([
    $_SESSION['sm_student_token']
]);

$profile = $stmt->fetch();

if (!$profile) {
    header('Location: profile.php');
    exit;
}

$profileId = (int)$profile['id'];

$degrees = ['Bachelor', 'Master', 'PhD', 'Any'];

$fundingTypes = [
    'Fully Funded',
    'Partially Funded',
    'Tuition Waiver',
    'Other'
];

$data = [
    'degree_level' => '',
    'field' => '',
    'country' => '',
    'funding_type' => '',
    'deadline_from' => '',
    'deadline_to' => ''
];

$errors = [];

// Load previously saved preferences.
$stmt = $pdo->prepare(
    "SELECT *
     FROM preference
     WHERE profile_id = ?
     LIMIT 1"
);

$stmt->execute([$profileId]);
$existing = $stmt->fetch();

if ($existing) {
    foreach ($data as $key => $unused) {
        $data[$key] = (string)($existing[$key] ?? '');
    }
}

// Save preferences.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!sm_verify_student_csrf(
        $_POST['csrf_token'] ?? null
    )) {
        http_response_code(403);
        exit('Invalid request. Refresh the page.');
    }

    foreach ($data as $key => $unused) {
        $data[$key] = pref_input($key);
    }

    if (
        $data['degree_level'] !== '' &&
        !in_array(
            $data['degree_level'],
            $degrees,
            true
        )
    ) {
        $errors[] = 'Invalid degree preference.';
    }

    if (
        $data['funding_type'] !== '' &&
        !in_array(
            $data['funding_type'],
            $fundingTypes,
            true
        )
    ) {
        $errors[] = 'Invalid funding preference.';
    }

    if (strlen($data['country']) > 100) {
        $errors[] = 'Country name is too long.';
    }

    if (strlen($data['field']) > 150) {
        $errors[] = 'Field name is too long.';
    }

    foreach (['deadline_from', 'deadline_to'] as $key) {

        if (!valid_preference_date($data[$key])) {
            $errors[] = 'Invalid deadline date.';
        }
    }

    if (
        $data['deadline_from'] !== '' &&
        $data['deadline_to'] !== '' &&
        $data['deadline_from'] > $data['deadline_to']
    ) {
        $errors[] =
            'End date cannot be before start date.';
    }

    if (empty($errors)) {

        try {
            $pdo->beginTransaction();

            // Enforce one preference set per profile.
            $stmt = $pdo->prepare(
                "INSERT INTO preference (profile_id)
                 VALUES (?)
                 ON DUPLICATE KEY UPDATE
                     id = LAST_INSERT_ID(id)"
            );

            $stmt->execute([$profileId]);

            $preferenceId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "UPDATE preference
                 SET degree_level = ?,
                     field = ?,
                     country = ?,
                     funding_type = ?,
                     deadline_from = ?,
                     deadline_to = ?
                 WHERE id = ?
                   AND profile_id = ?"
            );

            $stmt->execute([
                $data['degree_level'] ?: null,
                $data['field'] ?: null,
                $data['country'] ?: null,
                $data['funding_type'] ?: null,
                $data['deadline_from'] ?: null,
                $data['deadline_to'] ?: null,
                $preferenceId,
                $profileId
            ]);

            $pdo->commit();

            header('Location: preferences.php?saved=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] =
                'Unable to save preferences. Try again.';
        }
    }
}

$success = isset($_GET['saved'])
    && $_GET['saved'] === '1';

$profileSaved = isset($_GET['profile_saved'])
    && $_GET['profile_saved'] === '1';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Preferences - ScholarMatch</title>

    <link rel="stylesheet"
          href="assets/css/student.css">
<link rel="stylesheet" href="assets/css/v2.css">
</head>

<body>

<header class="site-header">

    <div class="nav-container">

        <a href="index.php" class="brand">
            🎓 ScholarMatch
        </a>

        <nav>
            <a href="index.php">Home</a>
            <a href="explore.php">Explore</a>
            <a href="profile.php">My Profile</a>
            <a href="preferences.php" class="active">
                Preferences
            </a>
			<a href="find_matches.php">Find My Matches</a>
<a href="recommendations.php">Recommendations</a>
        <a href="dashboard.php">Dashboard</a><a href="semantic_search.php">AI Search</a><a href="bookmarks.php">Saved</a><a href="application_tracker.php">Tracker</a><a href="deadline_calendar.php">Deadlines</a>
</nav>

    </div>

</header>

<main class="student-page">

    <div class="student-page-heading">

        <span class="student-eyebrow">
            Scholarship Preferences
        </span>

        <h1>Choose Your Scholarship Preferences</h1>

        <p>
            Tell ScholarMatch which degree, country,
            field and funding type you prefer.
        </p>

    </div>

    <?php if ($profileSaved): ?>

        <div class="student-success">
            Academic profile saved successfully!
            Now select your scholarship preferences.
        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="student-success">
            Preferences saved successfully!
        </div>

    <?php endif; ?>

    <?php if (!empty($errors)): ?>

        <div class="student-error" role="alert">

            <?php foreach ($errors as $error): ?>
                <p><?= sm_h($error) ?></p>
            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <form
        action="preferences.php"
        method="POST"
        class="student-form-card"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= sm_h(sm_student_csrf()) ?>"
        >

        <div class="form-section-title">

            <h2>Your Preferred Opportunities</h2>

            <p>
                All fields are optional.
                Leave a field blank for no preference.
            </p>

        </div>

        <div class="student-form-grid">

            <div class="student-field">

                <label for="degree_level">
                    Preferred Degree Level
                </label>

                <select
                    id="degree_level"
                    name="degree_level"
                >

                    <option value="">
                        Any Degree Level
                    </option>

                    <?php foreach ($degrees as $degree): ?>

                        <option
                            value="<?= sm_h($degree) ?>"
                            <?= $data['degree_level'] === $degree
                                ? 'selected' : '' ?>
                        >
                            <?= sm_h($degree) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <small>
                    The degree you want to pursue,
                    not your completed degree.
                </small>

            </div>

            <div class="student-field">

                <label for="country">
                    Preferred Country
                </label>

                <input
                    id="country"
                    name="country"
                    type="text"
                    maxlength="100"
                    placeholder="e.g. Canada"
                    value="<?= sm_h($data['country']) ?>"
                >

            </div>

            <div class="student-field">

                <label for="field">
                    Preferred Field
                </label>

                <input
                    id="field"
                    name="field"
                    type="text"
                    maxlength="150"
                    placeholder="e.g. Computer Science"
                    value="<?= sm_h($data['field']) ?>"
                >

            </div>

            <div class="student-field">

                <label for="funding_type">
                    Funding Type
                </label>

                <select
                    id="funding_type"
                    name="funding_type"
                >

                    <option value="">
                        Any Funding Type
                    </option>

                    <?php foreach ($fundingTypes as $funding): ?>

                        <option
                            value="<?= sm_h($funding) ?>"
                            <?= $data['funding_type'] === $funding
                                ? 'selected' : '' ?>
                        >
                            <?= sm_h($funding) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="student-field">

                <label for="deadline_from">
                    Deadline From
                </label>

                <input
                    id="deadline_from"
                    name="deadline_from"
                    type="date"
                    value="<?= sm_h($data['deadline_from']) ?>"
                >

            </div>

            <div class="student-field">

                <label for="deadline_to">
                    Deadline To
                </label>

                <input
                    id="deadline_to"
                    name="deadline_to"
                    type="date"
                    value="<?= sm_h($data['deadline_to']) ?>"
                >

            </div>

        </div>

        <div class="student-form-actions">

            <button type="submit" class="primary-btn">
                Save Preferences
            </button>

            <a href="profile.php" class="student-text-link">
                ← Edit Profile
            </a>

        </div>

    </form>

    <div class="student-info-card">

        <h3>What's Next?</h3>

        <p>
            Your profile and preferences will be used
            by our upcoming rule-based matching engine
            to evaluate scholarships and generate
            personalized recommendations.
        </p>

        <a href="find_matches.php" class="primary-btn">
    Find My Matches →
</a>

    </div>

</main>

<footer class="site-footer">
    <p>© <?= date('Y') ?> ScholarMatch</p>
</footer>

</body>
</html>
