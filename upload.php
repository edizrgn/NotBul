<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/upload_config.php';
require_once __DIR__ . '/includes/admin_notifications.php';
require_once __DIR__ . '/includes/note_form.php';
require_once __DIR__ . '/includes/auth_redirect.php';
@session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . authLoginUrl('upload.php'));
    exit;
}

$error = '';
$formValues = noteFormValues([]);
$uploadToken = (string)($_SESSION['csrf_token_upload'] ?? '');
if ($uploadToken === '') {
    $uploadToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_upload'] = $uploadToken;
}
$maxUploadMb = getMaxUploadMb();
$maxUploadBytes = getMaxUploadBytes();

$uploadErrorMessage = static function (int $errorCode, int $maxMb): string {
    return match ($errorCode) {
        UPLOAD_ERR_OK => '',
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Dosya boyutu {$maxMb} MB sınırını aşıyor.",
        UPLOAD_ERR_PARTIAL => 'Dosya yüklemesi tamamlanamadı. Lütfen tekrar deneyin.',
        UPLOAD_ERR_NO_FILE => 'Lütfen bir dosya seçin.',
        UPLOAD_ERR_NO_TMP_DIR => 'Sunucuda geçici yükleme klasörü bulunamadı.',
        UPLOAD_ERR_CANT_WRITE => 'Dosya sunucuya yazılamadı. Lütfen daha sonra tekrar deneyin.',
        UPLOAD_ERR_EXTENSION => 'Yükleme güvenlik nedeniyle engellendi.',
        default => 'Dosya yüklenirken beklenmeyen bir hata oluştu.'
    };
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $formValues = noteFormValues($_POST);
    $bindings = noteFormBindings($formValues);
    $title = $bindings['title'];
    $description = $bindings['description'];
    $universityId = $bindings['university_id'];
    $departmentType = $bindings['department_type'];
    $departmentId = $bindings['department_id'];
    $classId = $bindings['class_id'];
    $course = $bindings['course'];
    $topic = $bindings['topic'];
    $tags = $bindings['tags'];
    $formErrors = noteFormErrors($formValues);
    $requestToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';

    if ($requestToken === '' || !hash_equals($uploadToken, $requestToken)) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Bilgileriniz korundu; dosyayı yeniden seçip tekrar deneyin.';
    } elseif ($formErrors !== []) {
        $error = implode(' ', $formErrors);
    } elseif (!isset($_FILES['note_file'])) {
        $error = 'Dosya bilgisi alınamadı. Lütfen dosyayı tekrar seçin.';
    } else {
        $file = $_FILES['note_file'];
        $uploadErrorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErrorCode !== UPLOAD_ERR_OK) {
            $error = $uploadErrorMessage($uploadErrorCode, $maxUploadMb);
        }

        $maxSize = $maxUploadBytes;

        $allowedMimeTypes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/png',
            'image/jpeg',
            'image/webp'
        ];
        $allowedExtensions = ['pdf', 'docx', 'pptx', 'png', 'jpg', 'jpeg', 'webp'];

        $originalFilename = (string)($file['name'] ?? '');
        $fileSize = (int)($file['size'] ?? 0);
        $tmpName = (string)($file['tmp_name'] ?? '');

        if (!$error && !is_uploaded_file($tmpName)) {
            $error = 'Yüklenen dosya doğrulanamadı. Lütfen tekrar deneyin.';
        }

        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $mimeType = '';
        if (!$error) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = (string)finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }

        if (!$error && $fileSize > $maxSize) {
            $error = 'Dosya boyutu ' . $maxUploadMb . ' MB sınırını aşıyor.';
        } elseif (!$error && (!in_array($ext, $allowedExtensions, true) || !in_array($mimeType, $allowedMimeTypes, true))) {
            $error = 'Desteklenmeyen dosya formatı.';
        } elseif (!$error) {
            $storageRoot = rtrim(getNoteStorageDir(), "/\\") . DIRECTORY_SEPARATOR;
            $relativeDir = date('Y/m');
            $targetDir = $storageRoot . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir) . DIRECTORY_SEPARATOR;
            $sha256 = hash_file('sha256', $tmpName);

            if ($sha256 === false) {
                $error = 'Dosya bütünlük özeti hesaplanamadı.';
            }

            if (!$error && !is_dir($storageRoot)) {
                if (!mkdir($storageRoot, 0750, true) && !is_dir($storageRoot)) {
                    $error = 'Dosya saklama klasörü oluşturulamadı. Sunucu izinlerini kontrol edin.';
                } else {
                    @file_put_contents($storageRoot . '.htaccess', "Deny from all\n");
                }
            }

            if (!$error && !is_dir($targetDir)) {
                if (!mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
                    $error = 'Yükleme alt klasörü oluşturulamadı. Sunucu izinlerini kontrol edin.';
                }
            }

            if (!$error) {
                try {
                    $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
                } catch (Throwable $e) {
                    $storedFilename = md5(uniqid('nb_', true)) . '.' . $ext;
                }
                $storagePath = $relativeDir . '/' . $storedFilename;
                $destination = $targetDir . $storedFilename;
            }

            if (!$error && move_uploaded_file($tmpName, $destination)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO notes (
                            user_id, title, description, university_id, department_type, department_id,
                            class_id, course, topic, tags, original_filename, stored_filename, storage_disk,
                            storage_path, sha256, file_size, mime_type, upload_status, scan_status
                        ) VALUES (
                            :user_id, :title, :description, :university_id, :department_type, :department_id,
                            :class_id, :course, :topic, :tags, :original_filename, :stored_filename, :storage_disk,
                            :storage_path, :sha256, :file_size, :mime_type, :upload_status, :scan_status
                        )
                    ");

                    $result = $stmt->execute([
                        'user_id' => $userId,
                        'title' => $title,
                        'description' => $description,
                        'university_id' => $universityId,
                        'department_type' => $departmentType,
                        'department_id' => $departmentId,
                        'class_id' => $classId,
                        'course' => $course,
                        'topic' => $topic,
                        'tags' => $tags,
                        'original_filename' => $originalFilename,
                        'stored_filename' => $storedFilename,
                        'storage_disk' => 'local',
                        'storage_path' => $storagePath,
                        'sha256' => $sha256,
                        'file_size' => $fileSize,
                        'mime_type' => $mimeType,
                        'upload_status' => 'ready',
                        'scan_status' => 'clean'
                    ]);
                } catch (Throwable $e) {
                    error_log('upload note save error: ' . $e->getMessage());
                    $result = false;
                }

                if ($result) {
                    $noteId = (int)$pdo->lastInsertId();
                    try {
                        $uploaderStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = :id LIMIT 1");
                        $uploaderStmt->execute(['id' => $userId]);
                        $uploader = $uploaderStmt->fetch() ?: [
                            'id' => $userId,
                            'first_name' => (string)($_SESSION['first_name'] ?? ''),
                            'last_name' => (string)($_SESSION['last_name'] ?? ''),
                            'email' => '',
                        ];

                        sendAdminNotification($pdo, 'Yeni not yüklendi', 'Bir kullanıcı yeni bir ders notu yükledi.', [
                            'Not' => $title . ' (#' . $noteId . ')',
                            'Yükleyen' => adminNotificationUserLabel($uploader) . ' (#' . (int)$uploader['id'] . ')',
                            'Ders' => $course,
                            'Konu' => $topic !== '' ? $topic : '-',
                            'Üniversite ID' => $universityId ?? '-',
                            'Bölüm ID' => $departmentId ?? '-',
                            'Dosya' => $originalFilename,
                            'Dosya boyutu' => number_format($fileSize, 0, ',', '.') . ' B',
                            'MIME type' => $mimeType,
                            'SHA-256' => $sha256,
                        ], [
                            'Notu Gör' => adminNotificationUrl('note-detail.php?id=' . $noteId),
                            'Not Yönetimi' => adminNotificationUrl('admin.php#notes'),
                        ]);
                    } catch (Throwable $e) {
                        error_log('upload admin notification prep error: ' . $e->getMessage());
                    }

                    header('Location: note-detail.php?id=' . $noteId . '&uploaded=1', true, 303);
                    exit;
                } else {
                    $error = 'Veritabanına kaydedilirken bir hata oluştu.';
                    if (file_exists($destination)) {
                        @unlink($destination);
                    }
                }
            } elseif (!$error) {
                $error = 'Dosya sunucuya taşınırken bir hata oluştu.';
            }
        }
    }
}

$notesPayload = noteFormSuggestions($pdo);
$pageTitle = 'Not Bul | Not Yükle';
$pageKey = 'upload';
require __DIR__ . '/includes/header.php';
?>
<main id="mainContent" class="page-shell" tabindex="-1">
    <section class="container section-block">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="panel-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="section-title mb-1">Not Yükleme</h1>
                            <p class="mb-0 text-secondary">Ders notunu güvenli şekilde yükle, gerekli alanları doldur ve paylaş.</p>
                        </div>
                    </div>

                    <form id="uploadForm" class="mt-4" data-hierarchy-group data-filter-source="public" data-max-upload-mb="<?= (int)$maxUploadMb ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($uploadToken, ENT_QUOTES, 'UTF-8') ?>">
                        <div id="dropZone" class="drop-zone">
                            <input id="noteFile" name="note_file" type="file" accept=".pdf,.docx,.pptx,.png,.jpg,.jpeg,.webp" hidden>
                            <p class="drop-title mb-2">Dosyanı buraya bırak veya cihazından seç</p>
                            <p class="mb-3 text-secondary">Desteklenen türler: PDF, DOCX, PPTX, PNG, JPG, WEBP • En fazla <?= (int)$maxUploadMb ?> MB</p>
                            <button class="btn btn-primary" type="button" id="pickFileButton">Dosya Seç</button>
                            <div id="fileList" class="file-list mt-3"></div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger mt-3" role="alert"><?= htmlspecialchars($error) ?></div>
                            <p class="form-text">Girdiğiniz bilgiler korundu. Dosyanızı yeniden seçmeniz gerekiyor.</p>
                        <?php endif; ?>

                        <div id="uploadNotice" class="alert mt-3 d-none" role="alert"></div>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="uploadTitle">Başlık</label>
                                <input class="form-control" id="uploadTitle" name="title" value="<?= htmlspecialchars($formValues['title'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="160" placeholder="Örn: Veri Yapıları Final Özet Notları">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="uploadDescription">Açıklama</label>
                                <textarea class="form-control" id="uploadDescription" name="description" rows="4" maxlength="1000" placeholder="Notun içeriğini, kapsamını ve hangi sınavlar için uygun olduğunu yaz."><?= htmlspecialchars($formValues['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="uploadUniversity">Üniversite</label>
                                <select class="form-select" id="uploadUniversity" name="university_id" data-level="university" data-selected="<?= htmlspecialchars($formValues['university_id'], ENT_QUOTES, 'UTF-8') ?>" data-placeholder="Üniversite seç"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadDepartmentType">Program Türü</label>
                                <select class="form-select" id="uploadDepartmentType" name="department_type" data-level="department-type" data-selected="<?= htmlspecialchars($formValues['department_type'], ENT_QUOTES, 'UTF-8') ?>" data-placeholder="Program türü seç"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadDepartment">Bölüm</label>
                                <select class="form-select" id="uploadDepartment" name="department_id" data-level="department" data-selected="<?= htmlspecialchars($formValues['department_id'], ENT_QUOTES, 'UTF-8') ?>" data-placeholder="Bölüm seç (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadClass">Sınıf</label>
                                <select class="form-select" id="uploadClass" name="class_id" data-level="class" data-selected="<?= htmlspecialchars($formValues['class_id'], ENT_QUOTES, 'UTF-8') ?>" data-placeholder="Sınıf seç (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadCourse">Ders</label>
                                <input class="form-control" id="uploadCourse" name="course" value="<?= htmlspecialchars($formValues['course'], ENT_QUOTES, 'UTF-8') ?>" maxlength="150" data-level="course-input" list="uploadCourseList" placeholder="Dersi yaz veya önerilerden seç" required>
                                <datalist id="uploadCourseList"></datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadTopic">Konu</label>
                                <input class="form-control" id="uploadTopic" name="topic" value="<?= htmlspecialchars($formValues['topic'], ENT_QUOTES, 'UTF-8') ?>" maxlength="150" data-level="topic-input" list="uploadTopicList" placeholder="Konu yaz veya önerilerden seç (opsiyonel)">
                                <datalist id="uploadTopicList"></datalist>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="uploadTagField">Etiketler</label>
                                <div class="tag-input-shell" data-tag-input>
                                    <div class="tag-chips" data-tag-chips></div>
                                    <input class="form-control" id="uploadTagField" type="text" data-tag-field placeholder="Etiket yaz, Enter ile ekle (örn: final, çıkmış-soru)">
                                    <input type="hidden" name="tags" data-tag-hidden value="<?= htmlspecialchars($formValues['tags'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-lg btn-primary px-4" type="submit">Dosyayı Yükle</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <aside class="panel-card sticky-panel">
                    <h2 class="h5">Yükleme Bilgilendirmesi</h2>
                    <ul class="security-list mb-0">
                        <li><?= (int)$maxUploadMb ?> MB üstündeki dosyalar kabul edilmez.</li>
                        <li>Dosya isimi herkes tarafından görülebilir.</li>
                        <li>Girilen metinler güvenli filtrelerden geçirilir.</li>
                    </ul>
                </aside>
            </div>
        </div>
    </section>
</main>
<script>window.NOTBUL_NOTES = <?= json_encode($notesPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
