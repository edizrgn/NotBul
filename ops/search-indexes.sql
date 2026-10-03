-- MariaDB: mevcut arama kolonları için; verileri veya tablo şemasını yeniden oluşturmaz.
CREATE INDEX IF NOT EXISTS idx_notes_public_latest ON notes(upload_status, scan_status, deleted_at, created_at, id);
CREATE INDEX IF NOT EXISTS idx_notes_public_downloads ON notes(upload_status, scan_status, deleted_at, download_count, created_at, id);
CREATE INDEX IF NOT EXISTS idx_notes_public_hierarchy ON notes(upload_status, scan_status, deleted_at, university_id, department_type, department_id, class_id);
CREATE INDEX IF NOT EXISTS idx_note_comments_note_user_latest ON note_comments(note_id, user_id, id);
