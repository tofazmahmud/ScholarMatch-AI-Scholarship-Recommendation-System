
<?php
require_once __DIR__ . '/includes/student_session.php';
require_once __DIR__ . '/includes/student_helpers.php';
require_once __DIR__ . '/includes/matching_engine.php';
require_once __DIR__ . '/includes/v2.php';

$token = $_SESSION['sm_student_token'];

// Load current student's profile.
$stmt = $pdo->prepare(
    'SELECT * FROM profile WHERE session_id = ? LIMIT 1'
);

$stmt->execute([$token]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: profile.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!sm_verify_student_csrf(
        $_POST['csrf_token'] ?? null
    )) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page.');
    }

    try {
        $pdo->beginTransaction();

        // Load student's preferences.
        $stmt = $pdo->prepare(
            'SELECT * FROM preference
             WHERE profile_id = ?
             LIMIT 1'
        );

        $stmt->execute([(int)$profile['id']]);

        $preference = $stmt->fetch() ?: [];

        // Load active scholarships and their requirements.
        $rows = $pdo->query(
            "SELECT
                s.id,
                s.name,
                s.provider,
                s.country,
                s.degree_level,
                s.field,
                s.funding_type,
                s.deadline,

                r.id AS requirement_id,
                r.requirement_type,
                r.comparison_operator,
                r.value,
                r.certainty

             FROM scholarship s

             LEFT JOIN requirement r
                ON r.scholarship_id = s.id

             WHERE s.is_active = 1
               AND s.deadline >= CURDATE()

             ORDER BY s.id ASC, r.id ASC"
        )->fetchAll();

        // Group requirement rows under their scholarships.
        $scholarships = [];

        foreach ($rows as $row) {

            $id = (int)$row['id'];

            if (!isset($scholarships[$id])) {

                $scholarships[$id] = [
                    'details' => $row,
                    'rules' => []
                ];
            }

            if ($row['requirement_id'] !== null) {

                $scholarships[$id]['rules'][] = [
                    'requirement_type' =>
                        $row['requirement_type'],

                    'comparison_operator' =>
                        $row['comparison_operator'],

                    'value' => $row['value'],

                    'certainty' => $row['certainty']
                ];
            }
        }

        // Remove old results before recalculating.
        $delete = $pdo->prepare(
            'DELETE FROM recommendation
             WHERE profile_id = ?'
        );

        $delete->execute([(int)$profile['id']]);

        // Prepare recommendation insertion.
        $insert = $pdo->prepare(
            'INSERT INTO recommendation
                (
                    profile_id,
                    scholarship_id,
                    eligibility_status,
                    match_score,
                    suitability_label,
                    ai_blurb
                )
             VALUES (?, ?, ?, ?, ?, NULL)'
        );

        foreach ($scholarships as $item) {

            $scholarship = $item['details'];
            $rules = $item['rules'];

            // Determine eligibility.
            $status = sm_eligibility_status(
                $rules,
                $profile
            );

            // Calculate preference-based ranking.
            [$score, $label] = sm_preference_score(
                $preference,
                $scholarship
            );

            // Save result.
            $insert->execute([
                (int)$profile['id'],
                (int)$scholarship['id'],
                $status,
                $score,
                $label
            ]);
        }

        $pdo->commit();

        // Record a fingerprint of the data used for this match run.
        try { sm_v2_record_matches($pdo,(int)$profile['id']); }
        catch (Throwable $e) { error_log('Match snapshot: '.$e->getMessage()); }

        header('Location: recommendations.php');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'ScholarMatch matching failed: ' .
            $e->getMessage()
        );

        $error =
            'Could not generate matches. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Find My Matches - ScholarMatch</title>

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
            <a href="preferences.php">Preferences</a>
            <a href="recommendations.php">
                Recommendations
            </a>
        <a href="dashboard.php">Dashboard</a><a href="semantic_search.php">AI Search</a><a href="bookmarks.php">Saved</a><a href="application_tracker.php">Tracker</a><a href="deadline_calendar.php">Deadlines</a>
</nav>

    </div>
</header>

<main class="student-page">

    <div class="student-form-card match-start-card">

        <span class="student-eyebrow">
            Rule-Based Matching
        </span>

        <h1>Find My Scholarship Matches</h1>

        <p>
            ScholarMatch compares your academic
            profile with scholarship eligibility
            requirements and ranks opportunities
            using your preferences.
        </p>

        <p>
            Missing or unclear eligibility data
            will require manual checking.
            A high preference score does not
            guarantee eligibility.
        </p>

        <?php if ($error !== ''): ?>

            <div class="student-error" role="alert">
                <?= sm_h($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="find_matches.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= sm_h(sm_student_csrf()) ?>"
            >

            <button type="submit" class="primary-btn">
                Generate Recommendations →
            </button>

        </form>

        <p class="match-hint">

            <a href="profile.php">
                Edit Profile
            </a>

            ·

            <a href="preferences.php">
                Edit Preferences
            </a>

        </p>

    </div>

</main>

<footer class="site-footer">
    <p>© <?= date('Y') ?> ScholarMatch</p>
</footer>

</body>
</html>
