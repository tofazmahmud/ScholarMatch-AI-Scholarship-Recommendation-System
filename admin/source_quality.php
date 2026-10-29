<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();
require_once __DIR__ . '/../config/db.php';

$canEdit = admin_can_edit();
$error = '';
$notice = '';
$statuses = ['Unverified', 'Needs Review', 'Verified'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin_editor();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !valid_csrf($token)) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $status = $_POST['source_status'] ?? '';
    if (!$id || !is_string($status) || !in_array($status, $statuses, true)) {
        $error = 'Invalid scholarship or review status.';
    } else {
        try {
            $check = $pdo->prepare('SELECT source_status FROM scholarship WHERE id = ?');
            $check->execute([$id]);
            $previous = $check->fetchColumn();
            if ($previous === false) {
                $error = 'Scholarship not found.';
            } else {
                $update = $pdo->prepare(
                    'UPDATE scholarship SET source_status = ?, source_verified_at = ? WHERE id = ?'
                );
                $update->execute([
                    $status,
                    $status === 'Verified' ? date('Y-m-d') : null,
                    $id
                ]);
                admin_log($pdo, 'source.status', 'scholarship', (int)$id,
                    $previous . ' -> ' . $status);
                header('Location: source_quality.php?updated=1');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Source quality: ' . $e->getMessage());
            $error = 'Unable to update the source status. Check System Health.';
        }
    }
}

if (($_GET['updated'] ?? '') === '1') {
    $notice = 'Source review status saved.';
}

$rows = [];
try {
    $rows = $pdo->query(
        'SELECT s.id, s.name, s.provider, s.official_url, s.deadline,
                s.source_status, s.source_verified_at,
                (SELECT COUNT(*) FROM requirement r WHERE r.scholarship_id = s.id) AS rule_count
         FROM scholarship s
         ORDER BY (s.source_verified_at IS NULL) DESC, s.id DESC LIMIT 300'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Source quality listing: ' . $e->getMessage());
    $error = 'Source quality data is unavailable. Check V2 columns and System Health.';
}

sm_admin_header('Source Quality', 'source');
?>
<p class="admin-v3-muted">Review official provider pages manually before choosing Verified.
This tool records a human review; it does not automatically verify websites.</p>
<?php if ($error): ?><div class="admin-v3-danger" role="alert"><?= adm_h($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="admin-v3-success" role="status"><?= adm_h($notice) ?></div><?php endif; ?>
<section class="v2-card">
  <h2>Scholarship source review</h2>
  <div class="admin-v3-table-wrap">
    <table class="admin-v3-table">
      <thead><tr><th>Scholarship / Provider</th><th>Deadline</th><th>Rules</th><th>Source</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><strong><?= adm_h($row['name']) ?></strong><br><?= adm_h($row['provider']) ?></td>
          <td><?= adm_h($row['deadline']) ?></td>
          <td><?= (int)$row['rule_count'] ?></td>
          <td>
            <?php if (sm_source_valid_url((string)$row['official_url'])): ?>
              <a href="<?= adm_h($row['official_url']) ?>" target="_blank" rel="noopener noreferrer">Review official link</a>
            <?php else: ?>No valid URL<?php endif; ?>
          </td>
          <td>
            <?php if ($canEdit): ?>
              <form method="post" class="admin-v3-inline">
                <input type="hidden" name="csrf_token" value="<?= adm_h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <select name="source_status" aria-label="Source status">
                  <?php foreach ($statuses as $option): ?>
                    <option value="<?= adm_h($option) ?>" <?= $option === $row['source_status'] ? 'selected' : '' ?>><?= adm_h($option) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="v2-btn" type="submit">Save</button>
              </form>
            <?php else: ?>
              <strong><?= adm_h($row['source_status']) ?></strong>
            <?php endif; ?>
            <small>Last verified: <?= adm_h($row['source_verified_at'] ?: 'Never') ?></small>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5">No records available.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php sm_admin_footer(); ?>
<?php
function sm_source_valid_url(string $url): bool {
    return (bool)filter_var($url, FILTER_VALIDATE_URL) &&
        in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
}
