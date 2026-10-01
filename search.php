<?php
declare(strict_types=1);

@session_start();

require_once __DIR__ . '/includes/ratings.php';

$notesPayload = [];
$dbUnavailable = false;

try {
    require_once __DIR__ . '/includes/db.php';
} catch (Throwable $e) {
    $dbUnavailable = true;
}

if (!$dbUnavailable) {
    try {
        $stmt = $pdo->query("
            SELECT
                n.id,
                n.title,
                n.description,
                n.university_id,
                n.department_type,
                n.department_id,
                n.class_id,
                n.course,
                n.topic,
                n.tags,
                n.original_filename,
                n.mime_type,
                n.download_count AS download_count,
                n.created_at,
                rs.rating_average,
                COALESCE(rs.rating_count, 0) AS rating_count,
                u.first_name,
                u.last_name
            FROM notes n
            JOIN users u ON n.user_id = u.id
            LEFT JOIN (
                " . noteRatingSummarySql() . "
            ) rs ON rs.note_id = n.id
            WHERE n.upload_status = 'ready'
              AND n.scan_status = 'clean'
              AND n.deleted_at IS NULL
            ORDER BY n.created_at DESC
        ");
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $tags = array_values(array_filter(array_map('trim', explode(',', (string)($row['tags'] ?? '')))));
            $ext = strtolower(pathinfo((string)$row['original_filename'], PATHINFO_EXTENSION));
            $fileType = in_array($ext, ['pdf', 'docx', 'pptx'], true)
                ? $ext
                : (str_starts_with((string)$row['mime_type'], 'image/') ? 'image' : 'other');

            $notesPayload[] = [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'description' => (string)($row['description'] ?? ''),
                'uploader' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
                'universityId' => (string)($row['university_id'] ?? ''),
                'departmentType' => (string)($row['department_type'] ?? ''),
                'departmentId' => (string)($row['department_id'] ?? ''),
                'classId' => (string)($row['class_id'] ?? ''),
                'course' => (string)($row['course'] ?? ''),
                'topic' => (string)($row['topic'] ?? ''),
                'tags' => $tags,
                'views' => 0,
                'downloads' => (int)($row['download_count'] ?? 0),
                'ratingAverage' => ratingAverageValue($row['rating_average'] ?? null, (int)($row['rating_count'] ?? 0)),
                'ratingCount' => (int)($row['rating_count'] ?? 0),
                'fileType' => $fileType,
                'createdAt' => (string)$row['created_at']
            ];
        }
    } catch (Throwable $e) {
        $notesPayload = [];
        $dbUnavailable = true;
        error_log('search query error: ' . $e->getMessage());
    }
}

$pageTitle = 'Not Bul | Ders Notu Bul';
$pageKey = 'search';
require __DIR__ . '/includes/header.php';
?>
<main id="mainContent" class="page-shell" tabindex="-1">
    <section class="container section-block">
        <?php if ($dbUnavailable): ?>
            <div class="alert alert-warning" role="alert">Notlar şu anda getirilemiyor. Lütfen daha sonra tekrar deneyin.</div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <h1 class="section-title mb-0">Ders Notu Bul</h1>
            <div class="search-box-inline">
                <label for="searchQuery" class="visually-hidden">Not ara</label>
                <input id="searchQuery" name="q" class="form-control" type="search" placeholder="Başlık, açıklama veya etiket ara" aria-controls="searchResults" autocomplete="off">
            </div>
        </div>
        <div class="row g-4 align-items-start">
            <aside class="col-lg-4 col-xl-3">
                <details class="panel-card search-filter-panel" id="searchFilterPanel" open>
                    <summary class="search-filter-summary">Detaylı filtreler</summary>
                <form id="searchFilterForm" class="mt-3" data-hierarchy-group data-filter-source="public" data-options-scope="notes">

                    <div class="mb-3">
                        <label class="form-label" for="searchUniversity">Üniversite</label>
                        <select class="form-select" id="searchUniversity" name="university_id" data-level="university" data-placeholder="Tüm üniversiteler"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchDepartmentType">Program Türü</label>
                        <select class="form-select" id="searchDepartmentType" name="department_type" data-level="department-type" data-placeholder="Program türü seç"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchDepartment">Bölüm</label>
                        <select class="form-select" id="searchDepartment" name="department_id" data-level="department" data-placeholder="Tüm bölümler"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchClass">Sınıf</label>
                        <select class="form-select" id="searchClass" name="class_id" data-level="class" data-placeholder="Tüm sınıflar"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchCourse">Ders</label>
                        <select class="form-select" id="searchCourse" name="course" data-level="course" data-placeholder="Tüm dersler"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchTopic">Konu</label>
                        <select class="form-select" id="searchTopic" name="topic" data-level="topic" data-placeholder="Tüm konular"></select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="searchFileType">Dosya Türü</label>
                        <select class="form-select" id="searchFileType" name="file_type">
                            <option value="">Tüm dosya türleri</option>
                            <option value="pdf">PDF</option>
                            <option value="docx">DOCX</option>
                            <option value="pptx">PPTX</option>
                            <option value="image">Görsel</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline-secondary w-100 mt-3" data-reset-search>Filtreleri temizle</button>
                </form>
                </details>
            </aside>

            <div class="col-lg-8 col-xl-9">
                <div class="panel-card" id="searchResultsPanel" tabindex="-1">
                    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
                        <p class="mb-0">Toplam sonuç: <strong id="searchResultCount">0</strong></p>
                        <div>
                            <label for="searchSort" class="form-label small">Sıralama</label>
                            <select id="searchSort" class="form-select form-select-sm" name="sort">
                                <option value="relevance">En ilgili</option>
                                <option value="newest">En yeni</option>
                                <option value="rating">En yüksek puan</option>
                                <option value="downloads">En çok indirilen</option>
                            </select>
                        </div>
                    </div>
                    <p id="searchStatus" class="search-status small text-secondary" role="status" aria-live="polite" aria-atomic="true">Notlar hazırlanıyor…</p>
                    <div id="searchResults" class="search-results"></div>
                    <noscript><p class="alert alert-info">Arama ve filtreleme için tarayıcınızda JavaScript'i etkinleştirin.</p></noscript>
                    <nav class="mt-4" aria-label="Sayfalama">
                        <ul id="searchPagination" class="pagination justify-content-center mb-0"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </section>
</main>
<script>
window.NOTBUL_NOTES = <?= json_encode($notesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
