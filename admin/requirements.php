<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();
require_once __DIR__ . '/../config/db.php';

$canEdit = admin_can_edit();
$allowed = [
    'gpa' => ['=', '!=', '>', '>=', '<', '<='],
    'degree' => ['=', '!=', 'IN', 'NOT IN'],
    'field' => ['=', '!=', 'IN', 'NOT IN'],
    'skills' => ['CONTAINS'],
    'other' => ['=']
];
$labels = [
    'gpa' => 'GPA (4.00 scale)',
    'degree' => 'Completed Degree',
    'field' => 'Field of Study',
    'skills' => 'Skill',
    'other' => 'Other / Manual Check'
];
$rawId = $_POST['scholarship_id'] ?? $_GET['scholarship_id'] ?? null;
$scholarshipId = is_scalar($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]) : false;
$allScholarships = $pdo->query('SELECT id, name FROM scholarship ORDER BY name')
    ->fetchAll(PDO::FETCH_ASSOC);
$scholarship = null;
if ($scholarshipId !== false) {
    $stmt = $pdo->prepare('SELECT id, name FROM scholarship WHERE id = ?');
    $stmt->execute([$scholarshipId]);
    $scholarship = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$error = '';
$editId = 0;
$form = [
    'requirement_type' => 'gpa',
    'comparison_operator' => '>=',
    'value' => '',
    'certainty' => 'explicit'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin_editor();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !valid_csrf($token)) {
        http_response_code(403);
        exit('Invalid security token.');
    }
    if (!$scholarship) {
        $error = 'Select a valid scholarship first.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                $error = 'Invalid requirement ID.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'DELETE FROM requirement WHERE id = ? AND scholarship_id = ?'
                    );
                    $stmt->execute([$id, $scholarshipId]);
                    if ($stmt->rowCount() !== 1) {
                        $error = 'Requirement not found.';
                    } else {
                        admin_log($pdo, 'requirement.delete', 'requirement', (int)$id,
                            'Scholarship #' . $scholarshipId);
                        header('Location: requirements.php?scholarship_id=' . $scholarshipId . '&msg=deleted');
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log('Requirement delete: ' . $e->getMessage());
                    $error = 'Unable to delete requirement.';
                }
            }
        } elseif ($action === 'save') {
            foreach (array_keys($form) as $key) {
                $submitted = $_POST[$key] ?? '';
                $form[$key] = is_string($submitted) ? trim($submitted) : '';
            }
            $idValue = $_POST['id'] ?? '0';
            $id = is_scalar($idValue) ? filter_var($idValue, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0]]) : false;
            $type = $form['requirement_type'];
            $operator = $form['comparison_operator'];
            $value = $form['value'];
            $certainty = $form['certainty'];
            if ($id === false) {
                $error = 'Invalid requirement ID.';
            } elseif (!isset($allowed[$type]) || !in_array($operator, $allowed[$type], true)) {
                $error = 'Invalid type or operator.';
            } elseif ($value === '' || strlen($value) > 1000) {
                $error = 'Value must have 1–1000 characters.';
            } elseif (!in_array($certainty, ['explicit', 'ambiguous'], true)) {
                $error = 'Invalid certainty.';
            } elseif ($type === 'gpa' &&
                (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $value) || (float)$value > 4.0)) {
                $error = 'Enter a GPA between 0 and 4.00.';
            } elseif (in_array($operator, ['IN', 'NOT IN'], true) &&
                count(array_filter(array_map('trim', explode(',', $value)), 'strlen')) < 2) {
                $error = 'Enter at least two comma-separated values.';
            } elseif ($type === 'skills' && str_contains($value, ',')) {
                $error = 'Enter one skill per requirement.';
            }
            if ($type === 'other') {
                $certainty = 'ambiguous';
                $form['certainty'] = 'ambiguous';
            }
            if ($error === '') {
                try {
                    if ($id > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE requirement SET requirement_type = ?, comparison_operator = ?,
                             value = ?, certainty = ? WHERE id = ? AND scholarship_id = ?'
                        );
                        $stmt->execute([$type, $operator, $value, $certainty, $id, $scholarshipId]);
                        $check = $pdo->prepare('SELECT id FROM requirement WHERE id = ? AND scholarship_id = ?');
                        $check->execute([$id, $scholarshipId]);
                        if (!$check->fetchColumn()) throw new RuntimeException('Requirement not found.');
                        admin_log($pdo, 'requirement.update', 'requirement', (int)$id,
                            'Scholarship #' . $scholarshipId);
                        $msg = 'updated';
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO requirement
                             (scholarship_id, requirement_type, comparison_operator, value, certainty)
                             VALUES (?, ?, ?, ?, ?)'
                        );
                        $stmt->execute([$scholarshipId, $type, $operator, $value, $certainty]);
                        $newId = (int)$pdo->lastInsertId();
                        admin_log($pdo, 'requirement.create', 'requirement', $newId,
                            'Scholarship #' . $scholarshipId);
                        $msg = 'added';
                    }
                    header('Location: requirements.php?scholarship_id=' . $scholarshipId . '&msg=' . $msg);
                    exit;
                } catch (Throwable $e) {
                    error_log('Requirement save: ' . $e->getMessage());
                    $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save requirement.';
                }
            }
            $editId = $id !== false ? (int)$id : 0;
        } else {
            $error = 'Unknown action.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $scholarship && isset($_GET['edit'])) {
    if (!$canEdit) {
        http_response_code(403);
        exit('Viewer accounts cannot edit requirements.');
    }
    $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        $error = 'Invalid requirement ID.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM requirement WHERE id = ? AND scholarship_id = ?');
        $stmt->execute([$id, $scholarshipId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $editId = (int)$id;
            foreach (array_keys($form) as $key) $form[$key] = (string)$existing[$key];
        } else {
            $error = 'Requirement not found.';
        }
    }
}
$requirements = [];
if ($scholarship) {
    $stmt = $pdo->prepare('SELECT * FROM requirement WHERE scholarship_id = ? ORDER BY id DESC');
    $stmt->execute([$scholarshipId]);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$messages = ['added' => 'Requirement added.', 'updated' => 'Requirement updated.',
    'deleted' => 'Requirement deleted.'];
$msg = $_GET['msg'] ?? '';
$success = is_string($msg) ? ($messages[$msg] ?? '') : '';
sm_admin_header('Requirement Management', 'requirements');
?>
<p class="admin-v3-muted">Manage scholarship GPA, degree, field, skill and manual-review eligibility rules.</p>
<?php if ($error): ?><div class="admin-v3-danger" role="alert"><?= adm_h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-v3-success" role="status"><?= adm_h($success) ?></div><?php endif; ?>
<section class="v2-card">
  <h2>Select Scholarship</h2>
  <form method="get" action="requirements.php" class="v2-form">
    <label>Scholarship
      <select name="scholarship_id" required>
        <option value="">Choose a scholarship</option>
        <?php foreach ($allScholarships as $item): ?>
          <option value="<?= (int)$item['id'] ?>" <?= $scholarshipId == $item['id'] ? 'selected' : '' ?>>
            <?= adm_h($item['name']) ?> (#<?= (int)$item['id'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="v2-btn">Open Requirements</button>
  </form>
</section>
<?php if ($scholarship): ?>
  <?php if ($canEdit): ?>
  <section class="v2-card">
    <h2><?= $editId ? 'Edit' : 'Add' ?> Requirement · <?= adm_h($scholarship['name']) ?></h2>
    <form method="post" action="requirements.php?scholarship_id=<?= (int)$scholarshipId ?>" class="v2-form">
      <input type="hidden" name="csrf_token" value="<?= adm_h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="scholarship_id" value="<?= (int)$scholarshipId ?>">
      <input type="hidden" name="id" value="<?= (int)$editId ?>">
      <label>Requirement Type
        <select name="requirement_type" id="requirement_type" required>
          <?php foreach ($labels as $type => $label): ?>
            <option value="<?= adm_h($type) ?>" <?= $form['requirement_type'] === $type ? 'selected' : '' ?>><?= adm_h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Comparison Operator
        <select name="comparison_operator" id="comparison_operator" required>
          <?php foreach ($allowed[$form['requirement_type']] ?? ['='] as $op): ?>
            <option value="<?= adm_h($op) ?>" <?= $form['comparison_operator'] === $op ? 'selected' : '' ?>><?= adm_h($op) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Required Value
        <input name="value" maxlength="1000" value="<?= adm_h($form['value']) ?>" required placeholder="3.50 or Computer Science">
      </label>
      <label>Certainty
        <select name="certainty" id="certainty">
          <option value="explicit" <?= $form['certainty'] === 'explicit' ? 'selected' : '' ?>>Explicit</option>
          <option value="ambiguous" <?= $form['certainty'] === 'ambiguous' ? 'selected' : '' ?>>Ambiguous</option>
        </select>
      </label>
      <p class="admin-v3-muted">For IN / NOT IN, separate values using commas.
      Other / Manual Check is always treated as ambiguous.</p>
      <button class="v2-btn" type="submit"><?= $editId ? 'Update Requirement' : 'Save Requirement' ?></button>
      <?php if ($editId): ?>
        <a href="requirements.php?scholarship_id=<?= (int)$scholarshipId ?>" class="v2-btn secondary">Cancel Editing</a>
      <?php endif; ?>
    </form>
  </section>
  <?php endif; ?>
  <section class="v2-card">
    <h2>Requirements for <?= adm_h($scholarship['name']) ?></h2>
    <div class="admin-v3-table-wrap">
      <table class="admin-v3-table">
        <thead><tr><th>ID</th><th>Type</th><th>Operator</th><th>Value</th><th>Certainty</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($requirements as $rule): ?>
          <tr>
            <td><?= (int)$rule['id'] ?></td>
            <td><?= adm_h($rule['requirement_type']) ?></td>
            <td><?= adm_h($rule['comparison_operator']) ?></td>
            <td><?= adm_h($rule['value']) ?></td>
            <td><?= adm_h($rule['certainty']) ?></td>
            <td>
              <?php if ($canEdit): ?>
                <a href="requirements.php?scholarship_id=<?= (int)$scholarshipId ?>&amp;edit=<?= (int)$rule['id'] ?>">Edit</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this requirement?');">
                  <input type="hidden" name="csrf_token" value="<?= adm_h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="scholarship_id" value="<?= (int)$scholarshipId ?>">
                  <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                  <button type="submit" class="v2-btn secondary">Delete</button>
                </form>
              <?php else: ?>Read-only<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$requirements): ?><tr><td colspan="6">No requirements added yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
<?php if ($canEdit): ?>
<script>
const allowedOperators = <?= json_encode($allowed, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const typeElement = document.getElementById('requirement_type');
const operatorElement = document.getElementById('comparison_operator');
const certaintyElement = document.getElementById('certainty');
if (typeElement && operatorElement) {
  typeElement.addEventListener('change', () => {
    operatorElement.replaceChildren(...(allowedOperators[typeElement.value] || ['='])
      .map(op => new Option(op, op)));
    if (typeElement.value === 'other') certaintyElement.value = 'ambiguous';
  });
}
</script>
<?php endif; ?>
<?php sm_admin_footer(); ?>
