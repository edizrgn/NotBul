<?php
declare(strict_types=1);
$pageTitle = 'NotShare | Anasayfa';
$pageKey = 'home';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="hero-section container">
        <div class="hero-content">
            <span class="eyebrow">Turkiye'nin Not Agi</span>
            <h1>Ders notunu saniyeler icinde bul, paylas ve guvenle kullan.</h1>
            <p>Universite, sinif, ders ve konu bazinda filtreleyerek ihtiyacin olan icerige hizli ulas.</p>
        </div>

        <form id="homeFilterForm" class="glass-panel" data-hierarchy-group>
            <div class="row g-3 align-items-end">
                <div class="col-12">
                    <label class="form-label" for="homeQuery">Not Ara</label>
                    <input class="form-control form-control-lg" id="homeQuery" name="query" type="search" placeholder="Orn: Diferansiyel denklemler final notu">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="homeUniversity">Universite</label>
                    <select class="form-select" id="homeUniversity" name="university_id" data-level="university" data-placeholder="Tum universite"></select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="homeClass">Sinif</label>
                    <select class="form-select" id="homeClass" name="class_id" data-level="class" data-placeholder="Tum siniflar"></select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label" for="homeCourse">Ders</label>
                    <select class="form-select" id="homeCourse" name="course_id" data-level="course" data-placeholder="Tum dersler"></select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label" for="homeTopic">Konu</label>
                    <select class="form-select" id="homeTopic" name="topic_id" data-level="topic" data-placeholder="Tum konular"></select>
                </div>
                <div class="col-12 col-lg-2">
                    <label class="form-label" for="homeAudience">Kime Yonelik</label>
                    <select class="form-select" id="homeAudience" name="audience">
                        <option value="">Tum erisim tipleri</option>
                        <option value="all">Herkese Acik</option>
                        <option value="university">Universiteye Ozel</option>
                        <option value="department">Bolume Ozel</option>
                    </select>
                </div>
            </div>
            <p class="mt-3 mb-0 small text-secondary">Filtrelenmis sonuc sayisi: <strong id="homeResultCount">0</strong></p>
        </form>
    </section>

    <section class="container section-block">
        <div class="panel-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="section-title mb-0">Populer Notlar</h2>
                <a class="btn btn-sm btn-outline-primary" href="search.php">Tumunu Gor</a>
            </div>
            <div id="popularNotesGrid" class="row g-3"></div>
        </div>
    </section>

    <section class="container section-block pb-5">
        <div class="panel-card">
            <h2 class="section-title mb-3">Yeni Yuklenenler</h2>
            <div id="latestNotesGrid" class="row g-3"></div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
