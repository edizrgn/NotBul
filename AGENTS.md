lg# AGENTS.md

## Must-follow constraints

- New PHP logic files must start with `declare(strict_types=1);`.
- Page files (`index.php`, `search.php`, `upload.php`, `login.php`,
  `register.php`, `note-detail.php`) must define `$pageTitle` and
  `$pageKey` before including `includes/header.php`, and must include
  `includes/footer.php` at the end.
- Keep the auth contract aligned with `users` in `database.sql`:
  `email` is unique, passwords are stored with
  `password_hash(..., PASSWORD_DEFAULT)`, and verified with
  `password_verify`.
- Database access must stay centralized through PDO in `includes/db.php`;
  do not add duplicate connection code or credential copies.
- When rendering user-provided or dynamic text in PHP, use
  `htmlspecialchars`.
- After `header('Location: ...')`, always call `exit;` and do not emit
  output before redirects.

## Validation before finishing

- `Get-ChildItem -Filter *.php -Recurse | ForEach-Object {`
  `php -l $_.FullName }`
- For changes affecting filter/upload flows, verify in browser that
  hierarchical selections still work on `index.php`, `search.php`, and
  `upload.php`.

## Repo-specific conventions

- Notes are backend-persisted via `notes` table in `database.sql`.
- Filter hierarchy wiring depends on exact attribute names; do not rename
  `data-hierarchy-group`, `data-filter-source`, or `data-level`.
- Public filter data comes from `assets/data/universiteler.json` and
  `assets/data/bolumler.json`.
- Upload validation limits are fixed on frontend: 25 MB and
  `pdf/docx/pptx/png/jpg/jpeg/webp`. Backend doğrulamaları bu kurallarla
  senkron kalmalıdır.

## Important locations

- `includes/header.php`: session start and navbar auth state.
- `includes/db.php`: single DB connection point.
- `assets/js/app.js`: filter hierarchy, search, and upload form UX logic.
- `database.sql`: current persistent schema (`users`, `notes`).

## Change safety rules

- Preserve the current plain PHP page-based structure unless explicitly
  asked to refactor.
- Do not rename form/filter field names (`first_name`, `last_name`,
  `email`, `password`, `password_confirm`, `university_id`,
  `department_type`, `department_id`, `class_id`, `course`, `topic`,
  `file_type`); JS/PHP flows depend on them.
