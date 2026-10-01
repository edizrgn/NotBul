<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_notifications.php';
require_once __DIR__ . '/includes/auth_redirect.php';

@session_start();
$returnTo = authReturnToFromRequest();
$resendEmail = '';

$status = 'danger';
$message = 'Doğrulama bağlantısı geçersiz veya daha önce kullanılmış. Hesabını zaten doğruladıysan giriş yapabilirsin; gerekirse yeni bağlantı iste.';

$token = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
    $tokenHash = hash('sha256', $token);

    if (hash_equals((string)($_SESSION['verified_email_token_hash'] ?? ''), $tokenHash)) {
        $status = 'success';
        $message = 'E-posta adresin zaten doğrulandı. Giriş yapabilirsin.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, first_name, last_name, email, email_verification_token_expires_at
             FROM users
             WHERE email_verification_token = :token
               AND verified = 0
             LIMIT 1"
        );
        $stmt->execute(['token' => $tokenHash]);
        $user = $stmt->fetch();

        if ($user) {
            $resendEmail = (string)$user['email'];
            $expiresAtRaw = $user['email_verification_token_expires_at'] ?? null;
            $isExpired = false;

            if ($expiresAtRaw !== null && $expiresAtRaw !== '') {
                $expiresAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expiresAtRaw);
                if ($expiresAt instanceof DateTimeImmutable && $expiresAt < new DateTimeImmutable('now')) {
                    $isExpired = true;
                }
            }

            if ($isExpired) {
                $status = 'warning';
                $message = 'Doğrulama bağlantısının süresi dolmuş. Hesap bilgilerini değiştirmeden yeni bağlantı isteyebilirsin.';
            } else {
                $updateStmt = $pdo->prepare(
                    "UPDATE users
                     SET verified = 1,
                         email_verification_token = NULL,
                         email_verification_token_expires_at = NULL,
                         verified_at = NOW()
                     WHERE id = :id
                       AND verified = 0
                       AND email_verification_token = :token"
                );
                $updateStmt->execute(['id' => $user['id'], 'token' => $tokenHash]);

                if ($updateStmt->rowCount() === 1) {
                    $status = 'success';
                    $_SESSION['verified_email_token_hash'] = $tokenHash;
                    $message = 'E-posta adresin başarıyla doğrulandı. Artık giriş yapabilirsin.';
                    sendUserSecurityNotification($user, 'E-posta adresiniz doğrulandı', 'Not Bul hesabınızın e-posta adresi başarıyla doğrulandı.', [
                        'İşlem' => 'E-posta doğrulama',
                        'Zaman' => date('d.m.Y H:i:s'),
                    ], [
                        'Giriş Yap' => userNotificationUrl('login.php'),
                    ]);
                } else {
                    $status = 'warning';
                    $message = 'Hesap zaten doğrulanmış olabilir. Giriş yapmayı deneyebilirsin.';
                }
            }
        }
    }
}

$pageTitle = 'Not Bul | E-posta Doğrulama';
$pageKey = 'verify-email';
require __DIR__ . '/includes/header.php';
?>
<main id="mainContent" class="page-shell" tabindex="-1">
    <section class="container section-block">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="panel-card mt-5 text-center">
                    <h1 class="h3 mb-4">E-posta Doğrulama</h1>
                    <div class="alert alert-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" role="alert">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <a href="<?= htmlspecialchars(authLoginUrl($returnTo), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary mt-2">Giriş Sayfasına Git</a>
                    <?php if ($status !== 'success'): ?>
                        <a href="<?= htmlspecialchars(authPageUrl('resend-verification.php', $returnTo, $resendEmail !== '' ? ['email' => $resendEmail] : []), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary mt-2">Yeni Doğrulama Bağlantısı İste</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
