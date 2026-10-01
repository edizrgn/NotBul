<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/ratings.php';
require_once __DIR__ . '/includes/auth_redirect.php';
@session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . authLoginUrl('profile.php'));
    exit;
}

$userId = (int)$_SESSION['user_id'];

function profileRequestedPage(string $parameter): int
{
    $value = $_GET[$parameter] ?? 1;
    if (!is_scalar($value)) {
        return 1;
    }
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $page === false ? 1 : $page;
}

$profilePages = [
    'notes_page' => profileRequestedPage('notes_page'),
    'archived_page' => profileRequestedPage('archived_page'),
    'comments_page' => profileRequestedPage('comments_page'),
];

function profilePageUrl(array $pages, array $extra = [], string $anchor = ''): string
{
    $parameters = array_merge(array_filter($pages, static fn (int $page): bool => $page > 1), $extra);
    return 'profile.php' . ($parameters !== [] ? '?' . http_build_query($parameters) : '') . ($anchor !== '' ? '#' . $anchor : '');
}

function renderProfilePagination(int $count, array $pages, string $parameter, string $anchor, string $label): string
{
    if ($count === 0) {
        return '';
    }

    $page = $pages[$parameter];
    $pageCount = max(1, (int)ceil($count / 12));
    $start = ($page - 1) * 12 + 1;
    $end = min($count, $page * 12);
    $html = '<p class="small text-secondary mt-3 mb-2">' . $count . ' kayıt • ' . $start . '–' . $end . ' gösteriliyor</p>';
    if ($pageCount === 1) {
        return $html;
    }

    $link = static function (int $target, string $text, string $accessibleLabel) use ($pages, $parameter, $anchor): string {
        $targetPages = $pages;
        $targetPages[$parameter] = $target;
        return '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(profilePageUrl($targetPages, [], $anchor), ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($accessibleLabel, ENT_QUOTES, 'UTF-8') . '">' . $text . '</a></li>';
    };

    $html .= '<nav aria-label="' . htmlspecialchars($label . ' sayfaları', ENT_QUOTES, 'UTF-8') . '"><ul class="pagination flex-wrap gap-1 mb-0">';
    if ($page > 1) {
        $html .= $link($page - 1, 'Önceki', 'Önceki sayfa');
    }
    $first = max(1, min($page - 2, $pageCount - 4));
    $last = min($pageCount, $first + 4);
    if ($first > 1) {
        $html .= $link(1, '1', '1. sayfa');
        if ($first > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    for ($target = $first; $target <= $last; $target++) {
        $html .= $target === $page
            ? '<li class="page-item active"><span class="page-link" aria-current="page">' . $target . '</span></li>'
            : $link($target, (string)$target, $target . '. sayfa');
    }
    if ($last < $pageCount) {
        if ($last < $pageCount - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= $link($pageCount, (string)$pageCount, $pageCount . '. sayfa');
    }
    if ($page < $pageCount) {
        $html .= $link($page + 1, 'Sonraki', 'Sonraki sayfa');
    }
    return $html . '</ul></nav>';
}

$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, created_at, verified FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: ' . authLoginUrl('profile.php'));
    exit;
}

// CSRF token for note actions
$noteActionToken = (string)($_SESSION['csrf_token_profile_note_action'] ?? '');
if ($noteActionToken === '') {
    try {
        $noteActionToken = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $noteActionToken = hash('sha256', session_id() . (string)microtime(true));
    }
    $_SESSION['csrf_token_profile_note_action'] = $noteActionToken;
}

$noteActionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $noteId = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
    $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
    $requestToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token_profile_note_action'] ?? '');

    if (!in_array($action, ['soft_delete_note', 'restore_note', 'delete_comment'], true)) {
        $noteActionError = 'Geçersiz işlem.';
    } elseif ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
        $noteActionError = 'Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.';
    } else {
        try {
            if ($action === 'delete_comment') {
                if ($commentId <= 0) {
                    $noteActionError = 'Geçersiz yorum işlemi.';
                } else {
                    $actionStmt = $pdo->prepare("
                        DELETE FROM note_comments
                        WHERE id = :id
                          AND user_id = :user_id
                        LIMIT 1
                    ");
                    $actionStmt->execute([
                        'id' => $commentId,
                        'user_id' => $userId
                    ]);

                    if ($actionStmt->rowCount() > 0) {
                        header('Location: ' . profilePageUrl($profilePages, ['comment_deleted' => 1], 'comments'), true, 303);
                        exit;
                    }

                    $noteActionError = 'Yorum silinemedi. Yorum size ait olmayabilir.';
                }
            } elseif ($noteId <= 0) {
                $noteActionError = 'Geçersiz not işlemi.';
            } elseif ($action === 'soft_delete_note') {
                $actionStmt = $pdo->prepare("
                    UPDATE notes
                    SET deleted_at = NOW(),
                        deleted_by = :deleted_by
                    WHERE id = :id
                      AND user_id = :user_id
                      AND deleted_at IS NULL
                    LIMIT 1
                ");
                $actionStmt->execute([
                    'deleted_by' => $userId,
                    'id' => $noteId,
                    'user_id' => $userId
                ]);

                if ($actionStmt->rowCount() > 0) {
                    header('Location: ' . profilePageUrl($profilePages, ['note_deleted' => 1], 'notes'), true, 303);
                    exit;
                }

                $noteActionError = 'Not silinemedi. Not zaten silinmiş olabilir veya size ait olmayabilir.';
            } else {
                $actionStmt = $pdo->prepare("
                    UPDATE notes
                    SET deleted_at = NULL,
                        deleted_by = NULL
                    WHERE id = :id
                      AND user_id = :user_id
                      AND deleted_at IS NOT NULL
                    LIMIT 1
                ");
                $actionStmt->execute([
                    'id' => $noteId,
                    'user_id' => $userId
                ]);

                if ($actionStmt->rowCount() > 0) {
                    header('Location: ' . profilePageUrl($profilePages, ['note_restored' => 1], 'archived'), true, 303);
                    exit;
                }

                $noteActionError = 'Not geri alınamadı. Not zaten aktif olabilir veya size ait olmayabilir.';
            }
        } catch (Throwable $e) {
            error_log('profile note action error: ' . $e->getMessage());
            $noteActionError = 'Not işlemi sırasında beklenmeyen bir hata oluştu.';
        }
    }
}

// Active note count
$stmtNotes = $pdo->prepare("SELECT COUNT(*) as note_count FROM notes WHERE user_id = :uid AND deleted_at IS NULL");
$stmtNotes->execute(['uid' => $userId]);
$noteCount = (int) $stmtNotes->fetch()['note_count'];

// Deleted note count
$stmtDeletedCount = $pdo->prepare("SELECT COUNT(*) as deleted_count FROM notes WHERE user_id = :uid AND deleted_at IS NOT NULL");
$stmtDeletedCount->execute(['uid' => $userId]);
$deletedNoteCount = (int) $stmtDeletedCount->fetch()['deleted_count'];

$stmtCommentCount = $pdo->prepare("SELECT COUNT(*) as comment_count FROM note_comments WHERE user_id = :uid");
$stmtCommentCount->execute(['uid' => $userId]);
$commentCount = (int)$stmtCommentCount->fetch()['comment_count'];

$profilePages['notes_page'] = min($profilePages['notes_page'], max(1, (int)ceil($noteCount / 12)));
$profilePages['archived_page'] = min($profilePages['archived_page'], max(1, (int)ceil($deletedNoteCount / 12)));
$profilePages['comments_page'] = min($profilePages['comments_page'], max(1, (int)ceil($commentCount / 12)));

$stmtMyNotes = $pdo->prepare("
    SELECT
        n.id,
        n.title,
        n.course,
        n.topic,
        n.original_filename,
        n.file_size,
        n.download_count,
        n.upload_status,
        n.scan_status,
        n.created_at,
        rs.rating_average,
        COALESCE(rs.rating_count, 0) AS rating_count
    FROM notes n
    LEFT JOIN (" . noteRatingSummarySql() . ") rs ON rs.note_id = n.id
    WHERE n.user_id = :uid
      AND n.deleted_at IS NULL
    ORDER BY n.created_at DESC, n.id DESC
    LIMIT 12 OFFSET :offset
");
$stmtMyNotes->bindValue('uid', $userId, PDO::PARAM_INT);
$stmtMyNotes->bindValue('offset', ($profilePages['notes_page'] - 1) * 12, PDO::PARAM_INT);
$stmtMyNotes->execute();
$myNotes = $stmtMyNotes->fetchAll();

$stmtDeletedNotes = $pdo->prepare("
    SELECT
        n.id,
        n.title,
        n.course,
        n.topic,
        n.original_filename,
        n.file_size,
        n.download_count,
        n.deleted_at,
        n.created_at,
        rs.rating_average,
        COALESCE(rs.rating_count, 0) AS rating_count
    FROM notes n
    LEFT JOIN (" . noteRatingSummarySql() . ") rs ON rs.note_id = n.id
    WHERE n.user_id = :uid
      AND n.deleted_at IS NOT NULL
    ORDER BY n.deleted_at DESC, n.id DESC
    LIMIT 12 OFFSET :offset
");
$stmtDeletedNotes->bindValue('uid', $userId, PDO::PARAM_INT);
$stmtDeletedNotes->bindValue('offset', ($profilePages['archived_page'] - 1) * 12, PDO::PARAM_INT);
$stmtDeletedNotes->execute();
$deletedNotes = $stmtDeletedNotes->fetchAll();

$stmtMyComments = $pdo->prepare("
    SELECT
        nc.id,
        nc.note_id,
        nc.rating,
        nc.comment,
        nc.created_at,
        n.title AS note_title,
        n.course AS note_course,
        n.deleted_at AS note_deleted_at,
        n.upload_status,
        n.scan_status
    FROM note_comments nc
    JOIN notes n ON n.id = nc.note_id
    WHERE nc.user_id = :uid
    ORDER BY nc.created_at DESC, nc.id DESC
    LIMIT 12 OFFSET :offset
");
$stmtMyComments->bindValue('uid', $userId, PDO::PARAM_INT);
$stmtMyComments->bindValue('offset', ($profilePages['comments_page'] - 1) * 12, PDO::PARAM_INT);
$stmtMyComments->execute();
$myComments = $stmtMyComments->fetchAll();

$noteActionSuccess = '';
if (isset($_GET['note_deleted']) && $_GET['note_deleted'] === '1') {
    $noteActionSuccess = 'Not başarıyla arşive alındı. Dilerseniz aşağıdaki "Arşivlenen Notlar" bölümünden geri alabilirsiniz.';
} elseif (isset($_GET['note_restored']) && $_GET['note_restored'] === '1') {
    $noteActionSuccess = 'Not başarıyla geri alındı ve tekrar listelere dahil edildi.';
} elseif (isset($_GET['comment_updated']) && $_GET['comment_updated'] === '1') {
    $noteActionSuccess = 'Yorum başarıyla güncellendi.';
} elseif (isset($_GET['comment_deleted']) && $_GET['comment_deleted'] === '1') {
    $noteActionSuccess = 'Yorum başarıyla silindi.';
} elseif (isset($_GET['comment_error']) && $_GET['comment_error'] === 'not_found') {
    $noteActionError = 'Yorum bulunamadı veya size ait değil.';
} elseif (isset($_GET['note_updated']) && $_GET['note_updated'] === '1') {
    $noteActionSuccess = 'Not bilgileri güncellendi.';
} elseif (isset($_GET['note_error']) && $_GET['note_error'] === 'not_found') {
    $noteActionError = 'Not bulunamadı veya size ait değil.';
}

$pageTitle = 'Not Bul | Profilim';
$pageKey = 'profile';
require __DIR__ . '/includes/header.php';
?>
<main id="mainContent" class="page-shell" tabindex="-1">
    <section class="container section-block mt-5">
        <?php if ($noteActionSuccess): ?>
            <div class="alert alert-success mb-3"><?= htmlspecialchars($noteActionSuccess) ?></div>
        <?php endif; ?>
        <?php if ($noteActionError): ?>
            <div class="alert alert-danger mb-3"><?= htmlspecialchars($noteActionError) ?></div>
        <?php endif; ?>
        <div class="row g-4 align-items-start">
            <div class="col-lg-5">
                <div class="panel-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Profil Bilgileri</h1>
                        <a href="profile_edit.php" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i> Düzenle</a>
                    </div>
                    
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-4 text-secondary">
                                    <i class="fa-solid fa-user me-2"></i>Ad Soyad
                                </div>
                                <div class="col-sm-8 text-dark fw-medium">
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                </div>
                            </div>
                            <hr class="text-muted">
                            <div class="row mb-3">
                                <div class="col-sm-4 text-secondary">
                                    <i class="fa-solid fa-envelope me-2"></i>E-posta
                                </div>
                                <div class="col-sm-8 text-dark fw-medium">
                                    <?= htmlspecialchars($user['email']) ?>
                                    <?php if ((int)$user['verified'] === 1): ?>
                                        <span class="badge bg-success ms-2"><i class="fa-solid fa-check-circle"></i> Doğrulanmış</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark ms-2"><i class="fa-solid fa-clock"></i> Doğrulanmamış</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr class="text-muted">
                            <div class="row mb-3">
                                <div class="col-sm-4 text-secondary">
                                    <i class="fa-solid fa-calendar-days me-2"></i>Kayıt Tarihi
                                </div>
                                <div class="col-sm-8 text-dark fw-medium">
                                    <?= htmlspecialchars(date('d.m.Y H:i', strtotime($user['created_at']))) ?>
                                </div>
                            </div>
                            <hr class="text-muted">
                            <div class="row">
                                <div class="col-sm-4 text-secondary">
                                    <i class="fa-solid fa-file-lines me-2"></i>Yüklenen Notlar
                                </div>
                                <div class="col-sm-8 text-dark fw-medium">
                                    <?= $noteCount ?> aktif not yüklendi.
                                </div>
                            </div>
                            <hr class="text-muted">
                            <div class="row">
                                <div class="col-sm-4 text-secondary">
                                    <i class="fa-solid fa-box-archive me-2"></i>Arşivlenen Notlar
                                </div>
                                <div class="col-sm-8 text-dark fw-medium">
                                    <?= $deletedNoteCount ?> not arşivde.
                                </div>
                            </div>
                            <hr class="text-muted">
                            <div class="row">
                                <div class="col-sm-4 text-secondary">
                                    <i class="fa-solid fa-comments me-2"></i>Yorumlar
                                </div>
                                <div class="col-sm-8 text-dark fw-medium">
                                    <?= $commentCount ?> yorum yaptınız.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div id="notes" class="panel-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h3 mb-0">Notlarım</h2>
                        <a href="upload.php" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-upload me-1"></i> Yeni Not Yükle
                        </a>
                    </div>

                    <?php if (empty($myNotes)): ?>
                        <div class="empty-state">
                            Henüz not yüklemediniz. <a href="upload.php" class="text-decoration-none">İlk notunu şimdi yükle</a>.
                        </div>
                    <?php else: ?>
                        <div class="search-results">
                            <?php foreach ($myNotes as $note): ?>
                                <?php
                                    $isVisible = (string)$note['upload_status'] === 'ready' && (string)$note['scan_status'] === 'clean';
                                    $statusText = $isVisible ? 'Yayında' : 'İncelemede';
                                    $statusClass = $isVisible ? 'bg-success' : 'bg-warning text-dark';
                                ?>
                                <article class="result-item">
                                    <div class="my-note-item d-flex justify-content-between align-items-start gap-3">
                                        <div class="my-note-main">
                                            <h3 class="h6 mb-1"><?= htmlspecialchars((string)$note['title']) ?></h3>
                                            <p class="mb-2 text-secondary small">
                                                <?= htmlspecialchars((string)($note['course'] ?? '-')) ?>
                                                <?php if (!empty($note['topic'])): ?>
                                                    • <?= htmlspecialchars((string)$note['topic']) ?>
                                                <?php endif; ?>
                                            </p>
                                            <div class="small text-secondary my-note-file" title="<?= htmlspecialchars((string)$note['original_filename']) ?>">
                                                <?= htmlspecialchars((string)$note['original_filename']) ?>
                                                • <?= number_format(((int)$note['file_size']) / 1024, 1, ',', '.') ?> KB
                                            </div>
                                            <?php if ((int)($note['rating_count'] ?? 0) > 0): ?>
                                                <div class="mt-2">
                                                    <?= renderRatingSummary($note['rating_average'] ?? null, (int)$note['rating_count'], true) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="my-note-side text-end">
                                            <span class="badge <?= htmlspecialchars($statusClass) ?> mb-2"><?= htmlspecialchars($statusText) ?></span>
                                            <div class="small text-secondary mb-2">
                                                <?= (int)$note['download_count'] ?> indirme
                                                <br>
                                                <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$note['created_at']))) ?>
                                            </div>
                                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                <a href="note-edit.php?id=<?= (int)$note['id'] ?>" class="btn btn-sm btn-outline-primary">Düzenle</a>
                                                <form method="POST" action="<?= htmlspecialchars(profilePageUrl($profilePages, [], 'notes'), ENT_QUOTES, 'UTF-8') ?>" class="d-inline-block">
                                                    <input type="hidden" name="action" value="soft_delete_note">
                                                    <input type="hidden" name="note_id" value="<?= (int)$note['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($noteActionToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Bu notu arşive alıp yayından kaldırmak istediğinize emin misiniz?');"
                                                    >
                                                        Arşivle
                                                    </button>
                                                </form>
                                                <?php if ($isVisible): ?>
                                                    <a href="note-detail.php?id=<?= (int)$note['id'] ?>" class="btn btn-sm btn-primary">Detay</a>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Henüz Yayında Değil</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?= renderProfilePagination($noteCount, $profilePages, 'notes_page', 'notes', 'Notlarım') ?>
                </div>

                <div id="archived" class="panel-card mt-4">
                    <h2 class="h4 mb-3">Arşivlenen Notlar</h2>

                    <?php if (empty($deletedNotes)): ?>
                        <div class="empty-state">
                            Arşivlenen not bulunmuyor.
                        </div>
                    <?php else: ?>
                        <div class="search-results">
                            <?php foreach ($deletedNotes as $deletedNote): ?>
                                <article class="result-item">
                                    <div class="my-note-item d-flex justify-content-between align-items-start gap-3">
                                        <div class="my-note-main">
                                            <h3 class="h6 mb-1"><?= htmlspecialchars((string)$deletedNote['title']) ?></h3>
                                            <p class="mb-2 text-secondary small">
                                                <?= htmlspecialchars((string)($deletedNote['course'] ?? '-')) ?>
                                                <?php if (!empty($deletedNote['topic'])): ?>
                                                    • <?= htmlspecialchars((string)$deletedNote['topic']) ?>
                                                <?php endif; ?>
                                            </p>
                                            <div class="small text-secondary my-note-file" title="<?= htmlspecialchars((string)$deletedNote['original_filename']) ?>">
                                                <?= htmlspecialchars((string)$deletedNote['original_filename']) ?>
                                                • <?= number_format(((int)$deletedNote['file_size']) / 1024, 1, ',', '.') ?> KB
                                            </div>
                                            <?php if ((int)($deletedNote['rating_count'] ?? 0) > 0): ?>
                                                <div class="mt-2">
                                                    <?= renderRatingSummary($deletedNote['rating_average'] ?? null, (int)$deletedNote['rating_count'], true) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="my-note-side text-end">
                                            <span class="badge bg-secondary mb-2">Arşivde</span>
                                            <div class="small text-secondary mb-2">
                                                Silinme: <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$deletedNote['deleted_at']))) ?>
                                            </div>
                                            <a href="note-edit.php?id=<?= (int)$deletedNote['id'] ?>" class="btn btn-sm btn-outline-primary">Düzenle</a>
                                            <form method="POST" action="<?= htmlspecialchars(profilePageUrl($profilePages, [], 'archived'), ENT_QUOTES, 'UTF-8') ?>" class="d-inline-block">
                                                <input type="hidden" name="action" value="restore_note">
                                                <input type="hidden" name="note_id" value="<?= (int)$deletedNote['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($noteActionToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Geri Al</button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?= renderProfilePagination($deletedNoteCount, $profilePages, 'archived_page', 'archived', 'Arşivlenen notlar') ?>
                </div>

                <div id="comments" class="panel-card mt-4">
                    <h2 class="h4 mb-3">Yorumlarım</h2>

                    <?php if (empty($myComments)): ?>
                        <div class="empty-state">
                            Henüz yorum yapmadınız.
                        </div>
                    <?php else: ?>
                        <div class="search-results">
                            <?php foreach ($myComments as $comment): ?>
                                <?php
                                    $canViewNote = empty($comment['note_deleted_at'])
                                        && (string)$comment['upload_status'] === 'ready'
                                        && (string)$comment['scan_status'] === 'clean';
                                ?>
                                <article class="result-item">
                                    <div class="my-note-item d-flex justify-content-between align-items-start gap-3">
                                        <div class="my-note-main">
                                            <h3 class="h6 mb-1"><?= htmlspecialchars((string)$comment['note_title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                            <p class="mb-2 text-secondary small">
                                                <?= htmlspecialchars((string)($comment['note_course'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                                • <?= renderRatingStars((int)$comment['rating']) ?>
                                                • <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$comment['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($comment['note_deleted_at'])): ?>
                                                    • Not arşivde
                                                <?php endif; ?>
                                            </p>
                                            <p class="mb-0 text-break"><?= nl2br(htmlspecialchars((string)$comment['comment'], ENT_QUOTES, 'UTF-8')) ?></p>
                                        </div>

                                        <div class="my-note-side text-end">
                                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                <a class="btn btn-sm btn-outline-primary" href="comment-edit.php?id=<?= (int)$comment['id'] ?>&amp;return=profile">Düzenle</a>
                                                <?php if ($canViewNote): ?>
                                                    <a class="btn btn-sm btn-outline-secondary" href="note-detail.php?id=<?= (int)$comment['note_id'] ?>#comments">Notu Gör</a>
                                                <?php endif; ?>
                                                <form method="POST" action="<?= htmlspecialchars(profilePageUrl($profilePages, [], 'comments'), ENT_QUOTES, 'UTF-8') ?>" class="d-inline-block">
                                                    <input type="hidden" name="action" value="delete_comment">
                                                    <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($noteActionToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Bu yorumu kalıcı olarak silmek istediğinize emin misiniz?');"
                                                    >
                                                        Sil
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?= renderProfilePagination($commentCount, $profilePages, 'comments_page', 'comments', 'Yorumlarım') ?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
