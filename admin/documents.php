<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cv_document_parser.php';
require_once __DIR__ . '/../includes/document_chunks.php';

$canEdit = admin_can_edit();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin_editor();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !valid_csrf($token)) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new RuntimeException('Invalid document ID.');
            $stmt = $pdo->prepare('DELETE FROM scholarship_document WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Document not found.');
            }
            admin_log($pdo, 'document.delete', 'document', (int)$id);
            $success = 'Document and associated text chunks deleted.';
        } elseif ($action === 'upload') {
            $sid = filter_input(INPUT_POST, 'scholarship_id', FILTER_VALIDATE_INT);
            $title = trim((string)($_POST['title'] ?? ''));
            $url = trim((string)($_POST['source_url'] ?? ''));
            if (!$sid || $sid < 1 || $title === '' || strlen($title) > 255) {
                throw new RuntimeException('Select a scholarship and enter a title (max 255 characters).');
            }
            if ($url !== '' && (
                strlen($url) > 2048 ||
                !filter_var($url, FILTER_VALIDATE_URL) ||
                !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            )) {
                throw new RuntimeException('Invalid source URL. Use HTTP or HTTPS.');
            }
            $file = $_FILES['document'] ?? null;
            if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK ||
                (int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 4 * 1024 * 1024 ||
                !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
                throw new RuntimeException('Choose a PDF or DOCX file (maximum 4 MB).');
            }
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'docx'], true)) {
                throw new RuntimeException('Only PDF and DOCX documents are supported.');
            }
            $check = $pdo->prepare('SELECT id FROM scholarship WHERE id = ?');
            $check->execute([$sid]);
            if (!$check->fetchColumn()) throw new RuntimeException('Unknown scholarship.');

            $text = sm_cv_read_document((string)$file['tmp_name'], (string)$file['name']);
            $parts = sm_document_chunks($text);
            if (strlen(trim($text)) < 80 || !$parts) {
                throw new RuntimeException('Insufficient readable text. Scanned documents need OCR.');
            }
            // Verification is a manual administrator attestation, not automatic.
            $verified = ($url !== '' && ($_POST['verified'] ?? '') === 'yes') ? 1 : 0;
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO scholarship_document (scholarship_id, title, source_url, is_verified)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$sid, $title, $url !== '' ? $url : null, $verified]);
                $docId = (int)$pdo->lastInsertId();
                $insert = $pdo->prepare(
                    'INSERT INTO scholarship_chunk (document_id, sequence_no, chunk_text) VALUES (?, ?, ?)'
                );
                foreach ($parts as $number => $part) {
                    $insert->execute([$docId, $number + 1, $part]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            admin_log($pdo, 'document.upload', 'document', $docId,
                'Scholarship #' . (int)$sid . '; ' . count($parts) . ' chunks');
            $success = 'Document indexed into ' . count($parts) . ' text chunks.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        error_log('Admin documents: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() :
            'Document operation failed. Check System Health and V2 database tables.';
    }
}

$scholarships = $pdo->query('SELECT id, name FROM scholarship ORDER BY name LIMIT 1000')
    ->fetchAll(PDO::FETCH_ASSOC);
$documents = [];
try {
    $documents = $pdo->query(
        'SELECT d.id, d.title, d.is_verified, d.source_url, s.name AS scholarship_name,
                (SELECT COUNT(*) FROM scholarship_chunk c WHERE c.document_id = d.id) AS chunks
         FROM scholarship_document d
         JOIN scholarship s ON s.id = d.scholarship_id
         ORDER BY d.id DESC LIMIT 200'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Document listing: ' . $e->getMessage());
    $error = 'Document tables are unavailable. Check V2 migration and System Health.';
}
sm_admin_header('RAG Evidence Documents', 'documents');
?>
<p class="admin-v3-muted">Upload official scholarship guidelines. The system stores extracted text chunks,
not the original uploaded file. Verification is a manual admin decision.</p>
<?php if ($error): ?><div class="admin-v3-danger" role="alert"><?= adm_h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-v3-success" role="status"><?= adm_h($success) ?></div><?php endif; ?>
<?php if ($canEdit): ?>
<section class="v2-card">
  <h2>Add supporting document</h2>
  <form method="post" enctype="multipart/form-data" class="v2-form">
    <input type="hidden" name="csrf_token" value="<?= adm_h(csrf_token()) ?>">
    <input type="hidden" name="action" value="upload">
    <label>Scholarship
      <select name="scholarship_id" required>
        <option value="">Select scholarship</option>
        <?php foreach ($scholarships as $sch): ?>
          <option value="<?= (int)$sch['id'] ?>"><?= adm_h($sch['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Document title <input name="title" maxlength="255" required placeholder="Official eligibility guidelines"></label>
    <label>PDF / DOCX (max 4 MB) <input type="file" name="document" accept=".pdf,.docx" required></label>
    <label>Source URL (optional) <input type="url" name="source_url" maxlength="2048"></label>
    <label><input type="checkbox" name="verified" value="yes"> I manually verified this document against the official provider source.</label>
    <button type="submit" class="v2-btn">Extract &amp; Index</button>
  </form>
</section>
<?php endif; ?>
<section class="v2-card">
  <h2>Indexed sources</h2>
  <div class="admin-v3-table-wrap">
    <table class="admin-v3-table">
      <thead><tr><th>Scholarship / Document</th><th>Chunks</th><th>Verification</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($documents as $doc): ?>
        <tr>
          <td><?= adm_h($doc['scholarship_name']) ?><br><strong><?= adm_h($doc['title']) ?></strong></td>
          <td><?= (int)$doc['chunks'] ?></td>
          <td><?= $doc['is_verified'] ? 'Verified by admin' : 'Unverified' ?></td>
          <td>
            <?php if ($canEdit): ?>
              <form method="post" onsubmit="return confirm('Delete this document and its chunks?');">
                <input type="hidden" name="csrf_token" value="<?= adm_h(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                <button type="submit" class="v2-btn secondary">Delete</button>
              </form>
            <?php else: ?>Read-only<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$documents): ?><tr><td colspan="4">No indexed documents found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php sm_admin_footer(); ?>
