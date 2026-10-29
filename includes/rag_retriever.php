
<?php
// includes/rag_retriever.php

function sm_rag_retrieve(
    PDO $pdo,
    string $studentToken,
    int $scholarshipId
): ?array {

    if ($studentToken === '' || $scholarshipId < 1) {
        return null;
    }

    // Only retrieve recommendations belonging
    // to the current student session.
    $stmt = $pdo->prepare('
        SELECT
            r.eligibility_status,
            r.match_score,
            r.suitability_label,

            s.id AS scholarship_id,
            s.name,
            s.provider,
            s.country,
            s.degree_level,
            s.field AS scholarship_field,
            s.funding_type,
            s.deadline,
            s.official_url,
            s.description_text,

            p.education,
            p.degree AS student_degree,
            p.field AS student_field,
            p.gpa,
            p.skills,
            p.interests,
            p.projects

        FROM recommendation AS r

        INNER JOIN profile AS p
            ON p.id = r.profile_id

        INNER JOIN scholarship AS s
            ON s.id = r.scholarship_id

        WHERE p.session_id = :token
          AND r.scholarship_id = :scholarship_id
          AND s.is_active = 1

        LIMIT 1
    ');

    $stmt->execute([
        'token' => $studentToken,
        'scholarship_id' => $scholarshipId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    // Retrieve exact requirement evidence.
    $rulesStmt = $pdo->prepare('
        SELECT
            id,
            requirement_type,
            comparison_operator,
            value,
            certainty
        FROM requirement
        WHERE scholarship_id = :scholarship_id
        ORDER BY id ASC
    ');

    $rulesStmt->execute([
        'scholarship_id' => $scholarshipId
    ]);

    $requirements = [];

    foreach (
        $rulesStmt->fetchAll(PDO::FETCH_ASSOC)
        as $rule
    ) {
        $requirements[] = [
            'id' => (int)$rule['id'],
            'type' => (string)$rule['requirement_type'],
            'operator' => (string)$rule['comparison_operator'],
            'value' => (string)$rule['value'],
            'certainty' => (string)$rule['certainty']
        ];
    }

    // Optional document passages; fail safely before/without V2 migration.
    $documentSources=[];
    try {
        require_once __DIR__ . '/document_chunks.php';
        $documentSources=sm_document_evidence($pdo,$scholarshipId,['field'=>$row['student_field'],'interests'=>$row['interests'],'skills'=>$row['skills']],4);
    } catch (Throwable $e) { error_log('RAG document lookup unavailable: '.$e->getMessage()); }

    // Return only relevant student fields.
    // No student name or contact details.
    return [

        'student' => [
            'education' => $row['education'],
            'degree' => $row['student_degree'],
            'field' => $row['student_field'],
            'gpa' => $row['gpa'],
            'skills' => $row['skills'],
            'interests' => $row['interests'],
            'projects' => $row['projects']
        ],

        'scholarship' => [
            'id' => (int)$row['scholarship_id'],
            'name' => $row['name'],
            'provider' => $row['provider'],
            'country' => $row['country'],
            'degree_level' => $row['degree_level'],
            'field' => $row['scholarship_field'],
            'funding_type' => $row['funding_type'],
            'deadline' => $row['deadline'],
            'description' => $row['description_text'],
            'official_url' => $row['official_url']
        ],

        'requirements' => $requirements,
        'document_sources' => $documentSources,

        'match' => [
            'status' => $row['eligibility_status'],
            'score' => $row['match_score'],
            'suitability' => $row['suitability_label']
        ]
    ];
}
