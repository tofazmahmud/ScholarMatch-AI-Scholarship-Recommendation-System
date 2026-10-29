
<?php
require_once __DIR__ . '/includes/student_session.php';
require_once __DIR__ . '/includes/student_helpers.php';

function profile_input(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

$studentToken = $_SESSION['sm_student_token'];

$fields = [
    'education' => 255,
    'degree' => 150,
    'field' => 150,
    'skills' => 1500,
    'interests' => 3000,
    'experience' => 5000,
    'publications' => 5000,
    'projects' => 5000
];

$degrees = ['Bachelor', 'Master', 'PhD', 'Other'];

$data = array_fill_keys(array_keys($fields), '');
$data['gpa'] = '';

$errors = [];

// Load only the current student's profile.
$stmt = $pdo->prepare(
    "SELECT *
     FROM profile
     WHERE session_id = ?
     LIMIT 1"
);

$stmt->execute([$studentToken]);
$profile = $stmt->fetch();

if ($profile) {
    foreach ($data as $key => $unused) {
        $data[$key] = (string)($profile[$key] ?? '');
    }
}


// =========================================
// AI CV EXTRACTION - LOAD DRAFT
// =========================================

// Only load the extracted CV draft immediately
// after a successful upload redirect.
//
// A normal visit to profile.php should show
// the previously saved database information.

$cvImported = false;
$cvImportMessage = '';

if (isset($_SESSION['sm_cv_draft'])) {

    $draft = $_SESSION['sm_cv_draft'];

    $createdAt = (int)(
        $draft['created_at'] ?? 0
    );

    // Draft expires after 15 minutes.
    if (
        $createdAt <= 0 ||
        time() - $createdAt > 900
    ) {

        unset($_SESSION['sm_cv_draft']);

        if (($_GET['cv_import'] ?? '') === '1') {
            $cvImportMessage =
                'Your CV review draft expired. '
                . 'Please upload your CV again.';
        }

    } elseif (
        $_SERVER['REQUEST_METHOD'] === 'GET' &&
        ($_GET['cv_import'] ?? '') === '1' &&
        is_array($draft['data'] ?? null)
    ) {

        // Prefill form with extracted information.
        foreach ($data as $field => $currentValue) {

            $aiValue = $draft['data'][$field] ?? null;

            if (
                is_string($aiValue) &&
                trim($aiValue) !== ''
            ) {
                $data[$field] = trim($aiValue);
            }
        }

        $cvImported = true;
    }

} elseif (($_GET['cv_import'] ?? '') === '1') {

    $cvImportMessage =
        'No CV draft was found. '
        . 'Please upload your CV again.';
}


// =========================================
// PROFILE FORM SUBMISSION
// =========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF verification
    if (!sm_verify_student_csrf(
        $_POST['csrf_token'] ?? null
    )) {
        http_response_code(403);
        exit('Invalid request. Refresh the page.');
    }

    // Validate profile fields.
    foreach ($fields as $name => $maximum) {

        $data[$name] = profile_input($name);

        if (strlen($data[$name]) > $maximum) {
            $errors[] =
                ucfirst($name) . ' is too long.';
        }
    }

    $data['gpa'] = profile_input('gpa');

    // Completed degree is required.
    if (!in_array(
        $data['degree'],
        $degrees,
        true
    )) {
        $errors[] =
            'Select your completed degree.';
    }

    // Field of study is required.
    if ($data['field'] === '') {
        $errors[] =
            'Field of study is required.';
    }

    // GPA is optional, on a 4.00 scale.
    $gpa = null;

    if ($data['gpa'] !== '') {

        if (
            !preg_match(
                '/^\d{1,2}(?:\.\d{1,2})?$/D',
                $data['gpa']
            ) ||
            (float)$data['gpa'] > 4.00
        ) {
            $errors[] =
                'GPA must be between 0 and 4.00.';
        } else {
            $gpa = $data['gpa'];
        }
    }


    // =====================================
    // SAVE CONFIRMED PROFILE TO MYSQL
    // =====================================

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            // Create or reuse one profile
            // for the current anonymous session.
            $stmt = $pdo->prepare(
                "INSERT INTO profile (session_id)
                 VALUES (?)
                 ON DUPLICATE KEY UPDATE
                     id = LAST_INSERT_ID(id)"
            );

            $stmt->execute([$studentToken]);

            $profileId = (int)$pdo->lastInsertId();

            // Update confirmed profile information.
            $stmt = $pdo->prepare(
                "UPDATE profile
                 SET education = ?,
                     degree = ?,
                     field = ?,
                     gpa = ?,
                     skills = ?,
                     interests = ?,
                     experience = ?,
                     publications = ?,
                     projects = ?
                 WHERE id = ?
                   AND session_id = ?"
            );

            $stmt->execute([
                $data['education'],
                $data['degree'],
                $data['field'],
                $gpa,
                $data['skills'],
                $data['interests'],
                $data['experience'],
                $data['publications'],
                $data['projects'],
                $profileId,
                $studentToken
            ]);

            $pdo->commit();

            // Clear AI draft only after
            // student confirmation and successful save.
            unset($_SESSION['sm_cv_draft']);

            header(
                'Location: preferences.php?profile_saved=1'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] =
                'Unable to save profile. '
                . 'Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Profile - ScholarMatch</title>

    <link
        rel="stylesheet"
        href="assets/css/student.css"
    >
<link rel="stylesheet" href="assets/css/v2.css">
</head>

<body>

<header class="site-header">

    <div class="nav-container">

        <a href="index.php" class="brand">
            🎓 ScholarMatch
        </a>

        <nav>
            <a href="index.php">
                Home
            </a>

            <a href="explore.php">
                Explore
            </a>

            <a href="profile.php" class="active">
                My Profile
            </a>

            <a href="preferences.php">
                Preferences
            </a>

            <a href="find_matches.php">
                Find My Matches
            </a>

            <a href="recommendations.php">
                Recommendations
            </a>
        <a href="dashboard.php">Dashboard</a><a href="semantic_search.php">AI Search</a><a href="bookmarks.php">Saved</a><a href="application_tracker.php">Tracker</a><a href="deadline_calendar.php">Deadlines</a>
</nav>

    </div>

</header>


<main class="student-page">

    <div class="student-page-heading">

        <span class="student-eyebrow">
            Student Information
        </span>

        <h1>Build Your Academic Profile</h1>

        <p>
            Enter your educational background,
            skills and experience to prepare
            for scholarship matching.
        </p>

    </div>


    <!-- Validation Errors -->

    <?php if (!empty($errors)): ?>

        <div class="student-error" role="alert">

            <?php foreach ($errors as $error): ?>

                <p>
                    <?= sm_h($error) ?>
                </p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!-- AI CV Extraction Success -->

    <?php if ($cvImported): ?>

        <div
            class="student-info-card"
            role="status"
            style="
                margin-bottom:20px;
                border:1px solid #86efac;
                background:#f0fdf4;
            "
        >

            <h3>
                CV Information Extracted Successfully!
            </h3>

            
<p>
    <strong>Extraction Method:</strong>

    <?= sm_h(
        (string)(
            $_SESSION['sm_cv_draft']['method']
            ?? 'Unknown'
        )
    ) ?>
</p>

<p>
    Academic information has been extracted
    from your CV using the method shown above.
</p>


            <p>
                Please review and edit every field before
                clicking Save and Continue.
                Existing values may remain where the CV
                did not provide information.
            </p>

        </div>

    <?php elseif ($cvImportMessage !== ''): ?>

        <div class="student-error" role="alert">

            <p>
                <?= sm_h($cvImportMessage) ?>
            </p>

        </div>

    <?php endif; ?>


    <!-- AI CV Upload Section -->

    <div
        class="student-info-card"
        style="margin-bottom:25px;"
    >

        <h3>Automatic Profile Extraction</h3>

        <p>
            Save time by uploading your CV.
            ScholarMatch will extract your academic
            details using Gemini AI.
        </p>

        <p>
            You can review and correct the extracted
            information before saving.
        </p>

        <a
            href="upload_cv.php"
            class="primary-btn"
        >
            Upload CV with AI →
        </a>

    </div>


    <!-- Academic Profile Form -->

    <form
        action="profile.php"
        method="POST"
        class="student-form-card"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= sm_h(sm_student_csrf()) ?>"
        >


        <!-- Academic Information -->

        <div class="form-section-title">

            <h2>Academic Information</h2>

            <p>
                Tell us about your education.
            </p>

        </div>


        <div class="student-form-grid">

            <div class="student-field">

                <label for="education">
                    Education
                </label>

                <input
                    id="education"
                    type="text"
                    name="education"
                    maxlength="255"
                    placeholder="e.g. BSc in CSE"
                    value="<?= sm_h($data['education']) ?>"
                >

            </div>


            <div class="student-field">

                <label for="degree">
                    Completed Degree *
                </label>

                <select
                    id="degree"
                    name="degree"
                    required
                >

                    <option value="">
                        Select degree
                    </option>

                    <?php foreach ($degrees as $degree): ?>

                        <option
                            value="<?= sm_h($degree) ?>"
                            <?= $data['degree'] === $degree
                                ? 'selected' : '' ?>
                        >
                            <?= sm_h($degree) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="student-field">

                <label for="field">
                    Field of Study *
                </label>

                <input
                    id="field"
                    name="field"
                    type="text"
                    maxlength="150"
                    placeholder="e.g. Computer Science"
                    value="<?= sm_h($data['field']) ?>"
                    required
                >

            </div>


            <div class="student-field">

                <label for="gpa">
                    GPA (Out of 4.00)
                </label>

                <input
                    id="gpa"
                    name="gpa"
                    type="number"
                    min="0"
                    max="4"
                    step="0.01"
                    placeholder="e.g. 3.90"
                    value="<?= sm_h($data['gpa']) ?>"
                >

                <small>
                    Leave blank if unavailable.
                </small>

            </div>

        </div>


        <!-- Skills and Interests -->

        <div class="form-section-title student-section-gap">

            <h2>Skills and Interests</h2>

            <p>
                Include information relevant to
                scholarship applications.
            </p>

        </div>


        <div class="student-field">

            <label for="skills">
                Skills
            </label>

            <textarea
                id="skills"
                name="skills"
                rows="3"
                maxlength="1500"
                placeholder="Python, PHP, MySQL, Machine Learning"
            ><?= sm_h($data['skills']) ?></textarea>

            <small>
                Separate each skill with a comma.
            </small>

        </div>


        <div class="student-field">

            <label for="interests">
                Research / Academic Interests
            </label>

            <textarea
                id="interests"
                name="interests"
                rows="3"
                maxlength="3000"
                placeholder="Artificial Intelligence, Data Science"
            ><?= sm_h($data['interests']) ?></textarea>

        </div>


        <!-- Additional Information -->

        <div class="form-section-title student-section-gap">

            <h2>Additional Information</h2>

        </div>


        <div class="student-field">

            <label for="experience">
                Experience
            </label>

            <textarea
                id="experience"
                name="experience"
                rows="3"
                maxlength="5000"
                placeholder="Internships, work experience, volunteering"
            ><?= sm_h($data['experience']) ?></textarea>

        </div>


        <div class="student-field">

            <label for="projects">
                Projects
            </label>

            <textarea
                id="projects"
                name="projects"
                rows="3"
                maxlength="5000"
                placeholder="Describe your academic or technical projects"
            ><?= sm_h($data['projects']) ?></textarea>

        </div>


        <div class="student-field">

            <label for="publications">
                Publications
            </label>

            <textarea
                id="publications"
                name="publications"
                rows="3"
                maxlength="5000"
                placeholder="Research papers or publications, if any"
            ><?= sm_h($data['publications']) ?></textarea>

        </div>


        <!-- Save Profile -->

        <div class="student-form-actions">

            <button
                type="submit"
                class="primary-btn"
            >
                Save and Continue →
            </button>

            <a
                href="explore.php"
                class="student-text-link"
            >
                Explore Scholarships
            </a>

        </div>

    </form>


    <!-- Privacy Note -->

    <p class="student-privacy-note">
        No student account is required.
        Your profile is associated with this
        browser session.

        You may lose access to it if the
        session expires.
    </p>

</main>


<footer class="site-footer">

    <p>
        © <?= date('Y') ?> ScholarMatch
    </p>

</footer>

</body>
</html>
