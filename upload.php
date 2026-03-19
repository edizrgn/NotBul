<?php
declare(strict_types=1);
$pageTitle = 'NotShare | Not Yukle';
$pageKey = 'upload';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="container section-block">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="panel-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="section-title mb-1">Not Yukleme</h1>
                            <p class="mb-0 text-secondary">Dosyani guvenli bir sekilde yukle, hiyerarsiyi sec ve dogru ogrenci kitlesine ulastir.</p>
                        </div>
                        <span class="badge bg-soft-info text-primary-emphasis">Frontend prototipi</span>
                    </div>

                    <form id="uploadForm" class="mt-4" data-hierarchy-group>
                        <div id="dropZone" class="drop-zone">
                            <input id="noteFile" name="note_file" type="file" accept=".pdf,.docx,.pptx,.png,.jpg,.jpeg,.webp" hidden>
                            <p class="drop-title mb-2">Dosyayi surukle birak veya sec</p>
                            <p class="mb-3 text-secondary">Desteklenen turler: PDF, DOCX, PPTX, PNG, JPG, WEBP | Maksimum 25 MB</p>
                            <button class="btn btn-primary" type="button" id="pickFileButton">Dosya Sec</button>
                            <div id="fileList" class="file-list mt-3"></div>
                        </div>

                        <div id="uploadNotice" class="alert mt-3 d-none" role="alert"></div>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="uploadTitle">Baslik</label>
                                <input class="form-control" id="uploadTitle" name="title" required maxlength="160" placeholder="Orn: Veri Yapilari Final Ozet Notlari">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="uploadDescription">Aciklama</label>
                                <textarea class="form-control" id="uploadDescription" name="description" rows="4" maxlength="1000" placeholder="Notun icerigini, kapsamini ve hangi sinavlar icin uygun oldugunu yaz."></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="uploadUniversity">Universite</label>
                                <select class="form-select" id="uploadUniversity" name="university_id" data-level="university" data-placeholder="Universite sec"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadFaculty">Fakulte</label>
                                <select class="form-select" id="uploadFaculty" name="faculty_id" data-level="faculty" data-placeholder="Fakulte sec (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadDepartment">Bolum</label>
                                <select class="form-select" id="uploadDepartment" name="department_id" data-level="department" data-placeholder="Bolum sec (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadClass">Sinif</label>
                                <select class="form-select" id="uploadClass" name="class_id" data-level="class" data-placeholder="Sinif sec (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadCourse">Ders</label>
                                <select class="form-select" id="uploadCourse" name="course_id" data-level="course" data-placeholder="Ders sec (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadTopic">Konu</label>
                                <select class="form-select" id="uploadTopic" name="topic_id" data-level="topic" data-placeholder="Konu sec (opsiyonel)"></select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Etiketler</label>
                                <div class="tag-input-shell" data-tag-input>
                                    <div class="tag-chips" data-tag-chips></div>
                                    <input class="form-control" type="text" data-tag-field placeholder="Etiket yaz, Enter ile ekle (orn: final, cikmis-soru)">
                                    <input type="hidden" name="tags" data-tag-hidden>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label d-block">Erisim Izni</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="access_level" id="accessAll" value="all" checked>
                                        <label class="form-check-label" for="accessAll">Herkese Acik</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="access_level" id="accessUniversity" value="university">
                                        <label class="form-check-label" for="accessUniversity">Universiteye Ozel</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="access_level" id="accessDepartment" value="department">
                                        <label class="form-check-label" for="accessDepartment">Bolume Ozel</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-lg btn-primary px-4" type="submit">Dosyayi Yukle</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <aside class="panel-card sticky-panel">
                    <h2 class="h5">Guvenlik Kontrol Listesi</h2>
                    <ul class="security-list mb-0">
                        <li>MIME-type ve dosya uzantisi backend tarafinda yeniden dogrulanacak.</li>
                        <li>Maksimum dosya boyutu limitini asan yuklemeler reddedilecek.</li>
                        <li>Gercek dosya adi yerine benzersiz hash tabanli adlandirma kullanilacak.</li>
                        <li>Dosyalar web root disinda saklanip PHP ile stream edilecek.</li>
                        <li>Tum metin verileri cikista `htmlspecialchars` ile filtrelenecek.</li>
                    </ul>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
