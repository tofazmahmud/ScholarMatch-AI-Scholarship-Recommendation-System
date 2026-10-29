<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();
require_once __DIR__ . '/../config/db.php';

$canEdit = admin_can_edit();
$error = '';
$notice = '';
$preview = [];
$columns = ['name', 'provider', 'country', 'degree_level', 'field',
    'funding_type', 'deadline', 'official_url', 'description_text'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin_editor();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !valid_csrf($token)) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    $action = $_POST['action'] ?? '';
    try {
        if (!in_array($action, ['preview', 'import'], true)) {
            throw new RuntimeException('Choose Preview or Import.');
        }
        $file = $_FILES['csv'] ?? null;
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK ||
            (int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 1048576 ||
            !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Upload a CSV file no larger than 1 MB.');
        }
        if (strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            throw new RuntimeException('Only CSV files are supported.');
        }

        $handle = fopen((string)$file['tmp_name'], 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read CSV file.');
        }
        try {
            $header = fgetcsv($handle);
            if (!is_array($header)) {
                throw new RuntimeException('CSV header is missing.');
            }
            $header = array_map(static function ($key): string {
                return strtolower(trim((string)$key));
            }, $header);
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            if (count($header) !== count(array_unique($header))) {
                throw new RuntimeException('CSV has duplicate column names.');
            }
            foreach ($columns as $required) {
                if (!in_array($required, $header, true)) {
                    throw new RuntimeException('Missing CSV column: ' . $required);
                }
            }
            $number = 0;
            while (($line = fgetcsv($handle)) !== false) {
                if ($line === [null]) {
                    continue;
                }
                ++$number;
                if ($number > 200) {
                    throw new RuntimeException('Maximum 200 records per import.');
                }
                if (count($line) !== count($header)) {
                    throw new RuntimeException('Row ' . $number . ' has an incorrect number of cells.');
                }
                $raw = array_combine($header, $line);
                $record = [];
                foreach ($columns as $column) {
                    $record[$column] = trim((string)$raw[$column]);
                }
                foreach (array_slice($columns, 0, 8) as $required) {
                    if ($record[$required] === '') {
                        throw new RuntimeException('Row ' . $number . ': ' . $required . ' required.');
                    }
                }
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $record['deadline']);
                $scheme = strtolower((string)parse_url($record['official_url'], PHP_URL_SCHEME));
                if (!$date || $date->format('Y-m-d') !== $record['deadline'] ||
                    !filter_var($record['official_url'], FILTER_VALIDATE_URL) ||
                    !in_array($scheme, ['http', 'https'], true)) {
                    throw new RuntimeException('Row ' . $number . ': invalid deadline or official URL.');
                }
                if (!in_array($record['degree_level'], ['Bachelor', 'Master', 'PhD', 'Any'], true) ||
                    !in_array($record['funding_type'],
                        ['Fully Funded', 'Partially Funded', 'Tuition Waiver', 'Other'], true)) {
                    throw new RuntimeException('Row ' . $number . ': invalid degree or funding type.');
                }
                foreach ($record as $key => $value) {
                    $limit = $key === 'official_url' ? 2048 :
                        ($key === 'description_text' ? 10000 : 255);
                    if (strlen($value) > $limit) {
                        throw new RuntimeException('Row ' . $number . ': ' . $key . ' too long.');
                    }
                }
                $preview[] = $record;
            }
        } finally {
            fclose($handle);
        }

        if (!$preview) {
            throw new RuntimeException('CSV contains no scholarship records.');
        }

        if ($action === 'import') {
            $schema = $pdo->query('SHOW COLUMNS FROM scholarship')->fetchAll(PDO::FETCH_COLUMN);
            $insertColumns = $columns;
            $includeActive = in_array('is_active', $schema, true);
            $includeAdmin = in_array('created_by_admin_id', $schema, true);
            if ($includeActive) $insertColumns[] = 'is_active';
            if ($includeAdmin) $insertColumns[] = 'created_by_admin_id';
            $quoted = implode(', ', array_map(static fn($x) => '`' . $x . '`', $insertColumns));
            $slots = implode(', ', array_fill(0, count($insertColumns), '?'));

            $pdo->beginTransaction();
            try {
                $check = $pdo->prepare('SELECT id FROM scholarship WHERE name = ? AND provider = ? LIMIT 1');
                $insert = $pdo->prepare('INSERT INTO scholarship (' . $quoted . ') VALUES (' . $slots . ')');
                $added = 0;
                $skipped = 0;
                foreach ($preview as $row) {
                    $check->execute([$row['name'], $row['provider']]);
                    if ($check->fetchColumn()) {
                        ++$skipped;
                        continue;
                    }
                    $values = array_values($row);
                    if ($includeActive) $values[] = 1;
                    if ($includeAdmin) $values[] = (int)$_SESSION['admin_id'];
                    $insert->execute($values);
                    ++$added;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            admin_log($pdo, 'scholarship.csv_import', 'scholarship', 0,
                'Imported ' . $added . ' records; skipped ' . $skipped);
            $notice = "$added scholarships added, $skipped duplicates skipped. Review sources before marking Verified.";
            $preview = [];
        }
    } catch (Throwable $e) {
        error_log('ScholarMatch CSV import: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'CSV import failed. Check database and file format.';
        $preview = [];
    }
}

sm_admin_header('CSV Scholarship Import', 'import');
?>
<p class="admin-v3-muted">Validate and preview up to 200 records (1 MB). Import revalidates the uploaded file.
Duplicate scholarship name + provider pairs are skipped.</p>
<?php if ($error): ?><div class="admin-v3-danger" role="alert"><?= adm_h($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="admin-v3-success" role="status"><?= adm_h($notice) ?></div><?php endif; ?>
<section class="v2-card">
  <h2>CSV format</h2>
  <p class="admin-v3-muted">Required headers: <?= adm_h(implode(', ', $columns)) ?>.<br>
  Use UTF-8, YYYY-MM-DD dates, and valid HTTP(S) URLs.</p>
  <?php if ($canEdit): ?>
    <form method="post" enctype="multipart/form-data" class="v2-form">
      <input type="hidden" name="csrf_token" value="<?= adm_h(csrf_token()) ?>">
      <label>CSV file <input type="file" name="csv" accept=".csv,text/csv" required></label>
      <label>Action
        <select name="action">
          <option value="preview">Validate / Preview</option>
          <option value="import">Import after validation</option>
        </select>
      </label>
      <button class="v2-btn" type="submit">Process CSV</button>
    </form>
  <?php else: ?><p>Viewer access is read-only. CSV importing requires Editor access.</p><?php endif; ?>
</section>
<?php if ($preview): ?>
  <section class="v2-card">
    <h2>Validated preview: <?= count($preview) ?> records</h2>
    <div class="admin-v3-table-wrap">
      <table class="admin-v3-table">
        <thead><tr><th>Name</th><th>Provider</th><th>Country</th><th>Deadline</th></tr></thead>
        <tbody><?php foreach (array_slice($preview, 0, 20) as $row): ?>
          <tr><td><?= adm_h($row['name']) ?></td><td><?= adm_h($row['provider']) ?></td>
              <td><?= adm_h($row['country']) ?></td><td><?= adm_h($row['deadline']) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table>
    </div>
    <p class="admin-v3-muted">No records saved during Preview. Upload the same CSV again and select Import.</p>
  </section>
<?php endif; ?>
<?php sm_admin_footer(); ?>
