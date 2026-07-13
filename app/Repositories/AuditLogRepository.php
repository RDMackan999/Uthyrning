<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\AuditLog;
use PDO;

/**
 * Repository for append-only audit log records.
 */
final class AuditLogRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(AuditLog::class);
    }

    /**
     * Append one audit log entry.
     *
     * @param array<string, mixed> $context
     */
    public function append(
        string $eventName,
        ?int $actorUserId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $context = []
    ): AuditLog {
        $contextJson = $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($contextJson === false) {
            $contextJson = '{}';
        }

        $statement = Database::pdo()->prepare(
            'INSERT INTO audit_logs (
                event_name,
                actor_user_id,
                subject_type,
                subject_id,
                ip_address,
                user_agent,
                context_json,
                occurred_at
            ) VALUES (
                :event_name,
                :actor_user_id,
                :subject_type,
                :subject_id,
                :ip_address,
                :user_agent,
                :context_json,
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'event_name' => $eventName,
            'actor_user_id' => $actorUserId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'context_json' => $contextJson,
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Find an audit log entry by primary key.
     */
    public function findById(int|string $id): AuditLog
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM audit_logs WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Audit log not found.');
        }

        return new AuditLog($row);
    }

    /**
     * Find recent audit events for one actor.
     *
     * @return Collection<AuditLog>
     */
    public function findRecentByUserId(int $userId, int $limit = 50): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM audit_logs
             WHERE actor_user_id = :actor_user_id
             ORDER BY occurred_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue('actor_user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('limit', $this->safeLimit($limit), PDO::PARAM_INT);
        $statement->execute();

        return $this->collectionFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find recent audit events by event name.
     *
     * @return Collection<AuditLog>
     */
    public function findRecentByEventName(string $eventName, int $limit = 50): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM audit_logs
             WHERE event_name = :event_name
             ORDER BY occurred_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue('event_name', $eventName);
        $statement->bindValue('limit', $this->safeLimit($limit), PDO::PARAM_INT);
        $statement->execute();

        return $this->collectionFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<AuditLog>
     */
    private function collectionFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): AuditLog => new AuditLog($row),
            $rows
        ));
    }

    /**
     * Keep read queries bounded.
     */
    private function safeLimit(int $limit): int
    {
        return max(1, min(200, $limit));
    }
}
