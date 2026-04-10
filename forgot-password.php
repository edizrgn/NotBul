<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brevo.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim($_POST['email'] ?? ''));

    if ($email === '') {
        $error = 'Lütfen e-posta adresini girin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Lütfen geçerli bir e-posta adresi girin.';
    } else {
        $success = 'Eğer bu e-posta adresiyle kayıtlı ve doğrulanmış bir hesap varsa, şifre sıfırlama bağlantısı gönderildi.';

        try {
            $stmt = $pdo->prepare(
                "SELECT id, first_name, last_name, email, verified
                 FROM users
                 WHERE email = :email
                 LIMIT 1"
            );
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && (int) $user['verified'] === 1) {
                $plainToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $plainToken);
                $tokenExpiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

                $updateStmt = $pdo->prepare(
                    "UPDATE users
                     SET password_reset_token = :token,
                         password_reset_token_expires_at = :expires_at
                     WHERE id = :id"
                );
                $updateStmt->execute([
                    'token' => $tokenHash,
                    'expires_at' => $tokenExpiresAt,
                    'id' => $user['id'],
                ]);

                $fullName = trim(((string) $user['first_name']) . ' ' . ((string) $user['last_name']));
                if ($fullName === '') {
                    $fullName = 'Not Bul Kullanıcısı';
                }

                $resetUrl = buildAppBaseUrl() . '/reset-password.php?token=' . urlencode($plainToken);
                sendPasswordResetEmail((string) $user['email'], $fullName, $resetUrl);
            }
        } catch (Throwable $exception) {
            // Keep the same response message to avoid account enumeration.
        }
    }
}

$pageTitle = 'Not Bul | Şifremi Unuttum';
$pageKey = 'forgot-password';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="container section-block">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="panel-card mt-5">
                    <h1 class="h3 mb-3 text-center">Şifremi Unuttum</h1>
                    <p class="text-secondary text-center mb-4">Kayıtlı e-posta adresini gir, sana şifre sıfırlama bağlantısı gönderelim.</p>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success" role="alert"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <form action="forgot-password.php" method="POST" novalidate>
                        <div class="mb-4">
                            <label for="email" class="form-label">E-posta Adresi</label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                required
                                placeholder="ornek@universite.edu.tr"
                                value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Sıfırlama Bağlantısı Gönder</button>
                        </div>
                        <div class="mt-3 text-center">
                            <a href="login.php" class="text-decoration-none">Giriş sayfasına dön</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
