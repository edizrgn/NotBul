<footer class="site-footer mt-5">
    <div class="container py-4 py-lg-5">
        <div class="row g-4">
            <div class="col-6 col-lg-3">
                <h3 class="footer-title footer-brand">
                    <i class="fa-solid fa-book-open-reader brand-icon brand-icon-footer" aria-hidden="true"></i>
                    <span>Not Bul</span>
                </h3>
                <p class="footer-text mb-0">Not Bul, öğrenciler için ders notu paylaşım platformudur.</p>
            </div>
            <div class="col-6 col-lg-3">
                <h3 class="footer-title">Anasayfa</h3>
                <ul class="footer-list">                    
                    <li><a href="#">Giriş Yap</a></li>
                    <li><a href="#">Kayıt Ol</a></li>

                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h3 class="footer-title">Linkler</h3>
                <ul class="footer-list">
                    <li><a href="search.php">Ders Notu Bul</a></li>
                    <li><a href="upload.php">Not Yükle</a></li>
                    
                    
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h3 class="footer-title">Sosyal</h3>
                <ul class="footer-list">
                    <li><a href="https://github.com/edizrgn/notbul">GitHub</a></li>

                </ul>
            </div>
        </div>
        <div class="footer-bottom mt-4 pt-3 d-flex flex-column flex-md-row justify-content-between gap-2">
            <p class="mb-0">Not Bul | notbul.site</p>
            <p class="mb-0">Pre-Alpha</p>
            <p class="mb-0">Tüm hakları saklıdır.</p>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="assets/js/app.js?v=<?= rawurlencode((string)(@filemtime(__DIR__ . '/../assets/js/app.js') ?: time())) ?>"></script>
</body>
</html>
