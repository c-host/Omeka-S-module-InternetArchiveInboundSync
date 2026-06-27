<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Doctrine\DBAL\Connection;

class SyncRunService
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function createRun(?int $jobId, ?int $ownerId, array $parameters): int
    {
        $this->connection->insert(ModuleSettings::TABLE_SYNC_RUN, [
            'job_id' => $jobId,
            'owner_id' => $ownerId,
            'started' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'parameters' => json_encode($parameters, JSON_THROW_ON_ERROR),
            'stats' => json_encode([], JSON_THROW_ON_ERROR),
            'log' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $stats
     */
    public function updateRun(int $runId, array $stats, string $logAppend): void
    {
        $existing = $this->getRun($runId);
        $log = (string) ($existing['log'] ?? '') . $logAppend;
        $this->connection->update(
            ModuleSettings::TABLE_SYNC_RUN,
            [
                'stats' => json_encode($stats, JSON_THROW_ON_ERROR),
                'log' => $log,
            ],
            ['id' => $runId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRun(int $runId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . ModuleSettings::TABLE_SYNC_RUN . ' WHERE id = ?',
            [$runId]
        );
        if (!$row) {
            return null;
        }

        return $this->decodeRow($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRuns(int $limit = 50): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . ModuleSettings::TABLE_SYNC_RUN . ' ORDER BY id DESC LIMIT ' . (int) $limit
        );

        return array_map([$this, 'decodeRow'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function decodeRow(array $row): array
    {
        $row['parameters'] = json_decode((string) ($row['parameters'] ?? ''), true) ?: [];
        $row['stats'] = json_decode((string) ($row['stats'] ?? ''), true) ?: [];

        return $row;
    }
}
