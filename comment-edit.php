<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_redirect.php';
@session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . authLoginUrl(authSafeReturnTo($_SERVER['REQUEST_URI'] ?? '') ?: 'profile.php#comments'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$commentId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['id'] ?? 0)
    : (int)($_GET['id'] ?? 0);
$returnTo = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string)($_POST['return_to'] ?? 'note')
    : (string)($_GET['return'] ?? 'note');
$returnTo = in_array($returnTo, ['note', 'profile'], true) ? $returnTo : 'note';

if ($commentId <= 0) {
    header('Location: profile.php');
    exit;
}

$commentEditToken = (string)($_SESSION['csrf_token_comment_edit'] ?? '');
if ($commentEditToken === '') {
    try {
        $commentEditToken = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $commentEditToken = hash('sha256', session_id() . (string)microtime(true));
    }
    $_SESSION['csrf_token_comment_edit'] = $commentEditToken;
}

function redirectAfterCommentAction(int $noteId, string $returnTo, string $query): void
{
    if ($returnTo === 'profile') {
        header('Location: profile.php?' . $query . '=1#comments');
        exit;
    }

    header('Location: note-detail.php?id=' . $noteId . '&' . $query . '=1#comments');
    exit;
}

function formatCommentDate(?string $dateValue): string
{
    if ($dateValue === null || trim($dateValue) === '') {
        return '-';
    }

    $timestamp = strtotime($dateValue);
    if ($timestamp === false) {
        return '-';
    }

    return date('d.m.Y H:i', $timestamp);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'update_comment');
    $requestToken = (string)($_POST['csrf_token'] ?? '');
    $noteIdForRedirect = 0;

    if ($requestToken === '' || !hash_equals($commentEditToken, $requestToken)) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.';
    } else {
        $ownerStmt = $pdo->prepare("SELECT note_id FROM note_comments WHERE id = :id AND user_id = :user_id LIMIT 1");
        $ownerStmt->execute([
            'id' => $commentId,
            'user_id' => $userId,
        ]);
        $ownedComment = $ownerStmt->fetch();

        if (!$ownedComment) {
            $error = 'Yorum bulunamadı veya size ait değil.';
        } else {
            $noteIdForRedirect = (int)$ownedComment['note_id'];
        }
    }

    if ($error === '' && $action === 'update_comment') {
        $rating = (int)($_POST['rating'] ?? 0);
        $commentText = trim((string)($_POST['comment'] ?? ''));

        if ($rating < 1 || $rating > 5) {
            $error = 'Puan 1 ile 5 arasında olmalıdır.';
        } elseif ($commentText === '') {
            $error = 'Yorum metni boş olamaz.';
        } elseif (mb_strlen($commentText) > 5000) {
            $error = 'Yorum metni 5000 karakteri geçemez.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE note_comments
                    SET rating = :rating,
                        comment = :comment
                    WHERE id = :id
                      AND user_id = :user_id
                    LIMIT 1
                ");
                $stmt->execute([
                    'rating' => $rating,
                    'comment' => $commentText,
                    'id' => $commentId,
                    'user_id' => $userId,
                ]);

                redirectAfterCommentAction($noteIdForRedirect, $returnTo, 'comment_updated');
            } catch (Throwable $e) {
                error_log('comment-edit update error: ' . $e->getMessage());
                $error = 'Yorum güncellenirken beklenmeyen bir hata oluştu.';
            }
        }
    } elseif ($error === '' && $action === 'delete_comment') {
        try {
            $stmt = $pdo->prepare("DELETE FROM note_comments WHERE id = :id AND user_id = :user_id LIMIT 1");
            $stmt->execute([
                'id' => $commentId,
                'user_id' => $userId,
            ]);

            if ($stmt->rowCount() < 1) {
                $error = 'Yorum silinemedi. Yorum size ait olmayabilir.';
            } else {
                redirectAfterCommentAction($noteIdForRedirect, $returnTo, 'comment_deleted');
            }
        } catch (Throwable $e) {
            error_log('comment-edit delete error: ' . $e->getMessage());
            $error = 'Yorum silinirken beklenmeyen bir hata oluştu.';
        }
    } else {
        if ($error === '') {
            $error = 'Geçersiz yorum işlemi.';
        }
    }
}

$stmt = $pdo->prepare("
    SELECT
        nc.id,
        nc.note_id,
        nc.user_id,
        nc.rating,
        nc.comment,
        nc.created_at,
        n.title AS note_title,
        n.course AS note_course,
        n.deleted_at AS note_deleted_at
    FROM note_comments nc
    JOIN notes n ON n.id = nc.note_id
    WHERE nc.id = :id
      AND nc.user_id = :user_id
    LIMIT 1
");
$stmt->execute([
    'id' => $commentId,
    'user_id' => $userId,
]);
$comment = $stmt->fetch();

if (!$comment) {
    header('Location: profile.php?comment_error=not_found#comments');
    exit;
}

$backUrl = $returnTo === 'profile'
    ? 'profile.php#comments'
    : 'note-detail.php?id=' . (int)$comment['note_id'] . '#comments';

$pageTitle = 'Not Bul | Yorum Düzenle';
$pageKey = 'profile';
require __DIR__ . '/includes/header.php';
?>
<main id="mainContent" class="page-shell" tabindex="-1">
    <section class="container section-block">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="panel-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                        <div>
                            <h1 class="h3 mb-1">Yorum Düzenle</h1>
                            <p class="mb-0 text-secondary">
                                <?= htmlspecialchars((string)$comment['note_title'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($comment['note_deleted_at'])): ?>
                                    <span class="badge bg-secondary ms-1">Not arşivde</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-arrow-left"></i> Geri Dön
                        </a>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <form method="POST" action="comment-edit.php?id=<?= (int)$comment['id'] ?>">
                        <input type="hidden" name="action" value="update_comment">
                        <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
                        <input type="hidden" name="note_id" value="<?= (int)$comment['note_id'] ?>">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($commentEditToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="rating" class="form-label">Değerlendirme</label>
                            <select name="rating" id="rating" class="form-select" required>
                                <?php for ($rating = 1; $rating <= 5; $rating += 1): ?>
                                    <option value="<?= $rating ?>" <?= (int)$comment['rating'] === $rating ? 'selected' : '' ?>><?= $rating ?>/5</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="comment" class="form-label">Yorumunuz</label>
                            <textarea name="comment" id="comment" rows="6" maxlength="5000" class="form-control" required><?= htmlspecialchars((string)$comment['comment'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="form-text">Oluşturulma: <?= htmlspecialchars(formatCommentDate((string)$comment['created_at']), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Yorumu Kaydet</button>
                            <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary">Vazgeç</a>
                        </div>
                    </form>

                    <hr>

                    <form
                        method="POST"
                        action="comment-edit.php?id=<?= (int)$comment['id'] ?>"
                        onsubmit="return confirm('Bu yorumu kalıcı olarak silmek istediğinize emin misiniz?');"
                    >
                        <input type="hidden" name="action" value="delete_comment">
                        <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
                        <input type="hidden" name="note_id" value="<?= (int)$comment['note_id'] ?>">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($commentEditToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-trash"></i> Yorumu Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
