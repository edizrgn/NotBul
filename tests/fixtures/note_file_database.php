<?php
declare(strict_types=1);

// HTTP testleri için izole veri kaynağı; gerçek veritabanına bağlanmaz.
final class NoteFileTestPDO extends PDO
{
    private array $state;
    private ?array $before = null;

    public function __construct(private string $path)
    {
        $this->state = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new NoteFileTestStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $stmt = $this->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function beginTransaction(): bool
    {
        if ($this->before !== null) {
            throw new RuntimeException('Nested test transaction.');
        }
        $this->before = $this->state;
        return true;
    }

    public function inTransaction(): bool { return $this->before !== null; }
    public function rollBack(): bool { $this->state = $this->before ?? $this->state; $this->before = null; return true; }

    public function commit(): bool
    {
        if (!empty($this->state['fail_commit'])) {
            throw new RuntimeException('Injected commit failure.');
        }
        file_put_contents($this->path, json_encode($this->state, JSON_THROW_ON_ERROR));
        $this->before = null;
        return true;
    }

    public function run(string $query, array $parameters): array
    {
        $sql = preg_replace('/\s+/', ' ', trim($query));
        if (str_starts_with($sql, 'SELECT role FROM users')) {
            $user = $this->state['users'][$parameters['id']] ?? null;
            return $user ? [['role' => $user['role']]] : [];
        }
        if (str_starts_with($sql, 'SELECT COUNT(*)')) {
            return [['count' => str_contains($sql, 'FROM users') ? 1 : 0]];
        }
        if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'FROM users WHERE id')) {
            $user = $this->state['users'][$parameters['id']] ?? null;
            return $user ? [$user] : [];
        }
        if (str_starts_with($sql, 'SELECT DISTINCT')) {
            return [];
        }
        if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'FROM notes')) {
            if (isset($parameters['uid'])) {
                return array_values(array_filter($this->state['notes'], static fn($note) => $note['user_id'] === $parameters['uid']));
            }
            $note = $this->state['notes'][$parameters['id']] ?? null;
            if (!$note || (isset($parameters['user_id']) && $note['user_id'] !== $parameters['user_id'])) {
                return [];
            }
            if (str_contains($sql, 'JOIN users viewer')) {
                $viewer = $this->state['users'][$parameters['actor_id']] ?? null;
                if (!$viewer || ($viewer['role'] !== 'admin' && $note['user_id'] !== $parameters['owner_id'])) {
                    return [];
                }
            } elseif (str_contains($sql, 'JOIN users u')) {
                $note += $this->state['users'][$note['user_id']];
            }
            if (str_contains($sql, "upload_status = 'ready'")
                && ($note['upload_status'] !== 'ready' || $note['scan_status'] !== 'clean' || $note['deleted_at'] !== null)) {
                return [];
            }
            return [$note];
        }
        if (str_starts_with($sql, 'UPDATE notes')) {
            if (!empty($this->state['fail_update'])) {
                throw new RuntimeException('Injected update failure.');
            }
            $id = $parameters['id'];
            if (isset($parameters['user_id']) && $this->state['notes'][$id]['user_id'] !== $parameters['user_id']) {
                return [];
            }
            if (str_contains($sql, 'download_count = download_count + 1')) {
                $this->state['notes'][$id]['download_count']++;
            } else {
                foreach ($parameters as $key => $value) {
                    if ($key !== 'id' && $key !== 'user_id') {
                        $this->state['notes'][$id][$key] = $value;
                    }
                }
            }
            $this->state['notes'][$id]['updated_at'] = date('Y-m-d H:i:s');
            if (!$this->inTransaction()) {
                file_put_contents($this->path, json_encode($this->state, JSON_THROW_ON_ERROR));
            }
            return [];
        }
        if (str_starts_with($sql, 'DELETE FROM notes') || str_starts_with($sql, 'DELETE FROM users')) {
            $id = $parameters['id'] ?? $parameters['uid'];
            if (str_contains($sql, 'FROM notes')) {
                $deleted = isset($this->state['notes'][$id]);
                unset($this->state['notes'][$id]);
            } else {
                $deleted = isset($this->state['users'][$id]);
                unset($this->state['users'][$id]);
                $this->state['notes'] = array_filter($this->state['notes'], static fn($note) => $note['user_id'] !== $id);
            }
            if (!$this->inTransaction()) {
                file_put_contents($this->path, json_encode($this->state, JSON_THROW_ON_ERROR));
            }
            return $deleted ? [['deleted' => 1]] : [];
        }
        throw new RuntimeException('Unexpected test SQL: ' . $sql);
    }
}

final class NoteFileTestStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private NoteFileTestPDO $database, private string $sql) {}
    public function execute(?array $params = null): bool { $this->rows = $this->database->run($this->sql, $params ?? []); return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?: false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { $row = $this->fetch(); return $row ? array_values($row)[$column] : false; }
    public function rowCount(): int { return count($this->rows); }
}
