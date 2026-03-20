<?php
declare(strict_types=1);
$pageTitle = 'Not Bul | Not Yükle';
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
                            <h1 class="section-title mb-1">Not Yükleme</h1>
                            <p class="mb-0 text-secondary">Not Bul üzerinde ders notunu güvenli şekilde yükle, hiyerarşiyi seç ve doğru öğrenci kitlesine ulaştır.</p>
                        </div>
                        <span class="badge bg-soft-info text-primary-emphasis">Frontend prototipi</span>
                    </div>

                    <form id="uploadForm" class="mt-4" data-hierarchy-group data-filter-source="public">
                        <div id="dropZone" class="drop-zone">
                            <input id="noteFile" name="note_file" type="file" accept=".pdf,.docx,.pptx,.png,.jpg,.jpeg,.webp" hidden>
                            <p class="drop-title mb-2">Dosyayı sürükle bırak veya seç</p>
                            <p class="mb-3 text-secondary">Desteklenen türler: PDF, DOCX, PPTX, PNG, JPG, WEBP | Maksimum 25 MB</p>
                            <button class="btn btn-primary" type="button" id="pickFileButton">Dosya Seç</button>
                            <div id="fileList" class="file-list mt-3"></div>
                        </div>

                        <div id="uploadNotice" class="alert mt-3 d-none" role="alert"></div>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="uploadTitle">Başlık</label>
                                <input class="form-control" id="uploadTitle" name="title" required maxlength="160" placeholder="Örn: Veri Yapıları Final Özet Notları">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="uploadDescription">Açıklama</label>
                                <textarea class="form-control" id="uploadDescription" name="description" rows="4" maxlength="1000" placeholder="Notun içeriğini, kapsamını ve hangi sınavlar için uygun olduğunu yaz."></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="uploadUniversity">Üniversite</label>
                                <select class="form-select" id="uploadUniversity" name="university_id" data-level="university" data-placeholder="Üniversite seç"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadDepartmentType">Program Türü</label>
                                <select class="form-select" id="uploadDepartmentType" name="department_type" data-level="department-type" data-placeholder="Program türü seç"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadDepartment">Bölüm</label>
                                <select class="form-select" id="uploadDepartment" name="department_id" data-level="department" data-placeholder="Bölüm seç (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadClass">Sınıf</label>
                                <select class="form-select" id="uploadClass" name="class_id" data-level="class" data-placeholder="Sınıf seç (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadCourse">Ders</label>
                                <input class="form-control" id="uploadCourse" name="course" data-level="course-input" list="uploadCourseList" placeholder="Dersi yaz veya önerilerden seç" required>
                                <datalist id="uploadCourseList"></datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadTopic">Konu</label>
                                <input class="form-control" id="uploadTopic" name="topic" data-level="topic-input" list="uploadTopicList" placeholder="Konu yaz veya önerilerden seç (opsiyonel)">
                                <datalist id="uploadTopicList"></datalist>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Etiketler</label>
                                <div class="tag-input-shell" data-tag-input>
                                    <div class="tag-chips" data-tag-chips></div>
                                    <input class="form-control" type="text" data-tag-field placeholder="Etiket yaz, Enter ile ekle (örn: final, çıkmış-soru)">
                                    <input type="hidden" name="tags" data-tag-hidden>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-lg btn-primary px-4" type="submit">Dosyayı Yükle</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <aside class="panel-card sticky-panel">
                    <h2 class="h5">Güvenlik Kontrol Listesi</h2>
                    <ul class="security-list mb-0">
                        <li>MIME-type ve dosya uzantısı backend tarafında yeniden doğrulanacak.</li>
                        <li>Maksimum dosya boyutu limitini aşan yüklemeler reddedilecek.</li>
                        <li>Gerçek dosya adı yerine benzersiz hash tabanlı adlandırma kullanılacak.</li>
                        <li>Dosyalar web root dışında saklanıp PHP ile stream edilecek.</li>
                        <li>Tüm metin verileri çıkışta `htmlspecialchars` ile filtrelenecek.</li>
                    </ul>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

