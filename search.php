<?php
declare(strict_types=1);
$pageTitle = 'NotShare | Arama Sonuclari';
$pageKey = 'search';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="container section-block">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <h1 class="section-title mb-0">Arama Sonuclari</h1>
            <div class="search-box-inline">
                <input id="searchQuery" class="form-control" type="search" placeholder="Baslik, aciklama veya etiket ara">
            </div>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <span class="filter-chip active">Matematik</span>
            <span class="text-secondary small">or</span>
            <span class="filter-chip">1. Sinif</span>
        </div>

        <div class="row g-4 align-items-start">
            <aside class="col-lg-4 col-xl-3">
                <form id="searchFilterForm" class="panel-card" data-hierarchy-group>
                    <h2 class="h5 mb-3">Detayli Filtreler</h2>

                    <div class="mb-3">
                        <label class="form-label" for="searchUniversity">Universite</label>
                        <select class="form-select" id="searchUniversity" name="university_id" data-level="university" data-placeholder="Tum universite"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchFaculty">Fakulte</label>
                        <select class="form-select" id="searchFaculty" name="faculty_id" data-level="faculty" data-placeholder="Tum fakulteler"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchDepartment">Bolum</label>
                        <select class="form-select" id="searchDepartment" name="department_id" data-level="department" data-placeholder="Tum bolumler"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchClass">Sinif</label>
                        <select class="form-select" id="searchClass" name="class_id" data-level="class" data-placeholder="Tum siniflar"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchCourse">Ders</label>
                        <select class="form-select" id="searchCourse" name="course_id" data-level="course" data-placeholder="Tum dersler"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchTopic">Konu</label>
                        <select class="form-select" id="searchTopic" name="topic_id" data-level="topic" data-placeholder="Tum konular"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchAudience">Kime Yonelik</label>
                        <select class="form-select" id="searchAudience" name="audience">
                            <option value="">Tum erisim tipleri</option>
                            <option value="all">Herkese Acik</option>
                            <option value="university">Universiteye Ozel</option>
                            <option value="department">Bolume Ozel</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="searchFileType">Dosya Turu</label>
                        <select class="form-select" id="searchFileType" name="file_type">
                            <option value="">Tum dosya turleri</option>
                            <option value="pdf">PDF</option>
                            <option value="docx">DOCX</option>
                            <option value="pptx">PPTX</option>
                            <option value="image">Gorsel</option>
                        </select>
                    </div>
                </form>
            </aside>

            <div class="col-lg-8 col-xl-9">
                <div class="panel-card">
                    <p class="mb-3">Toplam sonuc: <strong id="searchResultCount">0</strong></p>
                    <div id="searchResults" class="search-results"></div>
                    <nav class="mt-4" aria-label="Sayfalama">
                        <ul id="searchPagination" class="pagination justify-content-center mb-0"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
