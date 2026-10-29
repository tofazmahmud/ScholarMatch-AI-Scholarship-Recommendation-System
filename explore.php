<?php
require_once __DIR__ . '/includes/student_helpers.php';

$catalogueNotice = '';
try {
    $setting = $pdo->prepare(
        'SELECT setting_value FROM admin_site_setting WHERE setting_key = ?'
    );
    $setting->execute(['catalogue_notice']);
    $catalogueNotice = (string)($setting->fetchColumn() ?: '');
} catch (Throwable $e) {
    // Public exploration remains accessible without optional Admin V3 settings.
}

$q = sm_get('q', 80);
$country = sm_get('country', 80);
$degree = sm_get('degree', 30);
$funding = sm_get('funding', 40);
$field = sm_get('field', 80);
$sort = sm_get('sort', 20);

$degrees = ['Bachelor', 'Master', 'PhD', 'Any'];
$fundingTypes = ['Fully Funded', 'Partially Funded', 'Tuition Waiver', 'Other'];
$orderOptions = [
    'deadline' => 's.deadline ASC, s.id DESC',
    'newest' => 's.id DESC',
    'name' => 's.name ASC, s.id DESC'
];
if (!in_array($degree, $degrees, true)) $degree = '';
if (!in_array($funding, $fundingTypes, true)) $funding = '';
if (!array_key_exists($sort, $orderOptions)) $sort = 'deadline';

$where = [sm_public_condition($pdo)];
$params = [];
if ($q !== '') {
    $where[] = '(s.name LIKE ? OR s.provider LIKE ? OR s.field LIKE ? OR s.country LIKE ?)';
    for ($i = 0; $i < 4; $i++) $params[] = '%' . $q . '%';
}
if ($country !== '') {
    $where[] = 's.country LIKE ?';
    $params[] = '%' . $country . '%';
}
if ($field !== '') {
    $where[] = 's.field LIKE ?';
    $params[] = '%' . $field . '%';
}
if ($degree !== '') {
    $where[] = 's.degree_level = ?';
    $params[] = $degree;
}
if ($funding !== '') {
    $where[] = 's.funding_type = ?';
    $params[] = $funding;
}
$whereSql = implode(' AND ', $where);

$count = $pdo->prepare('SELECT COUNT(*) FROM scholarship s WHERE ' . $whereSql);
$count->execute($params);
$total = (int)$count->fetchColumn();
$perPage = 9;
$totalPages = max(1, (int)ceil($total / $perPage));
$requested = filter_var(sm_get('page', 10), FILTER_VALIDATE_INT);
$page = $requested !== false && $requested >= 1 ? min($requested, $totalPages) : 1;
$offset = ($page - 1) * $perPage;

$sql = 'SELECT s.id, s.name, s.provider, s.country, s.degree_level,
               s.field, s.funding_type, s.deadline
        FROM scholarship s WHERE ' . $whereSql .
        ' ORDER BY ' . $orderOptions[$sort] . ' LIMIT ? OFFSET ?';
$stmt = $pdo->prepare($sql);
$index = 1;
foreach ($params as $value) {
    $stmt->bindValue($index++, $value, PDO::PARAM_STR);
}
$stmt->bindValue($index++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($index, $offset, PDO::PARAM_INT);
$stmt->execute();
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

function explore_page_url(int $page, array $filters): string {
    return 'explore.php?' . http_build_query(array_merge($filters, ['page' => $page]));
}
$filters = compact('q', 'country', 'degree', 'funding', 'field', 'sort');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Explore Scholarships - ScholarMatch</title>
  <link rel="stylesheet" href="assets/css/student.css">
  <style>
    .sm-catalogue-notice {background:#eef3ff;border-left:4px solid #3d66c6;
      padding:13px 17px;margin:16px 0;border-radius:8px;color:#243c64;}
  </style>
</head>
<body>
<header class="site-header">
  <div class="nav-container">
    <a href="index.php" class="brand">🎓 ScholarMatch</a>
    <nav aria-label="Main navigation">
      <a href="index.php">Home</a>
      <a href="explore.php" class="active">Explore Scholarships</a>
      <a href="dashboard.php">Dashboard</a>
      <a href="profile.php">My Profile</a>
      <a href="preferences.php">Preferences</a>
      <a href="find_matches.php">Find My Matches</a>
      <a href="recommendations.php">Recommendations</a>
    </nav>
  </div>
</header>
<main class="content-section explore-page">
  <div class="page-heading">
    <h1>Explore Scholarships</h1>
    <p>Find opportunities based on your preferred criteria.</p>
  </div>
  <?php if ($catalogueNotice !== ''): ?>
    <div class="sm-catalogue-notice" role="status"><?= sm_h($catalogueNotice) ?></div>
  <?php endif; ?>

  <form action="explore.php" method="get" class="filter-panel">
    <div class="filter-grid">
      <div><label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= sm_h($q) ?>" placeholder="Scholarship, provider or country"></div>
      <div><label for="country">Country</label>
        <input type="text" id="country" name="country" value="<?= sm_h($country) ?>" placeholder="e.g. Canada"></div>
      <div><label for="field">Field of Study</label>
        <input type="text" id="field" name="field" value="<?= sm_h($field) ?>" placeholder="e.g. Computer Science"></div>
      <div><label for="degree">Degree Level</label>
        <select id="degree" name="degree">
          <option value="">All Degrees</option>
          <?php foreach ($degrees as $item): ?>
            <option value="<?= sm_h($item) ?>" <?= $degree === $item ? 'selected' : '' ?>><?= sm_h($item) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label for="funding">Funding Type</label>
        <select id="funding" name="funding">
          <option value="">All Funding Types</option>
          <?php foreach ($fundingTypes as $item): ?>
            <option value="<?= sm_h($item) ?>" <?= $funding === $item ? 'selected' : '' ?>><?= sm_h($item) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label for="sort">Sort By</label>
        <select id="sort" name="sort">
          <option value="deadline" <?= $sort === 'deadline' ? 'selected' : '' ?>>Nearest Deadline</option>
          <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Recently Added</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Scholarship Name</option>
        </select>
      </div>
    </div>
    <div class="filter-actions">
      <button type="submit" class="primary-btn">Search Scholarships</button>
      <a href="explore.php" class="clear-link">Clear Filters</a>
    </div>
  </form>

  <div class="results-heading">
    <h2>Available Scholarships</h2>
    <span><?= number_format($total) ?> result(s) found</span>
  </div>
  <div class="scholarship-grid">
    <?php if (!$scholarships): ?>
      <div class="empty-state">
        <h3>No scholarships found</h3>
        <p>Try changing your search terms or filters.</p>
        <a href="explore.php" class="primary-btn">Show All Scholarships</a>
      </div>
    <?php else: ?>
      <?php foreach ($scholarships as $s): ?>
        <article class="scholarship-card">
          <div class="card-top">
            <span class="funding-badge"><?= sm_h($s['funding_type']) ?></span>
            <span class="card-country"><?= sm_h($s['country']) ?></span>
          </div>
          <h3><?= sm_h($s['name']) ?></h3>
          <p class="provider"><?= sm_h($s['provider']) ?></p>
          <div class="card-details">
            <p><strong>Degree:</strong> <?= sm_h($s['degree_level']) ?></p>
            <p><strong>Field:</strong> <?= sm_h($s['field']) ?></p>
            <p><strong>Deadline:</strong> <?= sm_h($s['deadline']) ?></p>
          </div>
          <a href="scholarship_details.php?id=<?= (int)$s['id'] ?>" class="card-button">View Details →</a>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Results pagination">
      <?php if ($page > 1): ?>
        <a href="<?= sm_h(explore_page_url($page - 1, $filters)) ?>">← Previous</a>
      <?php endif; ?>
      <span>Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="<?= sm_h(explore_page_url($page + 1, $filters)) ?>">Next →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</main>
<footer class="site-footer">
  <p>© <?= date('Y') ?> ScholarMatch. Scholarship Discovery System.</p>
</footer>
</body>
</html>
