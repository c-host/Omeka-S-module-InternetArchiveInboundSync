<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

/**
 * Per-import run options (job arguments).
 */
class SyncRunOptions
{
    public int $resourceTemplateId;

    public ?int $itemSetId;

    /** @var int[] */
    public array $siteIds;

    public bool $dryRun;

    public string $syncMode;

    public bool $updateMetadata;

    public bool $repairEmbedMedia;

    public function __construct(array $args)
    {
        $this->resourceTemplateId = (int) ($args['resource_template_id'] ?? 0);
        $this->itemSetId = isset($args['item_set_id']) && $args['item_set_id'] !== ''
            ? (int) $args['item_set_id']
            : null;
        $this->siteIds = array_map('intval', $args['site_ids'] ?? []);
        $this->dryRun = !empty($args['dry_run']);
        $this->syncMode = (string) ($args['sync_mode'] ?? 'create_only');
        $this->updateMetadata = !empty($args['update_metadata']);
        $this->repairEmbedMedia = !empty($args['repair_embed_media']);
    }

    public function toArray(): array
    {
        return [
            'resource_template_id' => $this->resourceTemplateId,
            'item_set_id' => $this->itemSetId,
            'site_ids' => $this->siteIds,
            'dry_run' => $this->dryRun,
            'sync_mode' => $this->syncMode,
            'update_metadata' => $this->updateMetadata,
            'repair_embed_media' => $this->repairEmbedMedia,
        ];
    }
}
