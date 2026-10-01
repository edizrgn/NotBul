<?php
declare(strict_types=1);

@session_start();

require_once __DIR__ . '/includes/ratings.php';
require_once __DIR__ . '/includes/admin_notifications.php';
require_once __DIR__ . '/includes/user_notifications.php';
require_once __DIR__ . '/includes/auth_redirect.php';

function resolveDepartmentName(string $departmentId): string
{
    $normalizedId = trim($departmentId);
    if ($normalizedId === '') {
        return '-';
    }

    static $departmentsById = null;

    if ($departmentsById === null) {
        $departmentsById = [];
        $dataPath = __DIR__ . '/assets/data/bolumler.json';

        $json = @file_get_contents($dataPath);
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach (['lisans', 'onlisans'] as $type) {
                    $departments = $decoded[$type] ?? [];
                    if (!is_array($departments)) {
                        continue;
                    }

                    foreach ($departments as $department) {
                        if (!is_array($department)) {
                            continue;
                        }

                        $id = trim((string)($department['id'] ?? ''));
                        $name = trim((string)($department['name'] ?? ''));

                        if ($id !== '' && $name !== '') {
                            $departmentsById[$id] = $name;
                        }
                    }
                }
            }
        }
    }

    return $departmentsById[$normalizedId] ?? $normalizedId;
}

function resolveUniversityName(string $universityId): string
{
    $normalizedId = trim($universityId);
    if ($normalizedId === '') {
        return '-';
    }

    static $universitiesById = null;

    if ($universitiesById === null) {
        $universitiesById = [];
        $dataPath = __DIR__ . '/assets/data/universiteler.json';

        $json = @file_get_contents($dataPath);
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $university) {
                    if (!is_array($university)) {
                        continue;
                    }

                    $id = trim((string)($university['id'] ?? ''));
                    $name = trim((string)($university['name'] ?? ''));

                    if ($id !== '' && $name !== '') {
                        $universitiesById[$id] = $name;
                    }
                }
            }
        }
    }

    return $universitiesById[$normalizedId] ?? $normalizedId;
}

function resolveDepartmentTypeLabel(string $departmentType): string
{
    return match (trim($departmentType)) {
        'lisans' => 'Lisans',
        'onlisans' => 'Önlisans',
        '' => '-',
        default => ucfirst($departmentType),
    };
}

function resolveClassLabel(string $classId): string
{
    $normalizedId = trim($classId);
    if ($normalizedId === '') {
        return '-';
    }

    if (ctype_digit($normalizedId)) {
        return $normalizedId . '. Sınıf';
    }

    return $normalizedId;
}

function formatFileSizeHuman(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    }

    if ($bytes < 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2, ',', '.') . ' MB';
    }

    return number_format($bytes / (1024 * 1024 * 1024), 2, ',', '.') . ' GB';
}

try {
    require_once __DIR__ . '/includes/db.php';
} catch (Throwable $e) {
    error_log('note-detail DB connection error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Şu anda veritabanına bağlanılamıyor. Lütfen daha sonra tekrar deneyin.';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo "HATA: Geçersiz ID ($id)"; 
    exit;
}

$backToResults = authSafeReturnTo($_GET['return_to'] ?? '');
$backPath = ltrim((string)parse_url($backToResults, PHP_URL_PATH), '/');
if (!in_array($backPath, ['search.php', 'index.php'], true)) {
    $backToResults = '';
}
$detailUrl = 'note-detail.php?id=' . $id;
if ($backToResults !== '') {
    $detailUrl .= '&return_to=' . rawurlencode($backToResults);
}

$deleteError = '';
$commentError = '';
$commentText = '';
$commentRating = 0;
$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'delete_note') {
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $requestToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token_note_delete'] ?? '');

    if ($currentUserId <= 0) {
        $deleteError = 'Not silmek için önce giriş yapmalısınız.';
    } elseif ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
        $deleteError = 'Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.';
    } else {
        try {
            $ownerStmt = $pdo->prepare("SELECT id, user_id, deleted_at FROM notes WHERE id = :id");
            $ownerStmt->execute(['id' => $id]);
            $noteToDelete = $ownerStmt->fetch();

            if (!$noteToDelete) {
                header('Location: index.php?error=not_found&id=' . $id);
                exit;
            }

            if ((int)$noteToDelete['user_id'] !== $currentUserId) {
                $deleteError = 'Sadece kendi yüklediğiniz notları silebilirsiniz.';
            } elseif ($noteToDelete['deleted_at'] !== null) {
                header('Location: profile.php?note_deleted=1');
                exit;
            } else {
                $deleteStmt = $pdo->prepare("
                    UPDATE notes
                    SET deleted_at = NOW(),
                        deleted_by = :deleted_by
                    WHERE id = :id
                      AND user_id = :user_id
                      AND deleted_at IS NULL
                    LIMIT 1
                ");
                $deleteStmt->execute([
                    'id' => $id,
                    'deleted_by' => $currentUserId,
                    'user_id' => $currentUserId
                ]);

                if ($deleteStmt->rowCount() < 1) {
                    $deleteError = 'Not arşive alınamadı. Not zaten silinmiş olabilir.';
                } else {
                    header('Location: profile.php?note_deleted=1');
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('note-detail delete error: ' . $e->getMessage());
            $deleteError = 'Not silinirken beklenmeyen bir hata oluştu.';
        }
    }
}

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $postedRating = $_POST['rating'] ?? '';
    $rating = is_string($postedRating) && in_array($postedRating, ['1', '2', '3', '4', '5'], true)
        ? (int)$postedRating
        : 0;
    $commentRating = $rating;
    $commentText = trim((string)($_POST['comment'] ?? ''));
    $requestToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token_note_comment'] ?? '');

    if ($currentUserId <= 0) {
        $commentError = 'Yorum yapmak için giriş yapmalısınız.';
    } elseif ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
        $commentError = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } elseif ($rating < 1 || $rating > 5) {
        $commentError = 'Lütfen 1 ile 5 arasında bir puan seçin.';
    } elseif ($commentText === '') {
        $commentError = 'Yorum boş olamaz.';
    } elseif (mb_strlen($commentText) > 5000) {
        $commentError = 'Yorum en fazla 5000 karakter olabilir.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO note_comments (note_id, user_id, rating, comment) VALUES (:note_id, :user_id, :rating, :comment)");
            $stmt->execute([
                'note_id' => $id,
                'user_id' => $currentUserId,
                'rating' => $rating,
                'comment' => $commentText
            ]);
            $commentId = (int)$pdo->lastInsertId();

            try {
                $notificationStmt = $pdo->prepare("
                    SELECT
                        nc.id,
                        nc.note_id,
                        nc.user_id,
                        nc.rating,
                        nc.comment,
                        nc.created_at,
                        n.title AS note_title,
                        n.course AS note_course,
                        n.topic AS note_topic,
                        n.user_id AS note_owner_id,
                        n.deleted_at AS note_deleted_at,
                        u.first_name,
                        u.last_name,
                        u.email,
                        owner.first_name AS owner_first_name,
                        owner.last_name AS owner_last_name,
                        owner.email AS owner_email,
                        owner.comment_email_notifications AS owner_comment_email_notifications
                    FROM note_comments nc
                    JOIN notes n ON n.id = nc.note_id
                    JOIN users u ON u.id = nc.user_id
                    JOIN users owner ON owner.id = n.user_id
                    WHERE nc.id = :id
                    LIMIT 1
                ");
                $notificationStmt->execute(['id' => $commentId]);
                $newComment = $notificationStmt->fetch();

                if ($newComment) {
                    sendAdminNotification($pdo, 'Yeni yorum yapıldı', 'Bir kullanıcı bir nota yeni yorum ekledi.', [
                        'Yorum' => '#' . (int)$newComment['id'],
                        'Not' => (string)$newComment['note_title'] . ' (#' . (int)$newComment['note_id'] . ')',
                        'Yazan' => adminNotificationUserLabel($newComment) . ' (#' . (int)$newComment['user_id'] . ')',
                        'Ders' => (string)($newComment['note_course'] ?? '-'),
                        'Konu' => (string)($newComment['note_topic'] ?? '-'),
                        'Puan' => (int)$newComment['rating'] . '/5',
                        'Yorum metni' => (string)$newComment['comment'],
                    ], [
                        'Notu Gör' => adminNotificationUrl('note-detail.php?id=' . (int)$newComment['note_id'] . '#comments'),
                        'Yorum Yönetimi' => adminNotificationUrl('admin.php#comments'),
                    ]);
                }

                if (
                    (int)($newComment['note_owner_id'] ?? 0) !== (int)$newComment['user_id']
                    && (int)($newComment['owner_comment_email_notifications'] ?? 1) === 1
                ) {
                    sendUserNotificationEmail(
                        (string)$newComment['owner_email'],
                        trim((string)$newComment['owner_first_name'] . ' ' . (string)$newComment['owner_last_name']),
                        'Notunuza yeni yorum yapıldı',
                        'Yüklediğiniz bir nota yeni yorum ve puan eklendi.',
                        [
                            'Not' => (string)$newComment['note_title'],
                            'Yorum yapan' => adminNotificationUserLabel($newComment),
                            'Puan' => (int)$newComment['rating'] . '/5',
                            'Yorum' => (string)$newComment['comment'],
                        ],
                        [
                            'Yorumu Gör' => userNotificationUrl('note-detail.php?id=' . (int)$newComment['note_id'] . '#comments'),
                            'Bildirim Ayarları' => userNotificationUrl('profile_edit.php'),
                        ],
                        'not yorumu bildirimi'
                    );
                }
            } catch (Throwable $e) {
                error_log('note-detail comment notification prep error: ' . $e->getMessage());
            }

            header('Location: ' . $detailUrl . '&comment_added=1#comments');
            exit;
        } catch (Throwable $e) {
            error_log('note-detail comment error: ' . $e->getMessage());
            $commentError = 'Yorum kaydedilirken bir hata oluştu.';
        }
    }
}

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'delete_comment') {
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $requestToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token_note_comment'] ?? '');

    if ($currentUserId <= 0) {
        $commentError = 'Yorum silmek için giriş yapmalısınız.';
    } elseif ($commentId <= 0) {
        $commentError = 'Geçersiz yorum işlemi.';
    } elseif ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
        $commentError = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        try {
            $stmt = $pdo->prepare("
                DELETE FROM note_comments
                WHERE id = :id
                  AND note_id = :note_id
                  AND user_id = :user_id
                LIMIT 1
            ");
            $stmt->execute([
                'id' => $commentId,
                'note_id' => $id,
                'user_id' => $currentUserId,
            ]);

            if ($stmt->rowCount() < 1) {
                $commentError = 'Yorum silinemedi. Yorum size ait olmayabilir.';
            } else {
                header('Location: ' . $detailUrl . '&comment_deleted=1#comments');
                exit;
            }
        } catch (Throwable $e) {
            error_log('note-detail delete comment error: ' . $e->getMessage());
            $commentError = 'Yorum silinirken beklenmeyen bir hata oluştu.';
        }
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT
            n.*,
            u.first_name,
            u.last_name,
            rs.rating_average,
            COALESCE(rs.rating_count, 0) AS rating_count
        FROM notes n
        JOIN users u ON n.user_id = u.id
        LEFT JOIN (" . noteRatingSummarySql() . ") rs ON rs.note_id = n.id
        WHERE n.id = :id
          AND n.upload_status = 'ready'
          AND n.scan_status = 'clean'
          AND n.deleted_at IS NULL
    ");
    $stmt->execute(['id' => $id]);
    $note = $stmt->fetch();
} catch (Throwable $e) {
    error_log('note-detail query error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Not bilgisi getirilirken bir sorun oluştu. Lütfen daha sonra tekrar deneyin.';
    exit;
}

if (!$note) {
    header('Location: index.php?error=not_found&id=' . $id);
    exit;
}

$deleteToken = (string)($_SESSION['csrf_token_note_delete'] ?? '');
if ($deleteToken === '') {
    try {
        $deleteToken = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $deleteToken = hash('sha256', session_id() . (string)microtime(true));
    }
    $_SESSION['csrf_token_note_delete'] = $deleteToken;
}

$commentToken = (string)($_SESSION['csrf_token_note_comment'] ?? '');
if ($commentToken === '') {
    try {
        $commentToken = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $commentToken = hash('sha256', session_id() . (string)microtime(true));
    }
    $_SESSION['csrf_token_note_comment'] = $commentToken;
}

$comments = [];
try {
    $stmt = $pdo->prepare("
        SELECT nc.*, u.first_name, u.last_name
        FROM note_comments nc
        JOIN users u ON nc.user_id = u.id
        WHERE nc.note_id = :note_id
        ORDER BY nc.created_at DESC
    ");
    $stmt->execute(['note_id' => $id]);
    $comments = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('note-detail fetch comments error: ' . $e->getMessage());
}

$isOwner = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$note['user_id'];
$departmentName = resolveDepartmentName((string)($note['department_id'] ?? ''));
$universityName = resolveUniversityName((string)($note['university_id'] ?? ''));
$departmentTypeLabel = resolveDepartmentTypeLabel((string)($note['department_type'] ?? ''));
$classLabel = resolveClassLabel((string)($note['class_id'] ?? ''));
$courseName = trim((string)($note['course'] ?? ''));
$topicName = trim((string)($note['topic'] ?? ''));
$originalFilename = trim((string)($note['original_filename'] ?? '-'));
$fileExtension = strtoupper(pathinfo($originalFilename, PATHINFO_EXTENSION));
$fileExtension = $fileExtension !== '' ? $fileExtension : '-';
$downloadCount = (int)($note['download_count'] ?? 0);
$fileSizeBytes = (int)($note['file_size'] ?? 0);
$formattedCreatedAt = date('d.m.Y H:i', strtotime((string)$note['created_at']));
$ratingCount = (int)($note['rating_count'] ?? 0);
$ratingAverage = $note['rating_average'] ?? null;
$noteTitle = trim((string)($note['title'] ?? 'Ders Notu'));
$rawMetaDescription = trim((string)($note['description'] ?? ''));

if ($rawMetaDescription === '') {
    $metaParts = array_filter([
        $courseName,
        $topicName,
        $universityName !== '-' ? $universityName : '',
        $departmentName !== '-' ? $departmentName : '',
        $classLabel !== '-' ? $classLabel : '',
    ], static fn(string $part): bool => trim($part) !== '');
    $rawMetaDescription = $metaParts !== []
        ? implode(' - ', $metaParts)
        : 'Not Bul üzerinde paylaşılan ders notunu incele.';
}

$metaTitle = $noteTitle . ' | Not Bul';
$metaDescription = $rawMetaDescription;
$metaType = 'article';
$metaUrl = 'note-detail.php?id=' . (int)$note['id'];
$canonicalUrl = $metaUrl;
$metaImage = 'assets/icons/apple-touch-icon.png';
$metaImageAlt = $noteTitle . ' ders notu önizlemesi';

$pageTitle = 'Not Bul | ' . $noteTitle;
$pageKey = 'detail';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell" id="mainContent" tabindex="-1">
    <section class="container section-block">
        <?php if ($deleteError): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($deleteError) ?></div>
        <?php endif; ?>
        <?php if ($isOwner && ($_GET['uploaded'] ?? '') === '1'): ?>
            <div class="alert alert-success" role="status">Notunuz başarıyla yüklendi ve paylaşıma açıldı.</div>
        <?php elseif ($isOwner && ($_GET['updated'] ?? '') === '1'): ?>
            <div class="alert alert-success" role="status">Notunuz başarıyla güncellendi.</div>
        <?php endif; ?>
        <nav aria-label="Sayfa yolu">
            <ol class="breadcrumb small mb-2">
                <li class="breadcrumb-item"><a href="index.php">Anasayfa</a></li>
                <li class="breadcrumb-item"><a href="search.php?course=<?= rawurlencode($courseName) ?>"><?= htmlspecialchars($courseName !== '' ? $courseName : 'Ders Notları') ?></a></li>
                <li class="breadcrumb-item active text-break" aria-current="page"><?= htmlspecialchars($noteTitle) ?></li>
            </ol>
        </nav>
        <?php if ($backToResults !== ''): ?>
            <a class="d-inline-block small mb-3" href="<?= htmlspecialchars($backToResults, ENT_QUOTES, 'UTF-8') ?>">Arama sonuçlarına dön</a>
        <?php endif; ?>
        <div class="row g-4">
            <div class="col-lg-7 order-2 order-lg-1">
                <div class="preview-shell">
                    <div class="preview-toolbar d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <strong>Dosya Önizleme</strong>
                        <span class="badge text-bg-info"><?= htmlspecialchars($fileExtension) ?></span>
                        <a class="small" href="view.php?id=<?= (int)$note['id'] ?>" target="_blank" rel="noopener noreferrer">Dosyayı yeni sekmede aç</a>
                    </div>
                    <div class="preview-canvas document-preview p-0">
                        <?php 
                        $mime = $note['mime_type'];
                        if (strpos($mime, 'pdf') !== false): 
                        ?>
                            <iframe src="view.php?id=<?= (int)$note['id'] ?>" class="note-pdf-preview" title="<?= htmlspecialchars($noteTitle . ' — PDF önizleme', ENT_QUOTES, 'UTF-8') ?>" loading="lazy"></iframe>
                        <?php elseif (strpos($mime, 'image/') === 0): ?>
                            <img src="view.php?id=<?= $note['id'] ?>" class="img-fluid" alt="<?= htmlspecialchars($note['title']) ?>">
                        <?php else: ?>
                            <div class="p-4 text-center">
                                <p class="mb-2 text-secondary">Bu dosya formatı (<?= htmlspecialchars($fileExtension) ?>) tarayıcıda önizleme desteklemiyor.</p>
                                <p class="mb-0 text-secondary">'İndir' butonu ile dosyayı indirebilirsiniz.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 order-1 order-lg-2">
                <article class="panel-card h-100">
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                        <h1 class="section-title mb-0"><?= htmlspecialchars($note['title']) ?></h1>
                        <?= renderRatingSummary($ratingAverage, $ratingCount, true, 'Henüz puan yok') ?>
                    </div>
                    <p class="text-secondary text-break"><?= nl2br(htmlspecialchars($note['description'] ?? 'Açıklama belirtilmedi.')) ?></p>

                    <div class="mb-4 d-grid gap-2 d-sm-flex flex-wrap">
                        <a class="btn btn-primary btn-lg px-4" href="view.php?id=<?= (int)$note['id'] ?>&amp;download=1" download="<?= htmlspecialchars($note['original_filename'], ENT_QUOTES, 'UTF-8') ?>">İndir</a>
                        <a class="btn btn-outline-primary btn-lg" href="search.php?similar_to=<?= (int)$note['id'] ?>">Benzer Notlar</a>
                    </div>

                    <div class="note-meta-grid">
                        <div><span>Yükleyen</span><strong><?= htmlspecialchars($note['first_name'] . ' ' . $note['last_name']) ?></strong></div>
                        <div><span>Üniversite</span><strong><?= htmlspecialchars($universityName) ?></strong></div>
                        <div><span>Program Türü</span><strong><?= htmlspecialchars($departmentTypeLabel) ?></strong></div>
                        <div><span>Bölüm</span><strong><?= htmlspecialchars($departmentName) ?></strong></div>
                        <div><span>Sınıf</span><strong><?= htmlspecialchars($classLabel) ?></strong></div>
                        <div><span>Ders</span><strong><?= htmlspecialchars($courseName !== '' ? $courseName : '-') ?></strong></div>
                        <div><span>Konu</span><strong><?= htmlspecialchars($topicName !== '' ? $topicName : '-') ?></strong></div>
                        <div><span>İndirme</span><strong><?= number_format($downloadCount, 0, ',', '.') ?></strong></div>
                        <div><span>Dosya Türü</span><strong><?= htmlspecialchars($fileExtension) ?></strong></div>
                        <div><span>Dosya Adı</span><strong><?= htmlspecialchars($originalFilename) ?></strong></div>
                        <div><span>Boyut</span><strong><?= htmlspecialchars(formatFileSizeHuman($fileSizeBytes)) ?></strong></div>
                        <div><span>Yüklenme</span><strong><?= htmlspecialchars($formattedCreatedAt) ?></strong></div>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <?php 
                        $tagStr = (string)$note['tags'];
                        $tags = explode(',', $tagStr);
                        foreach ($tags as $tag): 
                            $tag = trim($tag);
                            if ($tag !== ''):
                        ?>
                            <span class="badge rounded-pill text-bg-light">#<?= htmlspecialchars($tag) ?></span>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>

                    <?php if ($isOwner): ?>
                        <form method="POST" action="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-3 d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-primary" href="note-edit.php?id=<?= (int)$note['id'] ?>">Notu Düzenle</a>
                            <input type="hidden" name="action" value="delete_note">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($deleteToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button
                                type="submit"
                                class="btn btn-outline-danger"
                                onclick="return confirm('Bu notu arşive alıp yayından kaldırmak istediğinize emin misiniz?');"
                            >
                                Notu Arşivle
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-12">
                <div class="panel-card" id="comments">
                    <h2 class="section-title h4 mb-4">Yorumlar</h2>
                    
                    <?php if ($commentError): ?>
                        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($commentError) ?></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['comment_added'])): ?>
                        <div class="alert alert-success" role="status">Yorumunuz başarıyla eklendi.</div>
                    <?php elseif (isset($_GET['comment_updated'])): ?>
                        <div class="alert alert-success" role="status">Yorumunuz başarıyla güncellendi.</div>
                    <?php elseif (isset($_GET['comment_deleted'])): ?>
                        <div class="alert alert-success" role="status">Yorumunuz başarıyla silindi.</div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST" action="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>#comments" class="mb-4">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($commentToken, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="rating" class="form-label">Değerlendirme</label>
                                    <select name="rating" id="rating" class="form-select" aria-describedby="ratingHelp" required>
                                        <option value="" <?= $commentRating < 1 || $commentRating > 5 ? 'selected' : '' ?>>Puan seçin</option>
                                        <?php foreach ([5 => 'Harika', 4 => 'İyi', 3 => 'Orta', 2 => 'Kötü', 1 => 'Çok Kötü'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $commentRating === $value ? 'selected' : '' ?>><?= $value ?> - <?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-9">
                                    <label for="comment" class="form-label">Yorumunuz</label>
                                    <textarea name="comment" id="comment" rows="3" maxlength="5000" class="form-control" placeholder="Not hakkında düşünceleriniz..." required><?= htmlspecialchars($commentText, ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                                <div class="col-12 text-end">
                                    <p class="form-text text-start mt-0" id="ratingHelp">Her kullanıcı için son yorumundaki puan değerlendirmeye alınır.</p>
                                    <button type="submit" class="btn btn-primary">Yorum Gönder</button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info" role="status">Yorum yapabilmek için <a href="<?= htmlspecialchars(authLoginUrl($detailUrl . '#comments'), ENT_QUOTES, 'UTF-8') ?>">giriş yapmalısınız</a>.</div>
                    <?php endif; ?>

                    <div class="comments-list">
                        <?php if (empty($comments)): ?>
                            <p class="text-secondary">Henüz yorum yapılmamış. İlk yorumu siz yapın!</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <?php $isCommentOwner = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$comment['user_id']; ?>
                                <article class="comment-item p-3 border rounded mb-3 bg-light">
                                    <header class="d-flex justify-content-between align-items-start gap-3 mb-2 flex-wrap">
                                        <div>
                                            <strong><?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?></strong>
                                            <div class="comment-meta small">
                                                <?= renderRatingStars((int)$comment['rating']) ?>
                                                <span class="text-secondary"><?= date('d.m.Y H:i', strtotime((string)$comment['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        <?php if ($isCommentOwner): ?>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <a class="btn btn-sm btn-outline-primary" href="comment-edit.php?id=<?= (int)$comment['id'] ?>&amp;return=note">Düzenle</a>
                                                <form method="POST" action="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>#comments" class="d-inline-block">
                                                    <input type="hidden" name="action" value="delete_comment">
                                                    <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($commentToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Bu yorumu kalıcı olarak silmek istediğinize emin misiniz?');"
                                                    >
                                                        Sil
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </header>
                                    <p class="mb-0 text-break"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
