<?php
declare(strict_types=1);
$pageTitle = 'NotShare | Arama Sonuçları';
$pageKey = 'search';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="container section-block">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <h1 class="section-title mb-0">Arama Sonuçları</h1>
            <div class="search-box-inline">
                <input id="searchQuery" class="form-control" type="search" placeholder="Başlık, açıklama veya etiket ara">
            </div>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <span class="filter-chip active">Matematik</span>
            <span class="text-secondary small">veya</span>
            <span class="filter-chip">1. Sınıf</span>
        </div>

        <div class="row g-4 align-items-start">
            <aside class="col-lg-4 col-xl-3">
                <form id="searchFilterForm" class="panel-card" data-hierarchy-group data-filter-source="public">
                    <h2 class="h5 mb-3">Detaylı Filtreler</h2>

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
                        <select class="form-select" id="searchCourse" name="course_id" data-level="course" data-placeholder="Tüm dersler"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="searchTopic">Konu</label>
                        <select class="form-select" id="searchTopic" name="topic_id" data-level="topic" data-placeholder="Tüm konular"></select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="searchFileType">Dosya Türü</label>
                        <select class="form-select" id="searchFileType" name="file_type">
                            <option value="">Tüm dosya türleri</option>
                            <option value="pdf">Pdf</option>
                            <option value="docx">DOCX</option>
                            <option value="pptx">PPTX</option>
                            <option value="image">Görsel</option>
                        </select>
                    </div>
                </form>
            </aside>

            <div class="col-lg-8 col-xl-9">
                <div class="panel-card">
                    <p class="mb-3">Toplam sonuç: <strong id="searchResultCount">0</strong></p>
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
