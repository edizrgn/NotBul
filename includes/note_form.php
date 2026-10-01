<?php
declare(strict_types=1);

/** Keep submitted note fields together so validation failures can restore the form. */
function noteFormValues(array $source): array
{
    $values = [];
    foreach (['title', 'description', 'university_id', 'department_type', 'department_id', 'class_id', 'course', 'topic', 'tags'] as $field) {
        $values[$field] = is_scalar($source[$field] ?? null) ? trim((string)$source[$field]) : '';
    }

    return $values;
}

function noteFormErrors(array $values): array
{
    $errors = [];
    if ($values['title'] === '' || $values['course'] === '') {
        $errors[] = 'Başlık ve Ders alanları zorunludur.';
    }

    $limits = [
        'title' => [160, 'Başlık'],
        'description' => [1000, 'Açıklama'],
        'course' => [150, 'Ders adı'],
        'topic' => [150, 'Konu adı'],
        'tags' => [255, 'Etiketler'],
        'university_id' => [50, 'Üniversite'],
        'department_id' => [50, 'Bölüm'],
    ];
    foreach ($limits as $field => [$limit, $label]) {
        if (mb_strlen($values[$field], 'UTF-8') > $limit) {
            $errors[] = $label . ' ' . $limit . ' karakteri geçemez.';
        }
    }

    if ($values['department_type'] !== '' && !in_array($values['department_type'], ['lisans', 'onlisans'], true)) {
        $errors[] = 'Geçerli bir program türü seçin.';
    }
    if ($values['class_id'] !== '' && !in_array($values['class_id'], ['1', '2', '3', '4'], true)) {
        $errors[] = 'Geçerli bir sınıf seçin.';
    }

    return $errors;
}

function noteFormBindings(array $values): array
{
    foreach (['university_id', 'department_type', 'department_id', 'class_id'] as $field) {
        if ($values[$field] === '') {
            $values[$field] = null;
        }
    }

    return $values;
}

/** Only expose the public fields needed by course and topic suggestions. */
function noteFormSuggestions(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT university_id, department_type, department_id, class_id, course, topic
            FROM notes
            WHERE upload_status = 'ready'
              AND scan_status = 'clean'
              AND deleted_at IS NULL
        ");
        $suggestions = [];
        foreach ($stmt->fetchAll() as $row) {
            $suggestions[] = [
                'universityId' => (string)($row['university_id'] ?? ''),
                'departmentType' => (string)($row['department_type'] ?? ''),
                'departmentId' => (string)($row['department_id'] ?? ''),
                'classId' => (string)($row['class_id'] ?? ''),
                'course' => (string)($row['course'] ?? ''),
                'topic' => (string)($row['topic'] ?? ''),
            ];
        }
        return $suggestions;
    } catch (Throwable $e) {
        error_log('note form suggestions error: ' . $e->getMessage());
        return [];
    }
}
