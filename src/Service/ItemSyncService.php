<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Omeka\Api\Manager as ApiManager;

class ItemSyncService
{
    protected IaMetadataClient $iaMetadata;

    protected PayloadBuilder $payloadBuilder;

    protected ModuleSettings $settings;

    protected ApiManager $api;

    protected EmbedMediaThumbnailService $embedThumbnail;

    public function __construct(
        IaMetadataClient $iaMetadata,
        PayloadBuilder $payloadBuilder,
        ModuleSettings $settings,
        ApiManager $api,
        EmbedMediaThumbnailService $embedThumbnail
    ) {
        $this->iaMetadata = $iaMetadata;
        $this->payloadBuilder = $payloadBuilder;
        $this->settings = $settings;
        $this->api = $api;
        $this->embedThumbnail = $embedThumbnail;
    }

    /**
     * @return array{status: string, item_id: ?int, message: string}
     */
    public function syncOne(string $identifier, SyncRunOptions $options): array
    {
        $identifier = trim((string) $identifier);
        try {
            $ia = $this->iaMetadata->fetch($identifier);
            $existing = $this->findItemByIdentifier($identifier);

            if ($existing && ($options->updateMetadata || $options->repairEmbedMedia)) {
                return $this->updateExisting($existing, $ia, $identifier, $options);
            }
            if ($existing && $options->syncMode === 'create_only') {
                return [
                    'status' => 'skipped',
                    'item_id' => (int) $existing['o:id'],
                    'message' => 'already exists as o:id=' . $existing['o:id'],
                ];
            }

            $built = $this->payloadBuilder->build(
                $ia,
                $options->resourceTemplateId,
                $options->itemSetId
            );
            $itemPayload = $built['item'];
            $mediaRows = $built['media'];

            if ($options->dryRun) {
                return ['status' => 'skipped', 'item_id' => null, 'message' => 'dry-run'];
            }

            $response = $this->api->create('items', $itemPayload);
            if (!$response->getContent()) {
                throw new \RuntimeException('Item create returned no content');
            }
            $item = $response->getContent();
            $itemId = (int) $item->id();

            $warnings = [];
            if ($this->applyMetadataIfMissing($itemId, $itemPayload)) {
                $warnings[] = 'metadata applied after create';
            }

            foreach ($mediaRows as $media) {
                $media['o:item'] = ['o:id' => $itemId];
                try {
                    $created = $this->api->create('media', $media)->getContent();
                    if ($created && $this->isEmbedMediaPayload($media)) {
                        $this->embedThumbnail->attachIfMissing((int) $created->id());
                    }
                } catch (\Exception $e) {
                    $warnings[] = $e->getMessage();
                }
            }

            if ($options->siteIds) {
                try {
                    $this->assignSites($itemId, $options->siteIds);
                } catch (\Exception $e) {
                    $warnings[] = 'site assignment: ' . $e->getMessage();
                }
            }

            $msg = 'created o:id=' . $itemId;
            if ($warnings) {
                $msg .= ' (warnings: ' . implode('; ', $warnings) . ')';
            }
            return ['status' => 'created', 'item_id' => $itemId, 'message' => $msg];
        } catch (\Exception $e) {
            return ['status' => 'failed', 'item_id' => null, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed>|object $existing
     * @param array<string, mixed> $ia
     * @return array{status: string, item_id: ?int, message: string}
     */
    protected function updateExisting($existing, array $ia, string $identifier, SyncRunOptions $options): array
    {
        $itemId = is_array($existing)
            ? (int) $existing['o:id']
            : (int) $existing->id();
        $parts = [];
        if ($options->updateMetadata) {
            $built = $this->payloadBuilder->build(
                $ia,
                $options->resourceTemplateId,
                $options->itemSetId
            );
            $patch = $this->payloadBuilder->metadataPatchBody($built['item']);
            $this->api->update('items', $itemId, $patch, [], ['isPartial' => true]);
            $parts[] = 'metadata patched';
            if ($options->siteIds) {
                $this->assignSites($itemId, $options->siteIds);
            }
        }
        if ($options->updateMetadata) {
            $removed = $this->reconcileIiif($itemId, $ia, $identifier, $options);
            if ($removed) {
                $parts[] = 'removed ' . $removed . ' IIIF media';
            }
            $created = $this->ensureIiifMedia($itemId, $ia, $identifier, $options);
            if ($created) {
                $parts[] = 'added ' . $created . ' IIIF media';
            }
        }
        if ($options->repairEmbedMedia) {
            $repair = $this->repairEmbedMedia($itemId, $identifier, $options);
            if ($repair['updated']) {
                $parts[] = 'repaired ' . $repair['updated'] . ' embed media';
            }
            if ($repair['created']) {
                $parts[] = 'added ' . $repair['created'] . ' embed media';
            }
        }
        return [
            'status' => 'updated',
            'item_id' => $itemId,
            'message' => $parts ? implode('; ', $parts) : 'no changes',
        ];
    }

    /**
     * @param int[] $siteIds
     */
    protected function assignSites(int $itemId, array $siteIds): void
    {
        $body = ['o:site' => array_map(fn ($id) => ['o:id' => $id], $siteIds)];
        $this->api->update('items', $itemId, $body, [], ['isPartial' => true]);
    }

    /**
     * @return array{updated: int, created: int}
     */
    protected function repairEmbedMedia(int $itemId, string $identifier, SyncRunOptions $options): array
    {
        if ($options->dryRun) {
            return ['updated' => 0, 'created' => 0];
        }

        $item = $this->api->read('items', $itemId)->getContent();
        $embedKey = 'ia-media:' . $identifier . ':embed';
        $html = $this->payloadBuilder->embedIframeHtml($identifier);
        $updated = 0;
        $hasEmbedRow = false;

        foreach ($item->media() as $media) {
            if ($media->ingester() !== 'html' || !$this->mediaMatchesEmbedKey($media, $embedKey)) {
                continue;
            }
            $hasEmbedRow = true;
            $current = trim((string) ($media->mediaData()['html'] ?? ''));
            if ($current === '' || !str_contains($current, 'archive.org/embed')) {
                $this->api->update('media', $media->id(), ['html' => $html], [], ['isPartial' => true]);
                ++$updated;
            }
            if ($this->embedThumbnail->attachIfMissing((int) $media->id(), true)) {
                ++$updated;
            }
        }

        $created = 0;
        if (!$hasEmbedRow) {
            $position = count($item->media());
            $row = $this->payloadBuilder->embedMediaRow($identifier, $position);
            $row['o:item'] = ['o:id' => $itemId];
            $createdMedia = $this->api->create('media', $row)->getContent();
            if ($createdMedia && $this->embedThumbnail->attachIfMissing((int) $createdMedia->id(), true)) {
                ++$updated;
            }
            $created = 1;
        }

        return ['updated' => $updated, 'created' => $created];
    }

    /**
     * @param \Omeka\Api\Representation\MediaRepresentation $media
     */
    protected function mediaMatchesEmbedKey($media, string $embedKey): bool
    {
        foreach ($media->value('dcterms:identifier', ['all' => true]) ?: [] as $value) {
            if ((string) $value->value() === $embedKey) {
                return true;
            }
        }
        return false;
    }

    protected function reconcileIiif(int $itemId, array $ia, string $identifier, SyncRunOptions $options): int
    {
        if ($options->dryRun) {
            return 0;
        }

        $shouldHavePresentation = $this->payloadBuilder->shouldIncludeIiifPresentationMedia($ia, $identifier);

        $item = $this->api->read('items', $itemId)->getContent();
        $removed = 0;
        if (!$shouldHavePresentation) {
            foreach ($item->media() as $media) {
                if ($media->ingester() !== 'iiif_presentation') {
                    continue;
                }
                $this->api->delete('media', $media->id());
                ++$removed;
            }
        }
        return $removed;
    }

    protected function ensureIiifMedia(int $itemId, array $ia, string $identifier, SyncRunOptions $options): int
    {
        if ($options->dryRun) {
            return 0;
        }
        if (!$this->payloadBuilder->shouldIncludeIiifPresentationMedia($ia, $identifier)) {
            return 0;
        }
        $item = $this->api->read('items', $itemId)->getContent();
        foreach ($item->media() as $media) {
            if ($media->ingester() === 'iiif_presentation') {
                return 0;
            }
        }
        $iiifPosition = 1;
        $embedKey = 'ia-media:' . $identifier . ':embed';
        foreach ($item->media() as $media) {
            if ($media->ingester() !== 'html' || !$this->mediaMatchesEmbedKey($media, $embedKey)) {
                continue;
            }
            // Standard IA layout: thumbnail (0), IIIF (1), embed (2).
            $this->api->update('media', $media->id(), ['o:position' => 2], [], ['isPartial' => true]);
            break;
        }
        $row = $this->payloadBuilder->iiifPresentationMediaRow($identifier, $iiifPosition);
        $row['o:item'] = ['o:id' => $itemId];
        $this->api->create('media', $row);
        return 1;
    }

    /**
     * Omeka sometimes persists media on create but drops Dublin Core values.
     * Re-apply the built metadata once when the new item has no IA identifier.
     *
     * @param array<string, mixed> $itemPayload
     */
    protected function applyMetadataIfMissing(int $itemId, array $itemPayload): bool
    {
        $item = $this->api->read('items', $itemId)->getContent();
        if (!$this->itemMissingMetadata($item)) {
            return false;
        }

        $patch = $this->payloadBuilder->metadataPatchBody($itemPayload);
        unset($patch['o:resource_template'], $patch['o:item_set']);
        $this->api->update('items', $itemId, $patch, [], ['isPartial' => true]);

        return true;
    }

    /**
     * @param \Omeka\Api\Representation\ItemRepresentation $item
     */
    protected function itemMissingMetadata($item): bool
    {
        foreach ($item->value('dcterms:identifier', ['all' => true]) ?: [] as $value) {
            $text = trim((string) $value->value());
            if ($text !== '' && !str_contains($text, ':')) {
                return false;
            }
        }

        return trim((string) $item->displayTitle()) === '';
    }

    /**
     * @param array<string, mixed> $media
     */
    protected function isEmbedMediaPayload(array $media): bool
    {
        if (($media['o:ingester'] ?? '') !== 'html') {
            return false;
        }
        foreach ($media['dcterms:identifier'] ?? [] as $identifier) {
            $value = is_array($identifier) ? (string) ($identifier['@value'] ?? '') : (string) $identifier;
            if (str_ends_with($value, ':embed')) {
                return true;
            }
        }

        $html = trim((string) ($media['html'] ?? ''));
        return $html !== '' && str_contains($html, 'archive.org/embed');
    }

    protected function findItemByIdentifier(string $identifier): ?array
    {
        $identifier = IaPath::normalize($identifier);
        $prefix = $this->settings->iaIdentifierPrefix();
        $candidates = array_unique(array_filter([
            $identifier,
            $prefix ? $prefix . $identifier : null,
            $prefix && str_starts_with($identifier, $prefix)
                ? substr($identifier, strlen($prefix))
                : null,
        ]));
        foreach ($candidates as $text) {
            $response = $this->api->search('items', [
                'property' => [
                    [
                        'property' => 'dcterms:identifier',
                        'type' => 'eq',
                        'text' => $text,
                    ],
                ],
            ]);
            $content = $response->getContent();
            if ($content) {
                $first = reset($content);
                return [
                    'o:id' => $first->id(),
                ];
            }
        }

        $embedKey = 'ia-media:' . $identifier . ':embed';
        $response = $this->api->search('media', [
            'property' => [[
                'property' => 'dcterms:identifier',
                'type' => 'eq',
                'text' => $embedKey,
            ]],
        ]);
        $content = $response->getContent();
        if ($content) {
            $media = reset($content);
            $item = $media->item();
            if ($item) {
                return ['o:id' => $item->id()];
            }
        }

        return null;
    }
}
