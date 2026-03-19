<?php
declare(strict_types=1);
$pageTitle = 'NotShare | Not Detayi';
$pageKey = 'detail';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="container section-block">
        <p class="text-secondary small mb-3">Anasayfa > Calculus 101 - Fonksiyonlar</p>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="preview-shell">
                    <div class="preview-toolbar d-flex justify-content-between align-items-center">
                        <strong>Dosya Onizleme</strong>
                        <span class="badge text-bg-info">PDF Onizleme Alani</span>
                    </div>
                    <div class="preview-canvas">
                        <p class="mb-2 fw-semibold">Dosya yuklenince bu alanda PDF/icerik onizleme gosterilecek.</p>
                        <p class="mb-0 text-secondary">Backend baglantisi tamamlandiginda guvenli dosya stream endpoint'i ile goruntulenecek.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <article class="panel-card h-100">
                    <h1 class="section-title mb-2">Veri Yapilari - Final Hazirlik Notlari</h1>
                    <p class="text-secondary">Agac yapilari, hash tablolar ve siklikla gelen final sorularinin aciklamali cozumu.</p>

                    <div class="note-meta-grid">
                        <div><span>Yukleyen</span><strong>Ahmet Yilmaz</strong></div>
                        <div><span>Universite</span><strong>Istanbul Teknik Universitesi</strong></div>
                        <div><span>Bolum</span><strong>Bilgisayar Muhendisligi</strong></div>
                        <div><span>Sinif</span><strong>2. Sinif</strong></div>
                        <div><span>Goruntulenme</span><strong>4.982</strong></div>
                        <div><span>Indirme</span><strong>1.364</strong></div>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <span class="badge rounded-pill text-bg-light">#final</span>
                        <span class="badge rounded-pill text-bg-light">#algoritma</span>
                        <span class="badge rounded-pill text-bg-light">#cikmis-soru</span>
                    </div>

                    <div class="mt-4 d-grid gap-2 d-md-flex">
                        <a class="btn btn-primary btn-lg" href="#" role="button">Download</a>
                        <a class="btn btn-outline-primary btn-lg" href="search.php">Benzer Notlar</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="container section-block pb-5">
        <div class="panel-card">
            <h2 class="section-title mb-3">Yorumlar</h2>
            <div id="commentsList" class="comment-list">
                <article class="comment-item">
                    <header>
                        <strong>Zeynep</strong> <span class="text-secondary">| 5/5</span>
                    </header>
                    <p class="mb-0">Ozellikle final oncesi cok faydali oldu. Tesekkurler.</p>
                </article>
                <article class="comment-item">
                    <header>
                        <strong>Can</strong> <span class="text-secondary">| 4/5</span>
                    </header>
                    <p class="mb-0">Ornek soru cozumu sayisi artarsa daha da iyi olur.</p>
                </article>
            </div>

            <form id="commentForm" class="mt-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="commentAuthor">Adin</label>
                        <input class="form-control" id="commentAuthor" maxlength="70" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="commentRating">Puan</label>
                        <select class="form-select" id="commentRating" required>
                            <option value="5">5 - Cok iyi</option>
                            <option value="4">4 - Iyi</option>
                            <option value="3">3 - Orta</option>
                            <option value="2">2 - Gelistirilmeli</option>
                            <option value="1">1 - Zayif</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="commentText">Yorum</label>
                        <textarea class="form-control" id="commentText" rows="3" maxlength="700" required></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Yorum Ekle</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
