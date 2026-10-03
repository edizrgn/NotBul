<?php
declare(strict_types=1);

function seedNoteSearchTables(PDO $pdo, int $size = 40): void
{
    // Connection-scoped temporary tables shadow the real tables only on this connection.
    // No persistent rows or tables are changed.
    foreach (['note_comments', 'notes', 'users'] as $table) {
        $pdo->exec('CREATE TEMPORARY TABLE fixture_' . $table . ' LIKE ' . $table);
        $pdo->exec('ALTER TABLE fixture_' . $table . ' RENAME TO ' . $table);
    }
    $indexes = preg_replace('/^--.*$/m', '', (string)file_get_contents(__DIR__ . '/../../ops/search-indexes.sql'));
    foreach (array_filter(array_map('trim', explode(';', $indexes))) as $statement) {
        $pdo->exec($statement);
    }
    $pdo->exec("INSERT INTO users (id, first_name, last_name, email, password) VALUES
        (1, 'Test', 'Bir', 'one@example.invalid', 'fixture'), (2, 'Test', 'İki', 'two@example.invalid', 'fixture')");
    $universities = json_decode((string)file_get_contents(__DIR__ . '/../../assets/data/universiteler.json'), true);
    $departments = json_decode((string)file_get_contents(__DIR__ . '/../../assets/data/bolumler.json'), true);
    $insert = $pdo->prepare("INSERT INTO notes (id, user_id, title, description, university_id, department_type, department_id,
        class_id, course, topic, tags, original_filename, mime_type, file_size, upload_status, scan_status, download_count, created_at, deleted_at)
        VALUES (:id, 1, :title, :description, :university_id, 'lisans', :department_id, :class_id, :course, 'Final', :tags,
        :original_filename, :mime_type, 1024, :upload_status, :scan_status, :download_count, '2026-01-01 12:00:00', :deleted_at)");
    $pdo->beginTransaction();
    for ($i = 1; $i <= $size + 3; $i++) {
        $extension = ['pdf', 'docx', 'pptx', 'png'][($i - 1) % 4];
        $row = ['id' => $i, 'title' => 'Ders notu ' . $i, 'description' => str_repeat('Üniversite ders notlarının açıklaması. ', 8),
            'university_id' => (string)$universities[$i % 2]['id'], 'department_id' => (string)$departments['lisans'][$i % 2]['id'],
            'class_id' => (string)($i % 4 + 1), 'course' => $i % 2 ? 'Matematik' : 'Fizik', 'tags' => 'ders',
            'original_filename' => 'not.' . $extension, 'mime_type' => $extension === 'png' ? 'image/png' : 'application/pdf',
            'upload_status' => 'ready', 'scan_status' => 'clean', 'download_count' => $i % 7, 'deleted_at' => null];
        if ($i === 1) {
            $row = array_replace($row, ['title' => 'IŞIK Çalışma', 'description' => 'Şişli için ışık ve ÇÖZÜM', 'course' => 'IŞIK', 'tags' => ' final , özet ', 'download_count' => 100]);
        } elseif ($i === 2) {
            $row['title'] = '100% _ işaret =x';
        } elseif ($i === 4) {
            $row = array_replace($row, ['title' => 'Işık çizimleri', 'course' => 'Tarih', 'tags' => 'özet']);
        } elseif ($i === 5) {
            $row['course'] = 'IŞIK';
        } elseif ($i === 6) {
            $row['title'] = 'Çalışma çizimleri';
        } elseif ($i === 7) {
            $row['title'] = '<script>alert(7)</script>';
            $row['description'] = '<img src=x onerror=alert(7)>';
        } elseif ($i === 8) {
            $row = array_replace($row, ['title' => 'Final çalışma', 'course' => 'Final dersi', 'tags' => 'final']);
        }
        if ($i > $size) {
            $row = array_replace($row, ['title' => 'IŞIK Çalışma özel', 'description' => 'ÖZEL İÇERİK', 'tags' => 'final,özet', 'university_id' => 'private-university']);
            if ($i === $size + 1) $row['deleted_at'] = '2026-01-02 12:00:00';
            if ($i === $size + 2) $row['upload_status'] = 'pending';
            if ($i === $size + 3) $row['scan_status'] = 'infected';
        }
        $insert->execute($row);
    }
    $pdo->exec("INSERT INTO note_comments (id, note_id, user_id, rating, comment) VALUES
        (1, 1, 1, 1, 'önceki'), (2, 1, 1, 5, 'güncel'), (3, 1, 2, 3, 'ikinci kullanıcı'),
        (4, 2, 1, 5, 'tek kullanıcı'), (5, 3, 1, 5, 'birinci'), (6, 3, 2, 5, 'ikinci')");
    $pdo->commit();
    $pdo->query('ANALYZE TABLE notes, note_comments')->fetchAll();
}
