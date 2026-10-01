<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_redirect.php';
@session_start(); // ensure session is started if not done in header yet

$error = '';
$success = '';
$email = '';
$showResendVerification = false;
$returnTo = authReturnToFromRequest();

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = 'Şifren başarıyla güncellendi. Yeni şifrenle giriş yapabilirsin.';
} elseif (isset($_GET['account_deleted']) && $_GET['account_deleted'] === '1') {
    $success = 'Hesabınız kalıcı olarak silindi.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, password, verified, role FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ((int) $user['verified'] !== 1) {
                $error = 'Hesabın henüz doğrulanmamış. Lütfen e-posta adresine gönderilen doğrulama bağlantısını kullan.';
                $showResendVerification = true;
            } else {
                // Şifre doğru, oturum aç
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'] ?? 'user';

                unset($_SESSION['auth_return_to'], $_SESSION['auth_return_to_at']);
                header('Location: ' . ($returnTo !== '' ? $returnTo : 'index.php'));
                exit;
            }
        } else {
            $error = 'E-posta veya şifre hatalı.';
        }
    }
}

$pageTitle = 'Not Bul | Giriş Yap';
$pageKey = 'login';
require __DIR__ . '/includes/header.php';
?>
<main id="mainContent" class="page-shell" tabindex="-1">
    <section class="container section-block">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="panel-card mt-5">
                    <h1 class="h3 mb-4 text-center">Giriş Yap</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($showResendVerification): ?>
                        <p><a href="<?= htmlspecialchars(authPageUrl('resend-verification.php', $returnTo, ['email' => $email]), ENT_QUOTES, 'UTF-8') ?>">Doğrulama bağlantısını yeniden gönder</a></p>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta Adresi</label>
                            <input type="email" class="form-control" id="email" name="email" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required placeholder="ornek@email.com" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Şifre</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required placeholder="********">
                                <button type="button" class="btn btn-outline-secondary d-none" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Şifreyi göster" hidden>Göster</button>
                            </div>
                            <div class="text-end mt-2">
                                <a href="<?= htmlspecialchars(authPageUrl('forgot-password.php', $returnTo, $email !== '' ? ['email' => $email] : []), ENT_QUOTES, 'UTF-8') ?>" class="small text-decoration-none">Şifremi Unuttum</a>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Giriş Yap</button>
                        </div>
                        <div class="mt-3 text-center">
                            <span class="text-secondary">Hesabınız yok mu?</span> <a href="<?= htmlspecialchars(authPageUrl('register.php', $returnTo), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none">Kayıt Ol</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="assets/js/auth.js?v=<?= rawurlencode((string)filemtime(__DIR__ . '/assets/js/auth.js')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
