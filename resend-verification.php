<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brevo.php';
require_once __DIR__ . '/includes/registration_security.php';
require_once __DIR__ . '/includes/auth_redirect.php';

@session_start();

$error = '';
$success = '';
$returnTo = authReturnToFromRequest();
$turnstileSiteKey = registrationTurnstileSiteKey();
$clientIp = registrationClientIp();
$emailInput = $_POST['email'] ?? $_GET['email'] ?? '';
$email = is_string($emailInput) ? mb_strtolower(trim($emailInput), 'UTF-8') : '';
if (strlen($email) > 254) {
    $email = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestString = static function (string $key): string {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    };

    if (!registrationValidateCsrfToken($requestString('csrf_token'))) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.';
        registrationRecordAttempt($pdo, $clientIp, $email, 'csrf_failed');
    } elseif (trim($requestString('company_website')) !== '') {
        $error = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
        registrationRecordAttempt($pdo, $clientIp, $email, 'honeypot_failed');
    } elseif ((registrationValidateFormChallenge($requestString('form_nonce'))['ok'] ?? false) !== true) {
        $error = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
        registrationRecordAttempt($pdo, $clientIp, $email, 'timing_failed');
    }

    if ($error === '') {
        $rateLimit = registrationCheckRateLimit($pdo, $clientIp, $email);
        if (($rateLimit['allowed'] ?? false) !== true) {
            $error = 'Çok fazla istek gönderildi. Lütfen daha sonra tekrar deneyin.';
            registrationRecordAttempt($pdo, $clientIp, $email, 'rate_limited');
        }
    }

    if ($error === '' && (registrationValidateTurnstile($requestString('cf-turnstile-response'), $clientIp)['success'] ?? false) !== true) {
        $error = 'Güvenlik doğrulaması tamamlanamadı. Lütfen tekrar deneyin.';
        registrationRecordAttempt($pdo, $clientIp, $email, 'turnstile_failed');
    }

    if ($error === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Lütfen geçerli bir e-posta adresi girin.';
        registrationRecordAttempt($pdo, $clientIp, $email, 'validation_failed');
    }

    if ($error === '') {
        // Keep the response identical for missing, verified and limited accounts.
        $success = 'Bu adresle doğrulanmamış bir hesap varsa ve gönderim sınırı aşılmadıysa yeni doğrulama bağlantısı gönderildi. Spam klasörünü de kontrol edin. Yeniden istemeden önce 15 dakika bekleyin.';

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, verified FROM users WHERE email = :email LIMIT 1 FOR UPDATE');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            // Check after acquiring the account lock so concurrent resends share the cooldown.
            $emailLimit = registrationCheckVerificationEmailLimit($pdo, $email);

            if (($emailLimit['allowed'] ?? false) !== true) {
                registrationRecordAttempt($pdo, $clientIp, $email, 'verification_email_limited');
            } elseif ($user && (int)$user['verified'] === 0) {
                $plainToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $plainToken);
                $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
                $updateStmt = $pdo->prepare('UPDATE users SET email_verification_token = :token, email_verification_token_expires_at = :expires_at WHERE id = :id AND verified = 0');
                $updateStmt->execute(['token' => $tokenHash, 'expires_at' => $expiresAt, 'id' => $user['id']]);

                $verificationUrl = buildAppBaseUrl() . '/' . authPageUrl('verify-email.php', $returnTo, ['token' => $plainToken]);
                $fullName = trim((string)$user['first_name'] . ' ' . (string)$user['last_name']);
                sendVerificationEmail((string)$user['email'], $fullName, $verificationUrl);
                registrationRecordAttempt($pdo, $clientIp, $email, 'verification_email_sent');
            } else {
                registrationRecordAttempt($pdo, $clientIp, $email, 'verification_resend_requested');
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            registrationRecordAttempt($pdo, $clientIp, $email, 'verification_resend_error');
            error_log('verification resend error: ' . $exception->getMessage());
        }
    }
}

$csrfToken = registrationCsrfToken();
$formChallenge = registrationIssueFormChallenge();
$pageTitle = 'Not Bul | Doğrulama Bağlantısı';
$pageKey = 'resend-verification';
require __DIR__ . '/includes/header.php';
?>
<?php if ($turnstileSiteKey !== ''): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<main id="mainContent" class="page-shell" tabindex="-1">
    <section class="container section-block">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="panel-card mt-5">
                    <h1 class="h3 mb-3 text-center">Doğrulama Bağlantısı İste</h1>
                    <p class="text-secondary text-center mb-4">Kayıtlı e-posta adresini gir. Hesap bilgilerini yeniden doldurmana gerek yok.</p>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success" role="alert"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($turnstileSiteKey === ''): ?>
                        <div class="alert alert-warning" role="alert">Güvenlik doğrulaması şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.</div>
                    <?php endif; ?>
                    <form action="resend-verification.php" method="POST">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="form_nonce" value="<?= htmlspecialchars((string)$formChallenge['nonce'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="registration-trap" aria-hidden="true">
                            <label for="companyWebsite">Web sitesi</label>
                            <input type="text" id="companyWebsite" name="company_website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta Adresi</label>
                            <input type="email" class="form-control" id="email" name="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="254" placeholder="ornek@email.com" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <?php if ($turnstileSiteKey !== ''): ?>
                            <div class="cf-turnstile mb-3" data-sitekey="<?= htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8') ?>" data-action="register" data-theme="auto"></div>
                        <?php endif; ?>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" <?= $turnstileSiteKey === '' ? 'disabled' : '' ?>>Doğrulama Bağlantısını Gönder</button>
                        </div>
                    </form>
                    <div class="mt-3 text-center"><a href="<?= htmlspecialchars(authLoginUrl($returnTo), ENT_QUOTES, 'UTF-8') ?>">Giriş sayfasına dön</a></div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
