<?php
// admin/scholarships.php — ScholarMatch Admin V3
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();
require_once __DIR__ . '/../config/db.php';

$canEdit = admin_can_edit();

// Preserve compatibility with the original and expanded scholarship schemas.
$columns = $pdo->query('SHOW COLUMNS FROM scholarship')
    ->fetchAll(PDO::FETCH_COLUMN);
$hasActive = in_array('is_active', $columns, true);
$hasAdminFK = in_array('created_by_admin_id', $columns, true);
$hasSourceStatus = in_array('source_status', $columns, true);
$hasVerifiedAt = in_array('source_verified_at', $columns, true);

$fields = [
    'name', 'provider', 'country', 'degree_level', 'field',
    'funding_type', 'deadline', 'official_url', 'description_text'
];

$maxLengths = [
    'name' => 255, 'provider' => 255, 'country' => 100,
    'degree_level' => 150, 'field' => 150, 'funding_type' => 100,
    'official_url' => 2048
];

$data = array_fill_keys($fields, '');
$data['is_active'] = 1;
$error = '';
$editing = false;
$editId = 0;

function sm_scholarship_post_text(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

// All write operations require Editor/Super Admin and a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin_editor();

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !valid_csrf($token)) {
        http_response_code(403);
        exit('Invalid security token. Refresh and try again.');
    }

    $action = sm_scholarship_post_text('action');

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if ($id === false || $id === null || $id < 1) {
            $error = 'Invalid scholarship ID.';
        } else {
            try {
                $check = $pdo->prepare('SELECT name FROM scholarship WHERE id = ?');
                $check->execute([(int)$id]);
                $name = $check->fetchColumn();

                if ($name === false) {
                    $error = 'Scholarship not found.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM scholarship WHERE id = ?');
                    $stmt->execute([(int)$id]);

                    if ($stmt->rowCount() !== 1) {
                        $error = 'Scholarship could not be deleted.';
                    } else {
                        admin_log(
                            $pdo,
                            'scholarship.delete',
                            'scholarship',
                            (int)$id,
                            'Deleted: ' . $name
                        );
                        header('Location: scholarships.php?msg=deleted');
                        exit;
                    }
                }
            } catch (PDOException $ex) {
                error_log('Scholarship delete: ' . $ex->getMessage());
                $error = 'Could not delete this scholarship. Check related records.';
            }
        }

    } elseif ($action === 'save') {
        foreach ($fields as $field) {
            $data[$field] = sm_scholarship_post_text($field);
        }
        $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;

        $id = filter_var($_POST['id'] ?? '0', FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 0) {
            $error = 'Invalid scholarship ID.';
        } else {
            $editId = (int)$id;
            $editing = $editId > 0;
        }

        if ($error === '') {
            foreach (['name', 'provider', 'country', 'degree_level', 'field',
                'funding_type', 'deadline', 'official_url'] as $required) {
                if ($data[$required] === '') {
                    $error = 'Please fill in all required fields.';
                    break;
                }
            }
        }

        if ($error === '') {
            foreach ($maxLengths as $field => $maxLength) {
                if (strlen($data[$field]) > $maxLength) {
                    $error = ucfirst(str_replace('_', ' ', $field)) . ' is too long.';
                    break;
                }
            }
        }

        if ($error === '' && !in_array(
            $data['degree_level'], ['Bachelor', 'Master', 'PhD', 'Any'], true
        )) {
            $error = 'Choose a valid degree level.';
        }

        if ($error === '' && !in_array(
            $data['funding_type'],
            ['Fully Funded', 'Partially Funded', 'Tuition Waiver', 'Other'],
            true
        )) {
            $error = 'Choose a valid funding type.';
        }

        $deadline = DateTimeImmutable::createFromFormat('!Y-m-d', $data['deadline']);
        if ($error === '' && (
            !$deadline || $deadline->format('Y-m-d') !== $data['deadline']
        )) {
            $error = 'Enter a valid deadline.';
        }

        $urlScheme = strtolower((string)parse_url(
            $data['official_url'], PHP_URL_SCHEME
        ));
        if ($error === '' && (
            !filter_var($data['official_url'], FILTER_VALIDATE_URL) ||
            !in_array($urlScheme, ['https', 'http'], true)
        )) {
            $error = 'Enter a valid HTTP or HTTPS official URL.';
        }

        if ($error === '') {
            try {
                if ($editing) {
                    // Reject invalid IDs instead of reporting a successful update.
                    $exists = $pdo->prepare('SELECT id FROM scholarship WHERE id = ?');
                    $exists->execute([$editId]);
                    if (!$exists->fetchColumn()) {
                        throw new RuntimeException('Scholarship not found.');
                    }

                    $setFields = [];
                    $values = [];
                    foreach ($fields as $field) {
                        $setFields[] = "`$field` = ?";
                        $values[] = $data[$field];
                    }
                    if ($hasActive) {
                        $setFields[] = '`is_active` = ?';
                        $values[] = $data['is_active'];
                    }
                    $values[] = $editId;

                    $stmt = $pdo->prepare(
                        'UPDATE scholarship SET ' . implode(', ', $setFields) . ' WHERE id = ?'
                    );
                    $stmt->execute($values);

                    admin_log(
                        $pdo, 'scholarship.update', 'scholarship', $editId,
                        'Updated: ' . $data['name']
                    );
                    header('Location: scholarships.php?msg=updated');
                    exit;

                } else {
                    $insertFields = $fields;
                    $values = [];
                    foreach ($fields as $field) {
                        $values[] = $data[$field];
                    }
                    if ($hasActive) {
                        $insertFields[] = 'is_active';
                        $values[] = $data['is_active'];
                    }
                    if ($hasAdminFK) {
                        $insertFields[] = 'created_by_admin_id';
                        $values[] = (int)$_SESSION['admin_id'];
                    }

                    $fieldSql = implode(', ', array_map(
                        static fn(string $field): string => "`$field`",
                        $insertFields
                    ));
                    $placeholders = implode(', ', array_fill(0, count($values), '?'));
                    $stmt = $pdo->prepare(
                        "INSERT INTO scholarship ($fieldSql) VALUES ($placeholders)"
                    );
                    $stmt->execute($values);
                    $newId = (int)$pdo->lastInsertId();

                    admin_log(
                        $pdo, 'scholarship.create', 'scholarship', $newId,
                        'Added: ' . $data['name']
                    );
                    header('Location: scholarships.php?msg=added');
                    exit;
                }
            } catch (Throwable $ex) {
                error_log('Scholarship save: ' . $ex->getMessage());
                $error = $ex instanceof RuntimeException
                    ? $ex->getMessage()
                    : 'Database operation failed. Check table columns and constraints.';
            }
        }
    } else {
        $error = 'Unknown action.';
    }
}

// Load an existing scholarship for editing (Editor/Super Admin only).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    if (!$canEdit) {
        http_response_code(403);
        exit('Viewer accounts cannot edit scholarships.');
    }
    $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id < 1) {
        $error = 'Invalid scholarship ID.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM scholarship WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $editing = true;
            $editId = (int)$id;
            foreach ($fields as $field) {
                $data[$field] = (string)($existing[$field] ?? '');
            }
            $data['is_active'] = (int)($existing['is_active'] ?? 1);
        } else {
            $error = 'Scholarship not found.';
        }
    }
}

// Search and filter without embedding user input into SQL.
$search = trim((string)($_GET['q'] ?? ''));
$search = substr($search, 0, 120);
$statusFilter = (string)($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) {
    $statusFilter = 'all';
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE ? OR provider LIKE ? OR country LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
if ($hasActive && $statusFilter !== 'all') {
    $where[] = 'is_active = ?';
    $params[] = $statusFilter === 'active' ? 1 : 0;
}

$sql = 'SELECT * FROM scholarship';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

$messages = [
    'added' => 'Scholarship added successfully.',
    'updated' => 'Scholarship updated successfully.',
    'deleted' => 'Scholarship deleted successfully.'
];
$msgKey = $_GET['msg'] ?? '';
$success = is_string($msgKey) ? ($messages[$msgKey] ?? '') : '';

sm_admin_header('Scholarship Management', 'scholarships');
?>

<p class="admin-v3-muted">
    Manage the scholarship catalogue, eligibility requirements and source details.
    <?= $canEdit ? 'You have editing permission.' : 'You have read-only access.' ?>
</p>

<?php if ($success !== ''): ?>
    <div class="admin-v3-success" role="status"><?= adm_h($success) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="admin-v3-danger" role="alert"><?= adm_h($error) ?></div>
<?php endif; ?>

<div class="v2-actions">
    <a href="requirements.php" class="v2-btn secondary">Requirements</a>
    <a href="source_quality.php" class="v2-btn secondary">Source Verification</a>
    <a href="documents.php" class="v2-btn secondary">RAG Documents</a>
    <?php if ($canEdit): ?>
        <a href="import.php" class="v2-btn secondary">CSV Import</a>
    <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<section class="v2-card" id="scholarship-form">
    <h2><?= $editing ? 'Edit Scholarship #' . $editId : 'Add New Scholarship' ?></h2>

    <form method="POST" action="scholarships.php" class="scholarship-form">
        <input type="hidden" name="csrf_token" value="<?= adm_h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$editId ?>">

        <div class="form-grid">
            <div>
                <label for="sch-name">Scholarship Name *</label>
                <input id="sch-name" name="name" type="text" maxlength="255"
                       value="<?= adm_h($data['name']) ?>" required>
            </div>
            <div>
                <label for="sch-provider">Provider *</label>
                <input id="sch-provider" name="provider" type="text" maxlength="255"
                       value="<?= adm_h($data['provider']) ?>" required>
            </div>
            <div>
                <label for="sch-country">Country *</label>
                <input id="sch-country" name="country" type="text" maxlength="100"
                       value="<?= adm_h($data['country']) ?>" required>
            </div>
            <div>
                <label for="sch-degree">Degree Level *</label>
                <select id="sch-degree" name="degree_level" required>
                    <option value="">Select Degree</option>
                    <?php foreach (['Bachelor', 'Master', 'PhD', 'Any'] as $degree): ?>
                        <option value="<?= adm_h($degree) ?>"
                            <?= $data['degree_level'] === $degree ? 'selected' : '' ?>>
                            <?= adm_h($degree) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="sch-field">Field of Study *</label>
                <input id="sch-field" name="field" type="text" maxlength="150"
                       placeholder="e.g. Computer Science"
                       value="<?= adm_h($data['field']) ?>" required>
            </div>
            <div>
                <label for="sch-funding">Funding Type *</label>
                <select id="sch-funding" name="funding_type" required>
                    <option value="">Select Funding</option>
                    <?php foreach (['Fully Funded', 'Partially Funded', 'Tuition Waiver', 'Other'] as $funding): ?>
                        <option value="<?= adm_h($funding) ?>"
                            <?= $data['funding_type'] === $funding ? 'selected' : '' ?>>
                            <?= adm_h($funding) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="sch-deadline">Application Deadline *</label>
                <input id="sch-deadline" name="deadline" type="date"
                       value="<?= adm_h($data['deadline']) ?>" required>
            </div>
            <div>
                <label for="sch-url">Official Application URL *</label>
                <input id="sch-url" name="official_url" type="url" maxlength="2048"
                       placeholder="https://university.example/scholarships"
                       value="<?= adm_h($data['official_url']) ?>" required>
            </div>
        </div>

        <div class="form-full">
            <label for="sch-description">Description</label>
            <textarea id="sch-description" name="description_text" rows="5"><?= adm_h($data['description_text']) ?></textarea>
        </div>

        <?php if ($hasActive): ?>
            <div class="form-full">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1"
                        <?= (int)$data['is_active'] === 1 ? 'checked' : '' ?>>
                    Active scholarship
                </label>
            </div>
        <?php endif; ?>

        <div class="v2-actions">
            <button type="submit" class="v2-btn">
                <?= $editing ? 'Update Scholarship' : 'Save Scholarship' ?>
            </button>
            <?php if ($editing): ?>
                <a href="scholarships.php" class="v2-btn secondary">Cancel Editing</a>
            <?php endif; ?>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="v2-card">
    <h2>All Scholarships (<?= count($scholarships) ?>)</h2>

    <form action="scholarships.php" method="GET" class="admin-v3-inline" style="margin: 14px 0;">
        <input type="search" name="q" maxlength="120" placeholder="Search name, provider or country"
               value="<?= adm_h($search) ?>" style="max-width:350px;">
        <?php if ($hasActive): ?>
            <select name="status" aria-label="Filter by active status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        <?php endif; ?>
        <button class="v2-btn" type="submit">Search</button>
        <a href="scholarships.php" class="v2-btn secondary">Clear</a>
    </form>

    <div class="admin-v3-table-wrap">
        <table class="admin-v3-table">
            <thead>
            <tr>
                <th>ID</th><th>Scholarship</th><th>Provider</th><th>Country</th>
                <th>Degree</th><th>Deadline</th>
                <?php if ($hasActive): ?><th>Status</th><?php endif; ?>
                <?php if ($hasSourceStatus): ?><th>Source</th><?php endif; ?>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$scholarships): ?>
                <tr><td colspan="<?= 7 + (int)$hasActive + (int)$hasSourceStatus ?>">
                    No scholarships found.
                </td></tr>
            <?php endif; ?>

            <?php foreach ($scholarships as $s): ?>
            <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><strong><?= adm_h($s['name']) ?></strong></td>
                <td><?= adm_h($s['provider']) ?></td>
                <td><?= adm_h($s['country']) ?></td>
                <td><?= adm_h($s['degree_level']) ?></td>
                <td>
                    <?= adm_h($s['deadline'] ?? 'Not specified') ?>
                    <?php if (!empty($s['deadline']) && $s['deadline'] < date('Y-m-d')): ?>
                        <small style="color:#b45309;">(Expired)</small>
                    <?php endif; ?>
                </td>
                <?php if ($hasActive): ?>
                    <td><span class="admin-v3-badge">
                        <?= (int)$s['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                    </span></td>
                <?php endif; ?>
                <?php if ($hasSourceStatus): ?>
                    <td>
                        <?= adm_h($s['source_status'] ?: 'unverified') ?>
                        <?php if ($hasVerifiedAt && !empty($s['source_verified_at'])): ?>
                            <small><?= adm_h($s['source_verified_at']) ?></small>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
                <td>
                    <div class="admin-v3-inline">
                        <a href="requirements.php?scholarship_id=<?= (int)$s['id'] ?>">Requirements</a>
                        <?php if ($canEdit): ?>
                            <a href="scholarships.php?edit=<?= (int)$s['id'] ?>#scholarship-form">Edit</a>
                            <form method="POST" action="scholarships.php" style="display:inline;"
                                  onsubmit="return confirm('Delete this scholarship? Related recommendations, bookmarks and requirements may also be removed.');">
                                <input type="hidden" name="csrf_token" value="<?= adm_h(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button class="delete-btn" type="submit">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<p class="admin-v3-muted">
    Eligibility results saved previously may become outdated after editing scholarship
    requirements or details. Students should refresh their matches before relying on them.
    Only mark scholarship sources Verified after checking the official provider information.
</p>

<?php sm_admin_footer(); ?>
