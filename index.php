<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/note_search.php';
$latestNotes = [];
$popularNotes = [];
$dbUnavailable = false;
$inputError = '';
$request = noteSearchRequest([]);
$filterOptions = [];
$totalNotes = 0;
try {
    $request = noteSearchRequest($_GET);
    $request['page'] = 1;
    $request['similar_to'] = 0;
} catch (InvalidArgumentException $e) {
    $inputError = $e->getMessage();
}
$searchActive = $request['q'] !== '' || array_filter($request['filters'], static fn($value): bool => $value !== '') !== [];
$homeResult = ['request' => $request, 'rows' => [], 'total' => 0, 'pages' => 1, 'page_size' => 6];
try {
    require_once __DIR__ . '/includes/db.php';
    $filterOptions = noteSearchOptions($pdo, $request['filters']);
    if ($inputError === '') {
        if ($searchActive) {
            $homeResult = noteSearchPage($pdo, $request, 6);
            $request = $homeResult['request'];
            $popularNotes = $homeResult['rows'];
            $totalNotes = $homeResult['total'];
        } else {
            $featured = noteSearchFeatured($pdo);
            $popularNotes = $featured['popular'];
            $latestNotes = $featured['latest'];
            $totalNotes = (int)$pdo->query('SELECT COUNT(*) FROM notes n WHERE ' . noteSearchVisibility())->fetchColumn();
            $homeResult['rows'] = $popularNotes;
            $homeResult['total'] = $totalNotes;
        }
    }
} catch (Throwable $e) {
    $dbUnavailable = true;
    error_log('home limited note search: ' . $e->getMessage());
}
$homeResult['request'] = $request;

$pageTitle = 'Not Bul | Anasayfa';
$pageKey = 'home';
require __DIR__ . '/includes/header.php';

$errorMsg = isset($_GET['error']) && $_GET['error'] === 'not_found' ? 'Aradığınız not bulunamadı veya yayından kaldırılmış. Aramayı kullanarak başka notlara ulaşabilirsiniz.' : '';
$successMsg = isset($_GET['note_deleted']) && $_GET['note_deleted'] === '1'
    ? 'Not başarıyla arşive alındı.'
    : '';
?>
<main id="mainContent" class="page-shell" tabindex="-1">
    <section class="hero-section container">
        <?php if ($successMsg): ?>
            <div class="alert alert-success mt-3"><?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-warning mt-3"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>
        <?php if ($dbUnavailable): ?>
            <div class="alert alert-warning mt-3">Veritabanı bağlantısı kurulamadığı için not listeleri şu anda gösterilemiyor.</div>
        <?php endif; ?>
        <?php if ($inputError !== ''): ?>
            <div class="alert alert-warning" role="alert"><?= noteSearchEscape($inputError) ?></div>
        <?php endif; ?>
        <div class="hero-content">
            <span class="eyebrow">Not Bul • notbul.site</span>
            <h1>Ders Notu Bul, paylaş ve öğren.</h1>
            <p>Not Bul, öğrenciler için Ders Notu Paylaşım Platformu. Üniversite, bölüm, ders ve konu filtreleriyle ihtiyacın olan nota hızlıca ulaş.</p>
        </div>

        <form id="homeFilterForm" method="GET" action="search.php" class="glass-panel" data-hierarchy-group data-server-filters>
            <input type="hidden" name="topic" value="<?= noteSearchEscape($request['filters']['topic']) ?>">
            <input type="hidden" name="file_type" value="<?= noteSearchEscape($request['filters']['file_type']) ?>">
            <div class="row g-3 align-items-end">
                <div class="col-12">
                    <label class="form-label" for="homeQuery">Not Ara</label>
                    <input class="form-control form-control-lg" id="homeQuery" name="q" value="<?= noteSearchEscape($request['q']) ?>" maxlength="200" type="search" placeholder="Örn: Diferansiyel denklemler final notu">
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeUniversity">Üniversite</label>
                    <select class="form-select" id="homeUniversity" name="university_id" data-level="university" data-placeholder="Tüm üniversiteler"><?= noteSearchOptionsHtml($filterOptions['university_id'] ?? [], $request['filters']['university_id'], 'Tüm üniversiteler') ?></select>
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeDepartmentType">Program Türü</label>
                    <select class="form-select" id="homeDepartmentType" name="department_type" data-level="department-type" data-placeholder="Program türü seç"><?= noteSearchOptionsHtml($filterOptions['department_type'] ?? [], $request['filters']['department_type'], 'Tüm program türleri') ?></select>
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeDepartment">Bölüm</label>
                    <select class="form-select" id="homeDepartment" name="department_id" data-level="department" data-placeholder="Bölüm seç"><?= noteSearchOptionsHtml($filterOptions['department_id'] ?? [], $request['filters']['department_id'], 'Tüm bölümler') ?></select>
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeClass">Sınıf</label>
                    <select class="form-select" id="homeClass" name="class_id"><?= noteSearchOptionsHtml($filterOptions['class_id'] ?? [], $request['filters']['class_id'], 'Tüm sınıflar') ?></select>
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeCourse">Ders</label>
                    <select class="form-select" id="homeCourse" name="course" data-level="course" data-placeholder="Ders seç"><?= noteSearchOptionsHtml($filterOptions['course'] ?? [], $request['filters']['course'], 'Tüm dersler') ?></select>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
                <p class="mb-0 small text-secondary" role="status" aria-live="polite" aria-atomic="true">Bulunan not: <strong id="homeResultCount"><?= number_format($totalNotes, 0, ',', '.') ?></strong><span id="homeResultHint"><?= $searchActive && $totalNotes > 6 ? ' · İlk 6 not gösteriliyor. Tüm sonuçları açabilirsiniz.' : '' ?></span></p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary" data-reset-search>Filtreleri temizle</a>
                    <button type="submit" class="btn btn-sm btn-primary">Tüm sonuçları göster</button>
                </div>
            </div>
        </form>
    </section>

    <section class="container section-block">
        <div class="panel-card">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                <h2 class="section-title mb-0" id="homePrimaryPanelTitle"><?= $searchActive ? 'Arama Sonuçları' : 'Popüler Notlar' ?></h2>
                <a href="<?= noteSearchEscape(noteSearchUrl(array_replace($request, ['sort' => $searchActive ? 'relevance' : 'downloads']))) ?>" class="btn btn-sm btn-outline-primary" data-home-results-link data-sort="downloads">Tümünü görüntüle</a>
            </div>
            <p id="homeSearchStatus" class="small text-secondary" role="status" aria-live="polite"><?= $searchActive ? noteSearchEscape(noteSearchStatus($homeResult)) : '' ?></p>
            <div id="popularNotesGrid" class="row g-3">
                <?= noteSearchResults($homeResult, true, 'index.php') ?>
            </div>
        </div>
    </section>

    <section class="container section-block pb-5" id="homeLatestSection" <?= $searchActive ? 'hidden' : '' ?>>
        <div class="panel-card">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                <h2 class="section-title mb-0">Son Yüklenenler</h2>
                <a href="<?= noteSearchEscape(noteSearchUrl(array_replace($request, ['sort' => 'newest']))) ?>" class="btn btn-sm btn-outline-primary" data-home-results-link data-sort="newest">Tümünü görüntüle</a>
            </div>
            <div id="latestNotesGrid" class="row g-3">
                <?= noteSearchResults(array_replace($homeResult, ['rows' => $latestNotes]), true, 'index.php') ?>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
