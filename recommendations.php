
<?php
require_once __DIR__ . '/includes/student_session.php';
require_once __DIR__ . '/includes/student_helpers.php';
require_once __DIR__ . '/includes/v2.php';

// Identify the current student's profile.
$stmt = $pdo->prepare(
    'SELECT id FROM profile
     WHERE session_id = ?
     LIMIT 1'
);

$stmt->execute([
    $_SESSION['sm_student_token']
]);

$profileId = $stmt->fetchColumn();

if (!$profileId) {
    header('Location: profile.php');
    exit;
}

// Load only this student's saved recommendations.
$stmt = $pdo->prepare(
    "SELECT
        r.eligibility_status,
        r.match_score,
        r.suitability_label,
        r.created_at,

        s.id,
        s.name,
        s.provider,
        s.country,
        s.degree_level,
        s.field,
        s.funding_type,
        s.deadline

     FROM recommendation r

     JOIN scholarship s
        ON s.id = r.scholarship_id

     WHERE r.profile_id = ?
       AND s.is_active = 1
       AND s.deadline >= CURDATE()

     ORDER BY
        FIELD(
            r.eligibility_status,
            'Eligible',
            'Potentially Eligible',
            'Unknown/Manual Check',
            'Not Eligible'
        ),

        r.match_score DESC,
        s.deadline ASC"
);

$stmt->execute([(int)$profileId]);

$recommendations = $stmt->fetchAll();
$matchesStale = sm_v2_matches_stale($pdo,(int)$profileId);

function sm_status_class(string $status): string
{
    return match ($status) {

        'Eligible' => 'match-eligible',

        'Potentially Eligible' => 'match-potential',

        'Not Eligible' => 'match-ineligible',

        default => 'match-unknown'
    };
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Recommendations - ScholarMatch</title>

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

            <a href="recommendations.php" class="active">
                Recommendations
            </a>
        <a href="dashboard.php">Dashboard</a><a href="semantic_search.php">AI Search</a><a href="bookmarks.php">Saved</a><a href="application_tracker.php">Tracker</a><a href="deadline_calendar.php">Deadlines</a>
</nav>

    </div>

</header>

<main class="content-section explore-page">

    <div class="section-heading match-results-heading">

        <div>

            <span class="student-eyebrow">
                Personalized Results
            </span>

            <h1>My Scholarship Recommendations</h1>

            <p>
                Eligibility is based on recorded
                requirements. Preference fit is
                calculated separately.
            </p>

        </div>

        <form
            method="POST"
            action="find_matches.php"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= sm_h(sm_student_csrf()) ?>"
            >

            <button type="submit" class="primary-btn">
                Refresh Matches
            </button>

        </form>

    </div>

    <p class="result-summary">
        <?= count($recommendations) ?>
        scholarship(s) in your results.
    </p>

    <?php if ($matchesStale): ?><div class="v2-alert">Your academic profile or scholarship requirements changed. Please click Refresh Matches to update eligibility before relying on these results.</div><?php endif; ?>
<div class="scholarship-grid">

        <?php if (empty($recommendations)): ?>

            <div class="empty-state">

                <h2>No Recommendations Yet</h2>

                <p>
                    Generate matches to check available
                    scholarship opportunities.
                </p>

                <a href="find_matches.php" class="primary-btn">
                    Find My Matches
                </a>

            </div>

        <?php else: ?>

            <?php foreach ($recommendations as $r): ?>

                <article class="scholarship-card">

                    <div class="card-top">

                        <span
                            class="match-status <?= sm_status_class(
                                $r['eligibility_status']
                            ) ?>"
                        >
                            <?= sm_h(
                                $r['eligibility_status']
                            ) ?>
                        </span>

                        <span class="card-country">
                            <?= sm_h($r['country']) ?>
                        </span>

                    </div>

                    <h3>
                        <?= sm_h($r['name']) ?>
                    </h3>

                    <p class="provider">
                        <?= sm_h($r['provider']) ?>
                    </p>

                    <div class="card-details">

                        <p>
                            <strong>Degree:</strong>
                            <?= sm_h($r['degree_level']) ?>
                        </p>

                        <p>
                            <strong>Field:</strong>
                            <?= sm_h($r['field']) ?>
                        </p>

                        <p>
                            <strong>Funding:</strong>
                            <?= sm_h($r['funding_type']) ?>
                        </p>

                        <p>
                            <strong>Deadline:</strong>
                            <?= sm_h($r['deadline']) ?>
                        </p>

                        <p>
                            <strong>Preference Fit:</strong>
                            <?= sm_h(
                                $r['suitability_label']
                            ) ?>
                        </p>

                        <?php if ($r['match_score'] !== null): ?>

                            <p>
                                <strong>Preference Score:</strong>
                                <?= sm_h($r['match_score']) ?>/100
                            </p>

                        <?php endif; ?>

                    </div>

                    <?php if (
                        $r['eligibility_status'] === 'Not Eligible'
                    ): ?>

                        <p class="match-note">
                            At least one recorded explicit
                            requirement was not met.
                        </p>

                    <?php elseif (
                        $r['eligibility_status'] ===
                        'Unknown/Manual Check'
                    ): ?>

                        <p class="match-note">
                            Missing information or unverified
                            criteria require manual checking.
                        </p>

                    <?php elseif (
                        $r['eligibility_status'] ===
                        'Potentially Eligible'
                    ): ?>

                        <p class="match-note">
                            Some recorded criteria still
                            require manual confirmation.
                        </p>

                    <?php endif; ?>

                    
<form method="POST" action="bookmarks.php" style="margin:8px 0;"><input type="hidden" name="csrf_token" value="<?= sm_h(sm_student_csrf()) ?>"><input type="hidden" name="scholarship_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="save"><button class="v2-btn secondary" type="submit">☆ Save scholarship</button></form>
<!-- Scholarship Details Button -->
<a
    class="card-button"
    href="scholarship_details.php?id=<?= (int)$r['id'] ?>"
>
    View Scholarship Details →
</a>

<!-- RAG-Based Why This Fits Button -->
<a
    href="why_this_fits.php?scholarship_id=<?= (int)$r['id'] ?>"
    class="card-button"
    style="display:block; text-align:center; margin-top:10px;"
>
    Why This Fits · Manual / AI →
</a>



                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <p class="match-disclaimer">
        Results are based on the information currently
        stored in ScholarMatch. Always verify official
        scholarship requirements before applying.
        Refresh matches after changing your profile,
        preferences, or scholarship requirements.
    </p>

</main>

<footer class="site-footer">
    <p>© <?= date('Y') ?> ScholarMatch</p>
</footer>

</body>
</html>
