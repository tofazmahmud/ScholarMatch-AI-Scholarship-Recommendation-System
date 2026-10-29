<?php
require_once __DIR__ . '/includes/student_helpers.php';

// Public pages stay available even before Admin V3 settings migration.
$announcement = '';
$supportEmail = '';
try {
    $settings = $pdo->query(
        "SELECT setting_key, setting_value FROM admin_site_setting
         WHERE setting_key IN ('public_announcement', 'support_email')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $announcement = (string)($settings['public_announcement'] ?? '');
    $candidate = (string)($settings['support_email'] ?? '');
    if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) $supportEmail = $candidate;
} catch (Throwable $e) {
    // Setting records are optional; do not expose database details publicly.
}

$condition = sm_public_condition($pdo);
$total = (int)$pdo->query(
    "SELECT COUNT(*) FROM scholarship s WHERE $condition"
)->fetchColumn();
$featured = $pdo->query(
    "SELECT s.id, s.name, s.provider, s.country, s.degree_level,
            s.field, s.funding_type, s.deadline
     FROM scholarship s WHERE $condition
     ORDER BY s.deadline ASC, s.id DESC LIMIT 3"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ScholarMatch - Find Scholarships</title>
  <link rel="stylesheet" href="assets/css/student.css">
  <style>
    .sm-site-announcement {
      max-width: 1180px; margin: 18px auto; padding: 14px 20px;
      background: #ebf2ff; border: 1px solid #b6cef9;
      border-radius: 10px; color: #1c417a; font-weight: 600;
    }
  </style>
</head>
<body>
<header class="site-header">
  <div class="nav-container">
    <a href="index.php" class="brand">🎓 ScholarMatch</a>
    <nav aria-label="Main navigation">
      <a href="index.php" class="active">Home</a>
      <a href="explore.php">Explore Scholarships</a>
      <a href="dashboard.php">Dashboard</a>
      <a href="profile.php">My Profile</a>
      <a href="preferences.php">Preferences</a>
      <a href="find_matches.php">Find My Matches</a>
      <a href="recommendations.php">Recommendations</a>
    </nav>
  </div>
</header>
<main>
  <?php if ($announcement !== ''): ?>
    <div class="sm-site-announcement" role="status">
      <?= sm_h($announcement) ?>
    </div>
  <?php endif; ?>

  <section class="hero">
    <div class="hero-content">
      <span class="hero-label">Scholarship Discovery Made Easier</span>
      <h1>Find Scholarships That Match Your Future</h1>
      <p>Explore scholarships, review eligibility requirements,
         and discover funding options for your education.</p>
      <form action="explore.php" method="get" class="hero-search">
        <input type="search" name="q" placeholder="Search scholarships, fields or countries..."
               aria-label="Search scholarships">
        <button type="submit">Search</button>
      </form>
      <a href="explore.php" class="hero-secondary-link">Explore All Scholarships →</a>
    </div>
  </section>

  <section class="content-section">
    <div class="section-heading">
      <div>
        <h2>Upcoming Scholarship Opportunities</h2>
        <p>Discover available opportunities from our scholarship database.</p>
      </div>
      <a href="explore.php" class="text-link">View All →</a>
    </div>
    <p class="result-summary"><?= number_format($total) ?> available scholarship(s)</p>
    <div class="scholarship-grid">
      <?php if (!$featured): ?>
        <div class="empty-state">
          <h3>No available scholarships yet</h3>
          <p>New active listings will appear here when they are available.</p>
        </div>
      <?php else: ?>
        <?php foreach ($featured as $item): ?>
          <article class="scholarship-card">
            <div class="card-top">
              <span class="funding-badge"><?= sm_h($item['funding_type']) ?></span>
              <span class="card-country"><?= sm_h($item['country']) ?></span>
            </div>
            <h3><?= sm_h($item['name']) ?></h3>
            <p class="provider"><?= sm_h($item['provider']) ?></p>
            <div class="card-details">
              <p><strong>Degree:</strong> <?= sm_h($item['degree_level']) ?></p>
              <p><strong>Field:</strong> <?= sm_h($item['field']) ?></p>
              <p><strong>Deadline:</strong> <?= sm_h($item['deadline']) ?></p>
            </div>
            <a class="card-button" href="scholarship_details.php?id=<?= (int)$item['id'] ?>">
              View Details →
            </a>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="how-it-works">
    <h2>How ScholarMatch Works</h2>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-number">01</div>
        <h3>Explore</h3>
        <p>Search scholarships by country, degree, field and funding.</p>
      </div>
      <div class="step-card">
        <div class="step-number">02</div>
        <h3>Build Your Profile</h3>
        <p>Provide your academic background, skills and study preferences.</p>
      </div>
      <div class="step-card">
        <div class="step-number">03</div>
        <h3>Get Recommendations</h3>
        <p>Run the eligibility matching engine and review explanations.</p>
      </div>
    </div>
  </section>
</main>
<footer class="site-footer">
  <p>© <?= date('Y') ?> ScholarMatch. Scholarship Discovery System.</p>
  <?php if ($supportEmail !== ''): ?>
    <p>Support: <a href="mailto:<?= sm_h($supportEmail) ?>"><?= sm_h($supportEmail) ?></a></p>
  <?php endif; ?>
</footer>
</body>
</html>
