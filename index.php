<?php
declare(strict_types=1);
$pageTitle = 'NotShare | Anasayfa';
$pageKey = 'home';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="hero-section container">
        <div class="hero-content">
            <span class="eyebrow">Türkiye'nin Not Ağı</span>
            <h1>Ders notunu saniyeler içinde bul, paylaş ve güvenle kullan.</h1>
            <p>Üniversite, sınıf, ders ve konu bazında filtreleyerek ihtiyacın olan içeriğe hızlıca ulaş.</p>
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
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeClass">Sınıf</label>
                    <select class="form-select" id="homeClass" name="class_id" data-level="class" data-placeholder="Tüm sınıflar"></select>
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeCourse">Ders</label>
                    <select class="form-select" id="homeCourse" name="course_id" data-level="course" data-placeholder="Tüm dersler"></select>
                </div>
                <div class="col-6 col-lg">
                    <label class="form-label" for="homeTopic">Konu</label>
                    <select class="form-select" id="homeTopic" name="topic_id" data-level="topic" data-placeholder="Tüm konular"></select>
                </div>
            </div>
            <p class="mt-3 mb-0 small text-secondary">Filtrelenmiş sonuç sayısı: <strong id="homeResultCount">0</strong></p>
        </form>
    </section>

    <section class="container section-block">
        <div class="panel-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="section-title mb-0">Popüler Notlar</h2>
                <a class="btn btn-sm btn-outline-primary" href="search.php">Tümünü Gör</a>
            </div>
            <div id="popularNotesGrid" class="row g-3"></div>
        </div>
    </section>

    <section class="container section-block pb-5">
        <div class="panel-card">
            <h2 class="section-title mb-3">Yeni Yüklenenler</h2>
            <div id="latestNotesGrid" class="row g-3"></div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
