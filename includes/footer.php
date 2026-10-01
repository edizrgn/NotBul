<footer class="site-footer mt-5">
    <div class="container py-4 py-lg-5">
        <div class="row g-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <h3 class="footer-title footer-brand">
                    <i class="fa-solid fa-book-open-reader brand-icon brand-icon-footer" aria-hidden="true"></i>
                    <span>Not Bul</span>
                </h3>
                <p class="footer-text mb-0">Not Bul, öğrenciler için ders notu paylaşım platformudur.</p>
                <details class="footer-about mt-3" id="aboutNotBul">
                    <summary>Hakkımızda</summary>
                    <p class="footer-text mt-2">Üniversite, bölüm ve ders bilgilerine göre not arayabilir, desteklenen dosyaları önizleyebilir ve indirebilirsiniz. Hesabınızla not yükleyebilir, yorum yapabilir ve kendi paylaşımlarınızı profilinizden yönetebilirsiniz.</p>
                </details>
            </div>
            <div class="col-6 col-lg-3">
                <h3 class="footer-title">Hesap</h3>
                <ul class="footer-list">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php">Profilim</a></li>
                        <li><a href="profile_edit.php">Profil Ayarları</a></li>
                    <?php else: ?>
                        <li><a data-auth-page="login.php" href="<?= htmlspecialchars($headerLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">Giriş Yap</a></li>
                        <li><a data-auth-page="register.php" href="<?= htmlspecialchars($headerRegisterUrl, ENT_QUOTES, 'UTF-8'); ?>">Kayıt Ol</a></li>
                        <li><a href="forgot-password.php">Şifremi Unuttum</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h3 class="footer-title">Notlar</h3>
                <ul class="footer-list">
                    <li><a href="index.php">Anasayfa</a></li>
                    <li><a href="search.php">Ders Notu Bul</a></li>
                    <li><a href="upload.php">Not Yükle</a></li>
                    
                    
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h3 class="footer-title">Yardım</h3>
                <p class="footer-text mb-2">Sonuç bulamazsanız arama filtrelerini azaltın. Önizleme açılmazsa dosyayı indirip cihazınızda açabilirsiniz.</p>
                <ul class="footer-list">
                    <li><a href="https://github.com/edizrgn/notbul">GitHub</a></li>
                    <li><a href="https://github.com/edizrgn/notbul/issues/new">Sorun bildir</a></li>

                </ul>
            </div>
        </div>
        <div class="footer-bottom mt-4 pt-3">
            <p class="footer-bottom-brand mb-0">Not Bul | notbul.site</p>
            <div class="footer-legal-notes">
                <p class="mb-0">Bu proje eğitim amaçlı geliştirilmekte olup aktif olarak test edilmektedir.</p>
                <p class="mb-0">Platform kodları MIT lisansı ile lisanslanmıştır.</p>
            </div>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="assets/js/app.js?v=<?= rawurlencode((string)(@filemtime(__DIR__ . '/../assets/js/app.js') ?: time())) ?>"></script>
</body>
</html>
