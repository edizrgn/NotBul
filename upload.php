<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
@session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $universityId = $_POST['university_id'] ?? null;
    $departmentType = $_POST['department_type'] ?? null;
    $departmentId = $_POST['department_id'] ?? null;
    $classId = $_POST['class_id'] ?? null;
    $course = trim($_POST['course'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    
    // empty values should be null to avoid foreign key issues or empty strings
    if ($universityId === '') $universityId = null;
    if ($departmentType === '') $departmentType = null;
    if ($departmentId === '') $departmentId = null;
    if ($classId === '') $classId = null;
    
    if (empty($title) || empty($course)) {
        $error = 'Başlık ve Ders alanları zorunludur.';
    } elseif (!isset($_FILES['note_file']) || $_FILES['note_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Geçerli bir dosya seçilmedi veya yükleme hatası oluştu.';
    } else {
        $file = $_FILES['note_file'];
        $maxSize = 25 * 1024 * 1024; // 25 MB
        
        $allowedMimeTypes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/png',
            'image/jpeg',
            'image/webp'
        ];
        $allowedExtensions = ['pdf', 'docx', 'pptx', 'png', 'jpg', 'jpeg', 'webp'];
        
        $originalFilename = $file['name'];
        $fileSize = $file['size'];
        $tmpName = $file['tmp_name'];
        
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        
        if ($fileSize > $maxSize) {
            $error = 'Dosya boyutu 25 MB sınırını aşıyor.';
        } elseif (!in_array($ext, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
            $error = 'Desteklenmeyen dosya formatı.';
        } else {
            $uploadDir = __DIR__ . '/storage/notes/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
                file_put_contents($uploadDir . '.htaccess', "Deny from all\n");
            }
            
            $storedFilename = md5(uniqid('nb_', true)) . '.' . $ext;
            $destination = $uploadDir . $storedFilename;
            
            if (move_uploaded_file($tmpName, $destination)) {
                $stmt = $pdo->prepare("
                    INSERT INTO notes (
                        user_id, title, description, university_id, department_type, department_id, 
                        class_id, course, topic, tags, original_filename, stored_filename, file_size, mime_type
                    ) VALUES (
                        :user_id, :title, :description, :university_id, :department_type, :department_id,
                        :class_id, :course, :topic, :tags, :original_filename, :stored_filename, :file_size, :mime_type
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
                    'file_size' => $fileSize,
                    'mime_type' => $mimeType
                ]);
                
                if ($result) {
                    $success = 'Notunuz başarıyla yüklendi.';
                } else {
                    $error = 'Veritabanına kaydedilirken bir hata oluştu.';
                    if (file_exists($destination)) {
                        unlink($destination); // sil
                    }
                }
            } else {
                $error = 'Dosya sunucuya taşınırken bir hata oluştu.';
            }
        }
    }
}

$pageTitle = 'Not Bul | Not Yükle';
$pageKey = 'upload';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="container section-block">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="panel-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="section-title mb-1">Not Yükleme</h1>
                            <p class="mb-0 text-secondary">Not Bul üzerinde ders notunu güvenli şekilde yükle, hiyerarşiyi seç ve doğru öğrenci kitlesine ulaştır.</p>
                        </div>
                        <span class="badge bg-soft-info text-primary-emphasis">Frontend prototipi</span>
                    </div>

                    <form id="uploadForm" class="mt-4" data-hierarchy-group data-filter-source="public" method="POST" enctype="multipart/form-data">
                        <div id="dropZone" class="drop-zone">
                            <input id="noteFile" name="note_file" type="file" accept=".pdf,.docx,.pptx,.png,.jpg,.jpeg,.webp" hidden>
                            <p class="drop-title mb-2">Dosyayı sürükle bırak veya seç</p>
                            <p class="mb-3 text-secondary">Desteklenen türler: PDF, DOCX, PPTX, PNG, JPG, WEBP | Maksimum 25 MB</p>
                            <button class="btn btn-primary" type="button" id="pickFileButton">Dosya Seç</button>
                            <div id="fileList" class="file-list mt-3"></div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger mt-3" role="alert"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success mt-3" role="alert"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <div id="uploadNotice" class="alert mt-3 d-none" role="alert"></div>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="uploadTitle">Başlık</label>
                                <input class="form-control" id="uploadTitle" name="title" required maxlength="160" placeholder="Örn: Veri Yapıları Final Özet Notları">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="uploadDescription">Açıklama</label>
                                <textarea class="form-control" id="uploadDescription" name="description" rows="4" maxlength="1000" placeholder="Notun içeriğini, kapsamını ve hangi sınavlar için uygun olduğunu yaz."></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="uploadUniversity">Üniversite</label>
                                <select class="form-select" id="uploadUniversity" name="university_id" data-level="university" data-placeholder="Üniversite seç"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadDepartmentType">Program Türü</label>
                                <select class="form-select" id="uploadDepartmentType" name="department_type" data-level="department-type" data-placeholder="Program türü seç"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadDepartment">Bölüm</label>
                                <select class="form-select" id="uploadDepartment" name="department_id" data-level="department" data-placeholder="Bölüm seç (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadClass">Sınıf</label>
                                <select class="form-select" id="uploadClass" name="class_id" data-level="class" data-placeholder="Sınıf seç (opsiyonel)"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadCourse">Ders</label>
                                <input class="form-control" id="uploadCourse" name="course" data-level="course-input" list="uploadCourseList" placeholder="Dersi yaz veya önerilerden seç" required>
                                <datalist id="uploadCourseList"></datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="uploadTopic">Konu</label>
                                <input class="form-control" id="uploadTopic" name="topic" data-level="topic-input" list="uploadTopicList" placeholder="Konu yaz veya önerilerden seç (opsiyonel)">
                                <datalist id="uploadTopicList"></datalist>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Etiketler</label>
                                <div class="tag-input-shell" data-tag-input>
                                    <div class="tag-chips" data-tag-chips></div>
                                    <input class="form-control" type="text" data-tag-field placeholder="Etiket yaz, Enter ile ekle (örn: final, çıkmış-soru)">
                                    <input type="hidden" name="tags" data-tag-hidden>
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
                    <h2 class="h5">Güvenlik Kontrol Listesi</h2>
                    <ul class="security-list mb-0">
                        <li>MIME-type ve dosya uzantısı backend tarafında yeniden doğrulanacak.</li>
                        <li>Maksimum dosya boyutu limitini aşan yüklemeler reddedilecek.</li>
                        <li>Gerçek dosya adı yerine benzersiz hash tabanlı adlandırma kullanılacak.</li>
                        <li>Dosyalar web root dışında saklanıp PHP ile stream edilecek.</li>
                        <li>Tüm metin verileri çıkışta `htmlspecialchars` ile filtrelenecek.</li>
                    </ul>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

