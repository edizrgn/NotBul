<?php
declare(strict_types=1);

@session_start();
require_once __DIR__ . '/includes/auth_redirect.php';

$requestId = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? $_GET['id'] ?? 0) : ($_GET['id'] ?? 0);
$noteId = is_scalar($requestId) ? (int)$requestId : 0;
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . authLoginUrl('note-edit.php?id=' . $noteId));
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/note_form.php';
require_once __DIR__ . '/includes/note_file_panel.php';

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT *
    FROM notes
    WHERE id = :id AND user_id = :user_id
    LIMIT 1
");
$stmt->execute(['id' => $noteId, 'user_id' => $userId]);
$note = $stmt->fetch();
if (!$note) {
    header('Location: profile.php?note_error=not_found#notes');
    exit;
}

$editToken = (string)($_SESSION['csrf_token_note_edit'] ?? '');
if ($editToken === '') {
    $editToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_note_edit'] = $editToken;
}

$universities = json_decode((string)file_get_contents(__DIR__ . '/assets/data/universiteler.json'), true) ?: [];
$departmentsByType = json_decode((string)file_get_contents(__DIR__ . '/assets/data/bolumler.json'), true) ?: [];
$formValues = noteFormValues($note);
$error = '';
$fileAction = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$oversizedPost = $_SERVER['REQUEST_METHOD'] === 'POST' && $_POST === []
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > getMaxUploadBytes();
$fileFlash = $_SESSION['note_file_flash'][$noteId] ?? null;
unset($_SESSION['note_file_flash'][$noteId]);

if ($oversizedPost) {
    $error = 'Dosya boyutu ' . getMaxUploadMb() . ' MB sınırını aşıyor. Mevcut dosyanız korundu.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($fileAction, ['replace_file', 'undo_file'], true)) {
    try {
        $requestToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($requestToken === '' || !hash_equals($editToken, $requestToken)) {
            throw new NoteFileException('Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.');
        }
        changeNoteFile($pdo, $noteId, $userId, false,
            is_string($_POST['file_identity'] ?? null) ? $_POST['file_identity'] : '',
            $fileAction === 'replace_file' ? ($_FILES['note_file'] ?? []) : null,
            is_string($_POST['undo_token'] ?? null) ? $_POST['undo_token'] : '');
        $_SESSION['note_file_flash'][$noteId] = $fileAction === 'replace_file'
            ? 'Dosya değiştirildi. Son değişikliği 24 saat içinde geri alabilirsiniz.' : 'Önceki dosya geri yüklendi.';
        header('Location: note-edit.php?id=' . $noteId . '#noteFileHeading', true, 303);
        exit;
    } catch (NoteFileException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('note file change error: ' . $e->getMessage());
        $error = 'Dosya değişikliği tamamlanamadı. Sayfayı yenileyip tekrar deneyin.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$oversizedPost && !in_array($fileAction, ['replace_file', 'undo_file'], true)) {
    $formValues = noteFormValues($_POST);
    $requestToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $errors = noteFormErrors($formValues);

    if ($requestToken === '' || !hash_equals($editToken, $requestToken)) {
        $errors[] = 'Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.';
    }
    if ($formValues['university_id'] !== ''
        && $formValues['university_id'] !== (string)($note['university_id'] ?? '')
        && !in_array($formValues['university_id'], array_column($universities, 'id'), true)) {
        $errors[] = 'Listeden geçerli bir üniversite seçin.';
    }
    $departmentUnchanged = $formValues['department_id'] === (string)($note['department_id'] ?? '')
        && $formValues['department_type'] === (string)($note['department_type'] ?? '');
    if ($formValues['department_id'] !== '' && !$departmentUnchanged
        && !in_array($formValues['department_id'], array_column($departmentsByType[$formValues['department_type']] ?? [], 'id'), true)) {
        $errors[] = 'Seçtiğiniz program türüne uygun bir bölüm seçin.';
    }

    if ($errors !== []) {
        $error = implode(' ', $errors);
    } else {
        try {
            $bindings = noteFormBindings($formValues);
            $bindings['id'] = $noteId;
            $bindings['user_id'] = $userId;
            $stmt = $pdo->prepare("
                UPDATE notes
                SET title = :title, description = :description, university_id = :university_id,
                    department_type = :department_type, department_id = :department_id,
                    class_id = :class_id, course = :course, topic = :topic, tags = :tags
                WHERE id = :id AND user_id = :user_id
                LIMIT 1
            ");
            $stmt->execute($bindings);
            $isPublic = empty($note['deleted_at']) && $note['upload_status'] === 'ready' && $note['scan_status'] === 'clean';
            header('Location: ' . ($isPublic ? 'note-detail.php?id=' . $noteId . '&updated=1' : 'profile.php?note_updated=1#notes'), true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('note edit update error: ' . $e->getMessage());
            $error = 'Not bilgileri kaydedilemedi. Bilgileriniz korundu; lütfen tekrar deneyin.';
        }
    }
}

$undoInfo = null;
$historyError = '';
try {
    $undoInfo = noteFileUndoInfo($pdo, $note, $userId);
} catch (Throwable $e) {
    error_log('note file undo info: ' . $e->getMessage());
    $historyError = 'Geri alma bilgileri şu anda alınamıyor. Lütfen daha sonra tekrar deneyin.';
}
$notesPayload = noteFormSuggestions($pdo);
$pageTitle = 'Not Bul | Not Düzenle';
$pageKey = 'profile';
require __DIR__ . '/includes/header.php';
?>
<main id="mainContent" class="page-shell" tabindex="-1">
    <section class="container section-block">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="panel-card mb-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                        <div>
                            <h1 class="h3 mb-1">Not Düzenle</h1>
                            <p class="mb-0 text-secondary"><?= htmlspecialchars((string)$note['original_filename'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if (!empty($note['deleted_at'])): ?>
                                <span class="badge bg-secondary mt-2">Arşivde</span>
                            <?php endif; ?>
                        </div>
                        <a href="profile.php#<?= !empty($note['deleted_at']) ? 'archived' : 'notes' ?>" class="btn btn-sm btn-outline-secondary">Profilime Dön</a>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if (is_string($fileFlash) && $fileFlash !== ''): ?>
                        <div class="alert alert-success" role="status"><?= htmlspecialchars($fileFlash, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <?php renderNoteFilePanel($note, $editToken, 'note-edit.php?id=' . $noteId, $undoInfo, $historyError); ?>

                <div class="panel-card">
                    <h2 class="h4 mb-3">Not Bilgileri</h2>
                    <form method="POST" action="note-edit.php?id=<?= $noteId ?>" data-hierarchy-group data-filter-source="public">
                        <input type="hidden" name="id" value="<?= $noteId ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($editToken, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="editTitle">Başlık</label>
                                <input class="form-control" id="editTitle" name="title" value="<?= htmlspecialchars($formValues['title'], ENT_QUOTES, 'UTF-8') ?>" maxlength="160" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="editDescription">Açıklama</label>
                                <textarea class="form-control" id="editDescription" name="description" rows="4" maxlength="1000"><?= htmlspecialchars($formValues['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="editUniversity">Üniversite</label>
                                <select class="form-select" id="editUniversity" name="university_id" data-level="university" data-selected="<?= htmlspecialchars($formValues['university_id'], ENT_QUOTES, 'UTF-8') ?>" data-placeholder="Üniversite seç"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="editDepartmentType">Program Türü</label>
                                <select class="form-select" id="editDepartmentType" name="department_type" data-level="department-type" data-selected="<?= htmlspecialchars($formValues['department_type'], ENT_QUOTES, 'UTF-8') ?>" data-placeholder="Program türü seç"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="editDepartment">Bölüm</label>
                                <select class="form-select" id="editDepartment" name="department_id" data-level="department" data-selected="<?= htmlspecialchars($formValues['department_id'], ENT_QUOTES, 'UTF-8') ?>" data-placeholder="Bölüm seç (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="editClass">Sınıf</label>
                                <select class="form-select" id="editClass" name="class_id" data-level="class" data-selected="<?= htmlspecialchars($formValues['class_id'], ENT_QUOTES, 'UTF-8') ?>" data-placeholder="Sınıf seç (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="editCourse">Ders</label>
                                <input class="form-control" id="editCourse" name="course" value="<?= htmlspecialchars($formValues['course'], ENT_QUOTES, 'UTF-8') ?>" maxlength="150" data-level="course-input" list="uploadCourseList" required>
                                <datalist id="uploadCourseList"></datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="editTopic">Konu</label>
                                <input class="form-control" id="editTopic" name="topic" value="<?= htmlspecialchars($formValues['topic'], ENT_QUOTES, 'UTF-8') ?>" maxlength="150" data-level="topic-input" list="uploadTopicList">
                                <datalist id="uploadTopicList"></datalist>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="editTagField">Etiketler</label>
                                <div class="tag-input-shell" data-tag-input>
                                    <div class="tag-chips" data-tag-chips></div>
                                    <input class="form-control" id="editTagField" type="text" data-tag-field placeholder="Etiket yaz, Enter ile ekle">
                                    <input type="hidden" name="tags" data-tag-hidden value="<?= htmlspecialchars($formValues['tags'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <button class="btn btn-primary" type="submit">Değişiklikleri Kaydet</button>
                            <a class="btn btn-outline-secondary" href="profile.php#<?= !empty($note['deleted_at']) ? 'archived' : 'notes' ?>">Vazgeç</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<script>window.NOTBUL_NOTES = <?= json_encode($notesPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
