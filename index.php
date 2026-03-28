<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
@session_start();

// Son yüklenen 6 notu çek
try {
    $stmt = $pdo->query("
        SELECT n.*, u.first_name, u.last_name 
        FROM notes n
        JOIN users u ON n.user_id = u.id
        ORDER BY n.created_at DESC
        LIMIT 6
    ");
    $latestNotes = $stmt->fetchAll();
} catch (Exception $e) {
    $latestNotes = [];
}

$pageTitle = 'Not Bul | Anasayfa';
$pageKey = 'home';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="hero-section container">
        <div class="hero-content">
            <span class="eyebrow">Not Bul • notbul.site</span>
            <h1>Ders Notu Bul, paylaş ve öğren.</h1>
            <p>Not Bul, öğrenciler için Ders Notu Paylaşım Platformu. Üniversite, bölüm, ders ve konu filtreleriyle ihtiyacın olan nota hızlıca ulaş.</p>
        </div>

        <form id="homeFilterForm" class="glass-panel" data-hierarchy-group data-filter-source="public">
            <div class="row g-3 align-items-end">
                <div class="col-12">
                    <label class="form-label" for="homeQuery">Not Ara</label>
                    <input class="form-control form-control-lg" id="homeQuery" name="query" type="search" placeholder="Örn: Diferansiyel denklemler final notu">
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeUniversity">Üniversite</label>
                    <select class="form-select" id="homeUniversity" name="university_id" data-level="university" data-placeholder="Tüm üniversiteler"></select>
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeDepartmentType">Program Türü</label>
                    <select class="form-select" id="homeDepartmentType" name="department_type" data-level="department-type" data-placeholder="Program türü seç"></select>
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeDepartment">Bölüm</label>
                    <select class="form-select" id="homeDepartment" name="department_id" data-level="department" data-placeholder="Bölüm seç"></select>
                </div>
                <div class="col-12 col-lg">
                    <label class="form-label" for="homeCourse">Ders</label>
                    <input class="form-control" id="homeCourse" name="course" list="homeCourseList" placeholder="Ders adı">
                    <datalist id="homeCourseList"></datalist>
                </div>
            </div>
            <p class="mt-3 mb-0 small text-secondary">Filtrelenmiş sonuç sayısı: <strong id="homeResultCount"><?= count($latestNotes) ?></strong></p>
        </form>
    </section>

    <!-- Popüler Notlar (Önceki statik demo veriler JS tarafından doldurulmaya devam edebilir) -->
    <section class="container section-block">
        <div class="panel-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="section-title mb-0">Popüler Notlar</h2>
                <a class="btn btn-sm btn-outline-primary" href="search.php">Tümünü Gör</a>
            </div>
            <div id="popularNotesGrid" class="row g-3">
                <!-- app.js içindeki statik veriler buraya gelecek -->
            </div>
        </div>
    </section>

    <section class="container section-block pb-5">
        <div class="panel-card">
            <h2 class="section-title mb-3">Yeni Yüklenenler</h2>
            <div id="latestNotesGrid" class="row g-3">
                <?php if (empty($latestNotes)): ?>
                    <div class="col-12">
                        <div class="empty-state">Henüz hiç not yüklenmemiş. İster misin? <a href="upload.php" class="text-decoration-none">Hemen Yükle</a></div>
                    </div>
                <?php else: ?>
                    <?php foreach ($latestNotes as $note): ?>
                        <article class="col-sm-6 col-xl-4">
                            <div class="note-card card shadow-sm border-0">
                                <div class="card-body">
                                    <h3 class="h6 mb-2 text-truncate"><?= htmlspecialchars($note['title']) ?></h3>
                                    <p class="text-secondary mb-3 small" style="height: 3em; overflow: hidden;">
                                        <?= htmlspecialchars(mb_substr($note['description'] ?? '', 0, 80)) ?>...
                                    </p>
                                    <div class="note-tags mb-3">
                                        <?php 
                                        $tags = explode(',', $note['tags']);
                                        foreach (array_slice($tags, 0, 2) as $tag): 
                                            if ($tag = trim($tag)):
                                        ?>
                                            <span class="badge bg-light text-secondary fw-normal">#<?= htmlspecialchars($tag) ?></span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="small">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($note['first_name'] . ' ' . $note['last_name']) ?></div>
                                            <div class="text-secondary"><?= htmlspecialchars($note['course']) ?></div>
                                        </div>
                                        <a href="note-detail.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-primary">Detay</a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
