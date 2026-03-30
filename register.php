<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brevo.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($passwordConfirm)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Lütfen geçerli bir e-posta adresi girin.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Şifreler uyuşmuyor.';
    } elseif (strlen($password) < 8) {
        $error = 'Şifre en az 8 karakter olmalıdır.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, verified FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $existingUser = $stmt->fetch();

            if ($existingUser && (int) $existingUser['verified'] === 1) {
                $error = 'Bu e-posta adresi zaten kullanımda.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $plainToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $plainToken);
                $tokenExpiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
                $fullName = trim($firstName . ' ' . $lastName);
                $verificationUrl = buildAppBaseUrl() . '/verify-email.php?token=' . urlencode($plainToken);

                $pdo->beginTransaction();

                if ($existingUser) {
                    $updateStmt = $pdo->prepare(
                        "UPDATE users
                         SET first_name = :first_name,
                             last_name = :last_name,
                             password = :password,
                             verified = 0,
                             email_verification_token = :token,
                             email_verification_token_expires_at = :expires_at,
                             verified_at = NULL
                         WHERE id = :id"
                    );
                    $updateStmt->execute([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'password' => $hashedPassword,
                        'token' => $tokenHash,
                        'expires_at' => $tokenExpiresAt,
                        'id' => $existingUser['id'],
                    ]);
                } else {
                    $insertStmt = $pdo->prepare(
                        "INSERT INTO users
                            (first_name, last_name, email, password, verified, email_verification_token, email_verification_token_expires_at)
                         VALUES
                            (:first_name, :last_name, :email, :password, 0, :token, :expires_at)"
                    );
                    $insertStmt->execute([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'password' => $hashedPassword,
                        'token' => $tokenHash,
                        'expires_at' => $tokenExpiresAt,
                    ]);
                }

                sendVerificationEmail($email, $fullName, $verificationUrl);
                $pdo->commit();

                $success = 'Kayıt alındı. Hesabını aktifleştirmek için e-posta kutuna gönderdiğimiz doğrulama bağlantısını kullan.';
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($error === '') {
                $error = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
            }
        }
    }
}

$pageTitle = 'Not Bul | Kayıt Ol';
$pageKey = 'register';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="container section-block">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="panel-card mt-5">
                    <h1 class="h3 mb-4 text-center">Hesap Oluştur</h1>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <form action="register.php" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="firstName" class="form-label">Ad</label>
                                <input type="text" class="form-control" id="firstName" name="first_name" required placeholder="Adınız">
                            </div>
                            <div class="col-md-6">
                                <label for="lastName" class="form-label">Soyad</label>
                                <input type="text" class="form-control" id="lastName" name="last_name" required placeholder="Soyadınız">
                            </div>
                            <div class="col-12">
                                <label for="email" class="form-label">E-posta Adresi</label>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="ornek@universite.edu.tr">
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Şifre</label>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="En az 8 karakter">
                            </div>
                            <div class="col-md-6">
                                <label for="passwordConfirm" class="form-label">Şifre (Tekrar)</label>
                                <input type="password" class="form-control" id="passwordConfirm" name="password_confirm" required placeholder="Şifrenizi doğrulayın">
                            </div>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">Kayıt Ol</button>
                        </div>
                        <div class="mt-3 text-center">
                            <span class="text-secondary">Zaten hesabınız var mı?</span> <a href="login.php" class="text-decoration-none">Giriş Yap</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
