<?php
declare(strict_types=1);

require_once __DIR__ . '/ratings.php';

function noteSearchEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function noteSearchNormalize(string $value): string
{
    $value = mb_strtolower(str_replace('İ', 'i', $value), 'UTF-8');
    return strtr($value, ['ı' => 'i', 'ç' => 'c', 'ğ' => 'g', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u']);
}

function noteSearchRequest(array $source): array
{
    $value = static fn(string $name): string => is_scalar($source[$name] ?? null) ? trim((string)$source[$name]) : '';
    $filters = [];
    foreach (['university_id', 'department_type', 'department_id', 'class_id', 'course', 'topic', 'file_type'] as $field) {
        $filters[$field] = mb_substr($value($field), 0, 150, 'UTF-8');
    }
    $sort = $value('sort');
    $page = filter_var($value('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $query = $value('q');
    if ($query === '') {
        $query = $value('query');
    }
    if (mb_strlen($query, 'UTF-8') > 200) {
        throw new InvalidArgumentException('Arama metni en fazla 200 karakter olmalı.');
    }
    $words = array_values(array_unique(preg_split('/\s+/u', noteSearchNormalize($query), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    if (count($words) > 16) {
        throw new InvalidArgumentException('Arama için en fazla 16 kelime kullanın.');
    }
    return ['q' => $query, 'words' => $words, 'filters' => $filters,
        'sort' => in_array($sort, ['relevance', 'newest', 'rating', 'downloads'], true) ? $sort : 'relevance',
        'page' => $page === false ? 1 : $page, 'similar_to' => max(0, (int)$value('similar_to'))];
}

function noteSearchLookups(): array
{
    static $lookups;
    if ($lookups !== null) {
        return $lookups;
    }
    $lookups = ['university_id' => [], 'department_id' => []];
    $universities = json_decode((string)file_get_contents(__DIR__ . '/../assets/data/universiteler.json'), true) ?: [];
    $departments = json_decode((string)file_get_contents(__DIR__ . '/../assets/data/bolumler.json'), true) ?: [];
    foreach ($universities as $item) {
        $lookups['university_id'][(string)$item['id']] = (string)$item['name'];
    }
    foreach ($departments as $items) {
        foreach ($items as $item) {
            $lookups['department_id'][(string)$item['id']] = (string)$item['name'];
        }
    }
    return $lookups;
}

function noteSearchVisibility(): string
{
    return "n.upload_status = 'ready' AND n.scan_status = 'clean' AND n.deleted_at IS NULL";
}

function noteSearchLike(string $word): string
{
    return '%' . strtr($word, ['=' => '==', '%' => '=%', '_' => '=_']) . '%';
}

function noteSearchText(string $sql): string
{
    return "REPLACE(CONVERT({$sql} USING utf8mb4), 'ı', 'i') COLLATE utf8mb4_unicode_ci";
}

function noteSearchFilters(array $filters, ?array $fields = null): array
{
    $conditions = [noteSearchVisibility()];
    $bindings = [];
    foreach ($fields ?? ['university_id', 'department_type', 'department_id', 'class_id', 'course', 'topic'] as $field) {
        if (($filters[$field] ?? '') !== '') {
            $conditions[] = 'n.' . $field . ' = :' . $field;
            $bindings[$field] = $filters[$field];
        }
    }
    return [implode(' AND ', $conditions), $bindings];
}

function noteSearchPlan(PDO $pdo, array $request): array
{
    [$where, $bindings] = noteSearchFilters($request['filters']);
    $type = $request['filters']['file_type'];
    if ($type !== '') {
        $where .= match ($type) {
            'pdf', 'docx', 'pptx' => ' AND LOWER(SUBSTRING_INDEX(n.original_filename, \'.\', -1)) = :file_type',
            'image' => " AND n.mime_type LIKE 'image/%' AND LOWER(SUBSTRING_INDEX(n.original_filename, '.', -1)) NOT IN ('pdf', 'docx', 'pptx')",
            default => ' AND 1 = 0',
        };
        if (in_array($type, ['pdf', 'docx', 'pptx'], true)) {
            $bindings['file_type'] = $type;
        }
    }
    $lookups = noteSearchLookups();
    $text = noteSearchText("CONCAT_WS(' ', n.title, n.description, n.tags, n.course, n.topic)");
    foreach ($request['words'] as $i => $word) {
        $parts = ["{$text} LIKE :word_{$i} ESCAPE '='"];
        $bindings['word_' . $i] = noteSearchLike($word);
        foreach ($lookups as $field => $items) {
            $parameters = [];
            foreach ($items as $id => $name) {
                if (str_contains(noteSearchNormalize($name), $word)) {
                    $key = $field . '_' . $i . '_' . count($parameters);
                    $parameters[] = ':' . $key;
                    $bindings[$key] = (string)$id;
                }
            }
            if ($parameters !== []) {
                $parts[] = 'n.' . $field . ' IN (' . implode(',', $parameters) . ')';
            }
        }
        $where .= ' AND (' . implode(' OR ', $parts) . ')';
    }
    $similar = null;
    if ($request['similar_to'] > 0) {
        $stmt = $pdo->prepare('SELECT n.id, n.title, n.course, n.tags FROM notes n WHERE n.id = :id AND ' . noteSearchVisibility());
        $stmt->execute(['id' => $request['similar_to']]);
        $similar = $stmt->fetch() ?: null;
    }
    if ($similar === null) {
        $request['similar_to'] = 0;
    }
    $related = [];
    if ($similar !== null) {
        foreach (array_unique(array_filter(array_map('trim', explode(',', (string)$similar['tags'])))) as $tag) {
            $related[] = ["FIND_IN_SET(%s, " . noteSearchText("TRIM(REGEXP_REPLACE(COALESCE(n.tags, ''), '[[:space:]]*,[[:space:]]*', ','))") . ') > 0', noteSearchNormalize($tag), 10];
        }
        $words = array_unique(preg_split('/\s+/u', noteSearchNormalize((string)$similar['title']), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') > 2) {
                $related[] = [noteSearchText("CONCAT(' ', TRIM(REGEXP_REPLACE(n.title, '[[:space:]]+', ' ')), ' ')") . " LIKE %s ESCAPE '='", noteSearchLike(' ' . $word . ' '), 2];
            }
        }
        if (trim((string)$similar['course']) !== '') {
            $related[] = [noteSearchText('n.course') . ' = %s', noteSearchNormalize((string)$similar['course']), 5];
        }
        $parts = [];
        foreach ($related as $i => [$sql, $value]) {
            $parts[] = sprintf($sql, ':related_' . $i);
            $bindings['related_' . $i] = $value;
        }
        $where .= ' AND n.id <> :similar_id AND (' . ($parts === [] ? '1 = 0' : implode(' OR ', $parts)) . ')';
        $bindings['similar_id'] = (int)$similar['id'];
    }
    $score = [];
    $scoreBindings = [];
    if ($request['sort'] === 'relevance') {
        if ($similar !== null) {
            foreach ($related as $i => [$sql, $value, $weight]) {
                $score[] = 'IF(' . sprintf($sql, ':rank_related_' . $i) . ', ' . $weight . ', 0)';
                $scoreBindings['rank_related_' . $i] = $value;
            }
        } else {
            foreach ($request['words'] as $i => $word) {
                foreach (['title' => 5, 'course' => 3, 'tags' => 2] as $field => $weight) {
                    $key = 'rank_' . $field . '_' . $i;
                    $score[] = 'IF(' . noteSearchText('n.' . $field) . " LIKE :{$key} ESCAPE '=', {$weight}, 0)";
                    $scoreBindings[$key] = noteSearchLike($word);
                }
            }
        }
    }
    return ['request' => $request, 'where' => $where, 'bindings' => $bindings,
        'score' => $score === [] ? '0' : implode(' + ', $score), 'score_bindings' => $scoreBindings];
}

function noteSearchColumns(): string
{
    return noteSearchNoteColumns() . ', u.first_name, u.last_name';
}

function noteSearchNoteColumns(): string
{
    return 'n.id, n.user_id, n.title, n.description, n.university_id, n.department_type, n.department_id,
        n.class_id, n.course, n.topic, n.tags, n.original_filename, n.mime_type,
        n.download_count, n.created_at';
}

function noteSearchAttachRatings(PDO $pdo, array $rows): array
{
    if ($rows === []) {
        return [];
    }
    $ratings = [];
    foreach ($pdo->query(noteRatingSummarySql(array_column($rows, 'id')))->fetchAll() as $rating) {
        $ratings[(int)$rating['note_id']] = $rating;
    }
    foreach ($rows as &$row) {
        $rating = $ratings[(int)$row['id']] ?? [];
        $row['rating_average'] = $rating['rating_average'] ?? null;
        $row['rating_count'] = (int)($rating['rating_count'] ?? 0);
    }
    unset($row);
    return $rows;
}

function noteSearchPage(PDO $pdo, array $request, int $pageSize = 10): array
{
    $pageSize = max(1, min(10, $pageSize));
    $plan = noteSearchPlan($pdo, $request);
    $request = $plan['request'];
    $count = $pdo->prepare('SELECT COUNT(*) FROM notes n WHERE ' . $plan['where']);
    $count->execute($plan['bindings']);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $pageSize));
    $request['page'] = min($pages, $request['page']);
    $offset = ($request['page'] - 1) * $pageSize;
    $ratingSort = $request['sort'] === 'rating';
    $ratingJoin = $ratingSort ? ' LEFT JOIN (' . noteRatingSummarySql() . ') rs ON rs.note_id = n.id' : '';
    $extra = $ratingSort ? ', rs.rating_average, COALESCE(rs.rating_count, 0) AS rating_count' : ', ' . $plan['score'] . ' AS relevance_score';
    $order = match ($request['sort']) {
        'downloads' => 'n.download_count DESC, ',
        'rating' => 'COALESCE(rs.rating_average, 0) DESC, COALESCE(rs.rating_count, 0) DESC, ',
        'relevance' => $plan['score'] === '0' ? '' : 'relevance_score DESC, ',
        default => '',
    };
    $outerOrder = match ($request['sort']) {
        'downloads' => 'p.download_count DESC, ',
        'rating' => 'COALESCE(p.rating_average, 0) DESC, p.rating_count DESC, ',
        'relevance' => $plan['score'] === '0' ? '' : 'p.relevance_score DESC, ',
        default => '',
    };
    // LIMIT, kullanıcı bilgileri birleştirilmeden önce uygulanır.
    $stmt = $pdo->prepare('SELECT p.*, u.first_name, u.last_name FROM (SELECT ' . noteSearchNoteColumns() . $extra . ' FROM notes n'
        . $ratingJoin . ' WHERE ' . $plan['where'] . ' ORDER BY ' . $order . 'n.created_at DESC, n.id DESC LIMIT ' . $pageSize . ' OFFSET ' . $offset
        . ') p JOIN users u ON u.id = p.user_id ORDER BY ' . $outerOrder . 'p.created_at DESC, p.id DESC');
    $stmt->execute($plan['bindings'] + ($ratingSort ? [] : $plan['score_bindings']));
    $rows = $stmt->fetchAll();
    if (!$ratingSort) {
        $rows = noteSearchAttachRatings($pdo, $rows);
    }
    return ['request' => $request, 'rows' => $rows, 'total' => $total, 'pages' => $pages, 'page_size' => $pageSize];
}

function noteSearchFeatured(PDO $pdo): array
{
    $select = ' FROM notes n WHERE ' . noteSearchVisibility();
    $rows = $pdo->query('SELECT p.*, u.first_name, u.last_name FROM ((SELECT ' . noteSearchNoteColumns() . ", 'popular' AS bucket" . $select . ' ORDER BY n.download_count DESC, n.created_at DESC, n.id DESC LIMIT 6)'
        . ' UNION ALL (SELECT ' . noteSearchNoteColumns() . ", 'latest' AS bucket" . $select . ' ORDER BY n.created_at DESC, n.id DESC LIMIT 6)) p'
        . " JOIN users u ON u.id = p.user_id ORDER BY p.bucket, IF(p.bucket = 'popular', p.download_count, 0) DESC, p.created_at DESC, p.id DESC")->fetchAll();
    $result = ['popular' => [], 'latest' => []];
    foreach (noteSearchAttachRatings($pdo, $rows) as $row) {
        $result[$row['bucket']][] = $row;
    }
    return $result;
}

function noteSearchOptions(PDO $pdo, array $filters): array
{
    $fields = ['university_id', 'department_type', 'department_id', 'class_id', 'course', 'topic'];
    $lookups = noteSearchLookups();
    $lookups['department_type'] = ['onlisans' => 'Önlisans', 'lisans' => 'Lisans'];
    $lookups['class_id'] = ['1' => '1. Sınıf', '2' => '2. Sınıf', '3' => '3. Sınıf', '4' => '4. Sınıf'];
    $options = [];
    foreach ($fields as $i => $field) {
        [$where, $bindings] = noteSearchFilters($filters, array_slice($fields, 0, $i));
        $stmt = $pdo->prepare('SELECT DISTINCT n.' . $field . ' AS value FROM notes n WHERE ' . $where . ' AND n.' . $field . " IS NOT NULL AND n.{$field} <> '' ORDER BY n.{$field}");
        $stmt->execute($bindings);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $value = (string)$row['value'];
            $items[$value] = $lookups[$field][$value] ?? $value;
        }
        if (($filters[$field] ?? '') !== '' && !isset($items[$filters[$field]])) {
            $items[$filters[$field]] = $lookups[$field][$filters[$field]] ?? $filters[$field];
        }
        if (in_array($field, ['university_id', 'department_id'], true)) {
            uasort($items, static fn(string $a, string $b): int => strcmp(noteSearchNormalize($a), noteSearchNormalize($b)));
        }
        $options[$field] = $items;
    }
    return $options;
}

function noteSearchOptionsHtml(array $items, string $selected, string $placeholder): string
{
    $html = '<option value="">' . noteSearchEscape($placeholder) . '</option>';
    foreach ($items as $value => $label) {
        $value = (string)$value;
        $html .= '<option value="' . noteSearchEscape($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . noteSearchEscape((string)$label) . '</option>';
    }
    return $html;
}

function noteSearchQueryString(array $request, ?int $page = null): string
{
    $parameters = array_filter($request['filters'], static fn($value): bool => $value !== '');
    if ($request['q'] !== '') {
        $parameters['q'] = $request['q'];
    }
    if ($request['sort'] !== 'relevance') {
        $parameters['sort'] = $request['sort'];
    }
    if (($page ?? $request['page']) > 1) {
        $parameters['page'] = $page ?? $request['page'];
    }
    if ($request['similar_to'] > 0) {
        $parameters['similar_to'] = $request['similar_to'];
    }
    return http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

function noteSearchUrl(array $request, ?int $page = null, string $path = 'search.php'): string
{
    $query = noteSearchQueryString($request, $page);
    return $path . ($query === '' ? '' : '?' . $query);
}

function noteSearchPagination(array $result): string
{
    if ($result['pages'] <= 1) {
        return '';
    }
    $current = $result['request']['page'];
    $pages = array_unique(array_filter([1, $result['pages'], $current - 1, $current, $current + 1], static fn($page): bool => $page >= 1 && $page <= $result['pages']));
    sort($pages);
    $link = static function (int $page, string $label, bool $disabled = false) use ($result, $current): string {
        $active = $page === $current && !$disabled;
        $li = '<li class="page-item' . ($active ? ' active' : '') . ($disabled ? ' disabled' : '') . '">';
        return $li . ($disabled ? '<span class="page-link" aria-disabled="true">' . $label . '</span>'
            : '<a class="page-link" data-page="' . $page . '" href="' . noteSearchEscape(noteSearchUrl($result['request'], $page)) . '"'
                . ($active ? ' aria-current="page"' : '') . ' aria-label="' . (ctype_digit($label) ? 'Sayfa ' . $label : $label) . '">' . $label . '</a>') . '</li>';
    };
    $html = $link($current - 1, 'Önceki', $current === 1);
    $previous = 0;
    foreach ($pages as $page) {
        if ($previous > 0 && $page - $previous > 1) {
            $html .= '<li class="page-item disabled"><span class="page-link" aria-hidden="true">…</span></li>';
        }
        $html .= $link($page, (string)$page);
        $previous = $page;
    }
    return $html . $link($current + 1, 'Sonraki', $current === $result['pages']);
}

function noteSearchStatus(array $result): string
{
    $start = ($result['request']['page'] - 1) * $result['page_size'] + 1;
    return $result['rows'] === [] ? 'Sonuç bulunamadı. Daha az kelime veya daha geniş filtrelerle aramayı deneyin.'
        : number_format($result['total'], 0, ',', '.') . ' not bulundu. ' . $start . '–' . ($start + count($result['rows']) - 1) . ' arası gösteriliyor.'
            . ($result['request']['similar_to'] > 0 ? ' Benzer notlar listeleniyor.' : '');
}

function noteSearchFileType(array $note): string
{
    $extension = strtolower(pathinfo((string)$note['original_filename'], PATHINFO_EXTENSION));
    return in_array($extension, ['pdf', 'docx', 'pptx'], true) ? strtoupper($extension) : (str_starts_with((string)$note['mime_type'], 'image/') ? 'Görsel' : 'Dosya');
}

function noteSearchResults(array $result, bool $cards = false, string $returnPath = 'search.php'): string
{
    if ($result['rows'] === []) {
        return '<div class="' . ($cards ? 'col-12' : '') . '"><div class="empty-state">Aramanıza uygun not bulunamadı. Daha az kelime veya daha geniş filtrelerle aramayı deneyin. <a class="btn btn-sm btn-outline-primary mt-2" href="' . $returnPath . '" data-reset-search>Aramayı ve filtreleri temizle</a></div></div>';
    }
    $lookups = noteSearchLookups();
    $returnTo = noteSearchUrl($result['request'], null, $returnPath);
    $html = '';
    foreach ($result['rows'] as $note) {
        $title = noteSearchEscape((string)$note['title']);
        $description = trim((string)($note['description'] ?? ''));
        if ($description === '') {
            $description = 'Açıklama eklenmemiş.';
        }
        $limit = $cards ? 80 : 240;
        $description = noteSearchEscape(mb_substr($description, 0, $limit) . (mb_strlen($description) > $limit ? '…' : ''));
        $url = noteSearchEscape('note-detail.php?id=' . (int)$note['id'] . '&return_to=' . rawurlencode($returnTo));
        $context = array_filter([
            $lookups['university_id'][(string)$note['university_id']] ?? (string)$note['university_id'],
            $lookups['department_id'][(string)$note['department_id']] ?? (string)$note['department_id'],
            (string)$note['course'], $cards ? '' : (string)$note['topic'], noteSearchFileType($note),
        ], static fn(string $value): bool => $value !== '');
        $rating = renderRatingSummary($note['rating_average'] ?? null, (int)($note['rating_count'] ?? 0), !$cards);
        if ($cards) {
            $tags = '';
            foreach (array_slice(array_filter(array_map('trim', explode(',', (string)$note['tags']))), 0, 2) as $tag) {
                $tags .= '<span class="badge bg-light text-secondary fw-normal">#' . noteSearchEscape($tag) . '</span>';
            }
            $html .= '<article class="col-sm-6 col-xl-4"><div class="note-card card shadow-sm border-0"><div class="card-body">'
                . '<h3 class="h6 mb-2">' . $title . '</h3><p class="text-secondary mb-3 small" style="height: 3em; overflow: hidden;">' . $description . '</p>'
                . '<p class="note-card-context small text-secondary mb-2">' . noteSearchEscape(implode(' · ', $context)) . '</p><div class="note-tags mb-3">' . $tags . '</div>'
                . '<div class="note-card-footer d-flex justify-content-between align-items-center gap-3"><div class="small"><div class="fw-bold text-dark">'
                . noteSearchEscape(trim($note['first_name'] . ' ' . $note['last_name'])) . '</div><div class="text-secondary">' . noteSearchEscape((string)$note['course']) . '</div></div>'
                . '<div class="note-card-actions d-flex align-items-center gap-2 ms-auto">' . $rating . '<a class="btn btn-sm btn-primary" href="' . $url . '" aria-label="' . $title . ' notunu incele">Detay</a></div></div></div></div></article>';
        } else {
            $contextHtml = '';
            foreach ($context as $label) {
                $contextHtml .= '<span class="note-tag">' . noteSearchEscape($label) . '</span>';
            }
            $html .= '<article class="result-item"><div class="d-flex justify-content-between align-items-start gap-3"><div class="flex-grow-1" style="min-width: 0"><h3 class="h5 mb-1">'
                . $title . '</h3><p class="mb-2 text-secondary">' . $description . '</p></div><a class="btn btn-sm btn-outline-primary flex-shrink-0" href="' . $url . '" aria-label="' . $title . ' notunu incele">Detay</a></div>'
                . '<div class="result-footer"><div class="d-flex flex-wrap gap-2">' . $contextHtml . '</div><div class="result-stats text-secondary small">' . $rating . '<span>'
                . date('d.m.Y', strtotime((string)$note['created_at'])) . ' · ' . number_format((int)$note['download_count'], 0, ',', '.') . ' indirme</span></div></div></article>';
        }
    }
    return $html;
}
