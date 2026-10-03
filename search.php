<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/note_search.php';
$format = is_string($_GET['format'] ?? null) ? $_GET['format'] : '';
$jsonResponse = in_array($format, ['json', 'home', 'options'], true);
$dbUnavailable = false;
$inputError = '';
$request = noteSearchRequest([]);
$filterOptions = [];
$latestHomeRows = [];
$result = ['request' => $request, 'rows' => [], 'total' => 0, 'pages' => 1, 'page_size' => 10];
try {
    $request = noteSearchRequest($_GET);
} catch (InvalidArgumentException $e) {
    $inputError = $e->getMessage();
    $request['q'] = is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 200) : '';
}
try {
    require_once __DIR__ . '/includes/db.php';
    if ($inputError === '') {
        if ($format !== 'options') {
            if ($format === 'home') {
                $request['page'] = 1;
                $request['similar_to'] = 0;
            }
            $homeActive = $request['q'] !== '' || array_filter($request['filters'], static fn($value): bool => $value !== '') !== [];
            if ($format === 'home' && !$homeActive) {
                $featured = noteSearchFeatured($pdo);
                $latestHomeRows = $featured['latest'];
                $result = ['request' => $request, 'rows' => $featured['popular'],
                    'total' => (int)$pdo->query('SELECT COUNT(*) FROM notes n WHERE ' . noteSearchVisibility())->fetchColumn(), 'pages' => 1, 'page_size' => 6];
            } else {
                $result = noteSearchPage($pdo, $request, $format === 'home' ? 6 : 10);
            }
            $request = $result['request'];
        }
        if (!$jsonResponse || $format === 'options' || ($_GET['include_options'] ?? '') === '1') {
            $filterOptions = noteSearchOptions($pdo, $request['filters']);
        }
    }
} catch (Throwable $e) {
    $dbUnavailable = true;
    error_log('server note search: ' . $e->getMessage());
}
$result['request'] = $request;
if ($jsonResponse) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    if ($dbUnavailable || $inputError !== '') {
        http_response_code($dbUnavailable ? 503 : 400);
        echo json_encode(['error' => $inputError ?: 'Notlar şu anda getirilemiyor. Lütfen tekrar deneyin.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } else {
        $filterOptionsHtml = [];
        $placeholders = ['university_id' => 'Tüm üniversiteler', 'department_type' => 'Tüm program türleri',
            'department_id' => 'Tüm bölümler', 'class_id' => 'Tüm sınıflar', 'course' => 'Tüm dersler', 'topic' => 'Tüm konular'];
        foreach ($filterOptions as $field => $items) {
            $filterOptionsHtml[$field] = noteSearchOptionsHtml($items, $request['filters'][$field], $placeholders[$field]);
        }
        echo json_encode([
            'resultsHtml' => $format === 'options' ? '' : noteSearchResults($result, $format === 'home', $format === 'home' ? 'index.php' : 'search.php'),
            'paginationHtml' => $format === 'json' ? noteSearchPagination($result) : '',
            'total' => $result['total'], 'countLabel' => number_format($result['total'], 0, ',', '.'),
            'status' => noteSearchStatus($result), 'page' => $request['page'],
            'queryString' => noteSearchQueryString($request), 'options' => $filterOptionsHtml,
            'searchActive' => $request['q'] !== '' || array_filter($request['filters'], static fn($value): bool => $value !== '') !== [],
            'latestHtml' => $format === 'home' ? noteSearchResults(array_replace($result, ['rows' => $latestHomeRows]), true, 'index.php') : '',
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
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
        <?php if ($inputError !== ''): ?>
            <div class="alert alert-warning" role="alert"><?= noteSearchEscape($inputError) ?></div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <h1 class="section-title mb-0">Ders Notu Bul</h1>
            <div class="search-box-inline d-flex gap-2">
                <label for="searchQuery" class="visually-hidden">Not ara</label>
                <input id="searchQuery" name="q" form="searchFilterForm" value="<?= noteSearchEscape($request['q']) ?>" maxlength="200" class="form-control" type="search" placeholder="Başlık, açıklama veya etiket ara" aria-controls="searchResults" autocomplete="off">
                <button class="btn btn-primary" type="submit" form="searchFilterForm">Ara</button>
            </div>
        </div>
        <div class="row g-4 align-items-start">
            <aside class="col-lg-4 col-xl-3">
                <details class="panel-card search-filter-panel" id="searchFilterPanel" open>
                    <summary class="search-filter-summary">Detaylı filtreler</summary>
                <form id="searchFilterForm" method="GET" action="search.php" class="mt-3" data-hierarchy-group data-server-filters>
                    <input type="hidden" name="similar_to" value="<?= $request['similar_to'] ?>">

                    <div class="mb-3">
                        <label class="form-label" for="searchUniversity">Üniversite</label>
                        <select class="form-select" id="searchUniversity" name="university_id" data-level="university" data-placeholder="Tüm üniversiteler"><?= noteSearchOptionsHtml($filterOptions['university_id'] ?? [], $request['filters']['university_id'], 'Tüm üniversiteler') ?></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchDepartmentType">Program Türü</label>
                        <select class="form-select" id="searchDepartmentType" name="department_type" data-level="department-type" data-placeholder="Program türü seç"><?= noteSearchOptionsHtml($filterOptions['department_type'] ?? [], $request['filters']['department_type'], 'Tüm program türleri') ?></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchDepartment">Bölüm</label>
                        <select class="form-select" id="searchDepartment" name="department_id" data-level="department" data-placeholder="Tüm bölümler"><?= noteSearchOptionsHtml($filterOptions['department_id'] ?? [], $request['filters']['department_id'], 'Tüm bölümler') ?></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchClass">Sınıf</label>
                        <select class="form-select" id="searchClass" name="class_id" data-level="class" data-placeholder="Tüm sınıflar"><?= noteSearchOptionsHtml($filterOptions['class_id'] ?? [], $request['filters']['class_id'], 'Tüm sınıflar') ?></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchCourse">Ders</label>
                        <select class="form-select" id="searchCourse" name="course" data-level="course" data-placeholder="Tüm dersler"><?= noteSearchOptionsHtml($filterOptions['course'] ?? [], $request['filters']['course'], 'Tüm dersler') ?></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchTopic">Konu</label>
                        <select class="form-select" id="searchTopic" name="topic" data-level="topic" data-placeholder="Tüm konular"><?= noteSearchOptionsHtml($filterOptions['topic'] ?? [], $request['filters']['topic'], 'Tüm konular') ?></select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="searchFileType">Dosya Türü</label>
                        <select class="form-select" id="searchFileType" name="file_type">
                            <option value="">Tüm dosya türleri</option>
                            <option value="pdf" <?= $request['filters']['file_type'] === 'pdf' ? 'selected' : '' ?>>PDF</option>
                            <option value="docx" <?= $request['filters']['file_type'] === 'docx' ? 'selected' : '' ?>>DOCX</option>
                            <option value="pptx" <?= $request['filters']['file_type'] === 'pptx' ? 'selected' : '' ?>>PPTX</option>
                            <option value="image" <?= $request['filters']['file_type'] === 'image' ? 'selected' : '' ?>>Görsel</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">Filtreleri Uygula</button>
                    <a href="search.php" class="btn btn-outline-secondary w-100 mt-2" data-reset-search>Filtreleri temizle</a>
                </form>
                </details>
            </aside>

            <div class="col-lg-8 col-xl-9">
                <div class="panel-card" id="searchResultsPanel" tabindex="-1" data-page="<?= $request['page'] ?>">
                    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
                        <p class="mb-0">Toplam sonuç: <strong id="searchResultCount"><?= number_format($result['total'], 0, ',', '.') ?></strong></p>
                        <div>
                            <label for="searchSort" class="form-label small">Sıralama</label>
                            <select id="searchSort" class="form-select form-select-sm" name="sort" form="searchFilterForm">
                                <option value="relevance" <?= $request['sort'] === 'relevance' ? 'selected' : '' ?>>En ilgili</option>
                                <option value="newest" <?= $request['sort'] === 'newest' ? 'selected' : '' ?>>En yeni</option>
                                <option value="rating" <?= $request['sort'] === 'rating' ? 'selected' : '' ?>>En yüksek puan</option>
                                <option value="downloads" <?= $request['sort'] === 'downloads' ? 'selected' : '' ?>>En çok indirilen</option>
                            </select>
                        </div>
                    </div>
                    <p id="searchStatus" class="search-status small text-secondary" role="status" aria-live="polite" aria-atomic="true"><?= noteSearchEscape($dbUnavailable ? 'Notlar şu anda getirilemiyor.' : ($inputError ?: noteSearchStatus($result))) ?></p>
                    <div id="searchResults" class="search-results"><?= noteSearchResults($result) ?></div>
                    <nav class="mt-4" aria-label="Sayfalama">
                        <ul id="searchPagination" class="pagination justify-content-center mb-0"><?= noteSearchPagination($result) ?></ul>
                    </nav>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
