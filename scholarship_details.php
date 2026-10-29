
<?php
require_once __DIR__ . '/includes/student_helpers.php';

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false) {
    http_response_code(404);
    exit('Scholarship not found.');
}

// Hide inactive scholarships from public visitors.
$sql = "SELECT s.*
        FROM scholarship s
        WHERE s.id = ?";

if (sm_has_active_column($pdo)) {
    $sql .= " AND s.is_active = 1";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$scholarship = $stmt->fetch();

if (!$scholarship) {
    http_response_code(404);
    exit('Scholarship not found.');
}

// Fetch eligibility requirements.
$stmt = $pdo->prepare(
    "SELECT
        requirement_type,
        comparison_operator,
        value,
        certainty
     FROM requirement
     WHERE scholarship_id = ?
     ORDER BY id ASC"
);

$stmt->execute([$id]);
$requirements = $stmt->fetchAll();

$isOpen = $scholarship['deadline'] >= date('Y-m-d');

$officialUrl = $scholarship['official_url'];

$hasOfficialLink = sm_valid_url($officialUrl);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= sm_h($scholarship['name']) ?> - ScholarMatch
    </title>

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
            <a href="explore.php">Explore Scholarships</a>
			<a href="profile.php">My Profile</a>
<a href="preferences.php">Preferences</a>
<a href="find_matches.php">Find My Matches</a>
<a href="recommendations.php">Recommendations</a>
        <a href="dashboard.php">Dashboard</a><a href="semantic_search.php">AI Search</a><a href="bookmarks.php">Saved</a><a href="application_tracker.php">Tracker</a><a href="deadline_calendar.php">Deadlines</a>
</nav>

    </div>

</header>

<main class="details-container">

    <a href="explore.php" class="back-link">
        ← Back to Scholarships
    </a>

    <section class="details-card">

        <div class="details-top">

            <span class="funding-badge">
                <?= sm_h($scholarship['funding_type']) ?>
            </span>

            <span class="<?= $isOpen
                ? 'status-open'
                : 'status-closed' ?>">
                <?= $isOpen
                    ? 'Application Period Open'
                    : 'Deadline Passed' ?>
            </span>

        </div>

        <h1>
            <?= sm_h($scholarship['name']) ?>
        </h1>

        <p class="details-provider">
            Offered by:
            <strong>
                <?= sm_h($scholarship['provider']) ?>
            </strong>
        </p>

        <div class="info-grid">

            <div>
                <span>Country</span>
                <strong>
                    <?= sm_h($scholarship['country']) ?>
                </strong>
            </div>

            <div>
                <span>Degree Level</span>
                <strong>
                    <?= sm_h($scholarship['degree_level']) ?>
                </strong>
            </div>

            <div>
                <span>Field of Study</span>
                <strong>
                    <?= sm_h($scholarship['field']) ?>
                </strong>
            </div>

            <div>
                <span>Funding Type</span>
                <strong>
                    <?= sm_h($scholarship['funding_type']) ?>
                </strong>
            </div>

            <div>
                <span>Application Deadline</span>
                <strong>
                    <?= sm_h($scholarship['deadline']) ?>
                </strong>
            </div>

        </div>

        <h2>Scholarship Description</h2>

        <p class="description-text">
            <?= nl2br(sm_h(
                $scholarship['description_text']
            )) ?>
        </p>

        <h2>Eligibility Requirements</h2>

        <?php if (empty($requirements)): ?>

            <p class="empty-requirements">
                Detailed requirements have not been
                added to ScholarMatch yet.
                Please check the official scholarship page.
            </p>

        <?php else: ?>

            <div class="requirement-list">

                <?php foreach ($requirements as $rule): ?>

                    <div class="requirement-item">

                        <div>
                            <strong>
                                <?= sm_h(ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $rule['requirement_type']
                                    )
                                )) ?>
                            </strong>

                            <p>
                                <?= sm_h($rule['comparison_operator']) ?>
                                <?= sm_h($rule['value']) ?>
                            </p>
                        </div>

                        <span class="certainty-label">
                            <?= $rule['certainty'] === 'explicit'
                                ? 'Explicit'
                                : 'Manual Check' ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <p class="eligibility-note">
            These are the requirements currently recorded
            in ScholarMatch. Always verify the complete
            eligibility rules on the provider's official
            application page before applying.
        </p>

        <?php if (($scholarship['source_status'] ?? 'Unverified') !== 'Verified'): ?>
            <p class="v2-alert">This scholarship record has not been marked verified by an administrator. Check its official source independently.</p>
        <?php endif; ?>
        <?php if (strtolower((string)parse_url($officialUrl,PHP_URL_HOST)) === 'example.com'): ?>
            <p class="v2-alert">This is a demo URL (example.com), not an official scholarship application page.</p>
        <?php endif; ?>
        <?php if ($hasOfficialLink): ?>

            <a
                href="<?= sm_h($officialUrl) ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="primary-btn application-link"
            >
                Visit Official Application Website ↗
            </a>

        <?php else: ?>

            <p class="empty-requirements">
                A valid official application link
                is not available.
            </p>

        <?php endif; ?>

    </section>

</main>

<footer class="site-footer">
    <p>
        © <?= date('Y') ?> ScholarMatch.
        Scholarship Discovery System.
    </p>
</footer>

</body>
</html>
