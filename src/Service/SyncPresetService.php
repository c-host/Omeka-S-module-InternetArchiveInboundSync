<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Doctrine\DBAL\Connection;

/**
 * Import presets (read-only in admin UI; no separate Presets management page).
 */
class SyncPresetService
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function ensureTable(): void
    {
        $table = ModuleSettings::TABLE_SYNC_PRESET;
        $this->connection->exec(<<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id INT AUTO_INCREMENT NOT NULL,
    label VARCHAR(190) NOT NULL,
    settings LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
    UNIQUE INDEX UNIQ_ia_inbound_sync_preset_label (label),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
SQL);
    }

    public function seedDefaults(): void
    {
        $this->ensureTable();
        $table = ModuleSettings::TABLE_SYNC_PRESET;
        $defaults = [
            [
                'label' => 'bilingual (ka+en)',
                'settings' => [
                    'metadata_label_mode' => ModuleSettings::MODE_BILINGUAL,
                    'sync_mode' => 'create_only',
                ],
            ],
            [
                'label' => 'Single-language archive',
                'settings' => [
                    'metadata_label_mode' => ModuleSettings::MODE_SINGLE,
                    'sync_mode' => 'create_only',
                ],
            ],
        ];
        foreach ($defaults as $preset) {
            $exists = $this->connection->fetchOne(
                'SELECT id FROM ' . $table . ' WHERE label = ?',
                [$preset['label']]
            );
            if ($exists) {
                continue;
            }
            $this->connection->insert($table, [
                'label' => $preset['label'],
                'settings' => json_encode($preset['settings'], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPresets(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $table = ModuleSettings::TABLE_SYNC_PRESET;
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . $table . ' ORDER BY label ASC'
        );
        foreach ($rows as &$row) {
            $row['settings'] = json_decode((string) $row['settings'], true) ?: [];
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPreset(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $table = ModuleSettings::TABLE_SYNC_PRESET;
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . $table . ' WHERE id = ?',
            [$id]
        );
        if (!$row) {
            return null;
        }
        $row['settings'] = json_decode((string) $row['settings'], true) ?: [];

        return $row;
    }

    protected function tableExists(): bool
    {
        return (bool) $this->connection->fetchOne(
            "SHOW TABLES LIKE '" . ModuleSettings::TABLE_SYNC_PRESET . "'"
        );
    }
}
