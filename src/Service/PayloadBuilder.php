<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

class PayloadBuilder
{
    public const IA_THUMB_FILENAME = '__ia_thumb.jpg';

    /** Bundled Internet Archive logo + wordmark for embed viewer media-list thumbnails. */
    public const IA_EMBED_VIEWER_THUMBNAIL_FILE = 'asset/img/internet-archive-logo-wordmark.png';

    protected ModuleSettings $settings;

    protected BilingualTextSplitter $splitter;

    protected IaLanguageResolver $languageResolver;

    protected LabelCatalog $labels;

    protected IaIiifProbe $iiifProbe;

    public function __construct(
        ModuleSettings $settings,
        BilingualTextSplitter $splitter,
        IaLanguageResolver $languageResolver,
        LabelCatalog $labels,
        IaIiifProbe $iiifProbe
    ) {
        $this->settings = $settings;
        $this->splitter = $splitter;
        $this->languageResolver = $languageResolver;
        $this->labels = $labels;
        $this->iiifProbe = $iiifProbe;
    }

    /**
     * @param array<string, mixed> $ia Full IA metadata API response
     * @return array{item: array<string, mixed>, media: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function build(
        array $ia,
        int $resourceTemplateId,
        ?int $itemSetId
    ): array {
        $meta = $ia['metadata'] ?? [];
        $identifier = IaPath::normalize((string) ($meta['identifier'] ?? ''));
        if ($identifier === '') {
            throw new \InvalidArgumentException('IA metadata missing identifier');
        }

        $splitDelim = $this->settings->splitOnDelimiters();
        $splitDescriptionByLanguage = $this->settings->splitDescriptionHtml();
        $primaryLang = $this->languageResolver->resolvePrimary($meta);

        $payload = [
            'o:resource_template' => ['o:id' => $resourceTemplateId],
            'dcterms:identifier' => [$this->literal((string) $identifier)],
            'dcterms:source' => $this->labels->sourceUriValues(
                IaPath::detailsUrl($identifier)
            ),
        ];

        if ($itemSetId) {
            $payload['o:item_set'] = [['o:id' => $itemSetId]];
        }

        $payload['dcterms:title'] = [];
        foreach ($this->splitter->splitTitle($meta['title'] ?? null, $splitDelim) as $part) {
            $payload['dcterms:title'][] = $this->literal($part['value'], $part['language']);
        }
        if (!$payload['dcterms:title']) {
            unset($payload['dcterms:title']);
        }

        $payload['dcterms:creator'] = [];
        foreach ($this->splitter->splitCreators($meta['creator'] ?? null, $splitDelim) as $part) {
            $payload['dcterms:creator'][] = $this->literal($part['value'], $part['language']);
        }
        if (!$payload['dcterms:creator']) {
            unset($payload['dcterms:creator']);
        }

        $subjectRaw = $meta['subject'] ?? null;
        $subjectParts = is_array($subjectRaw)
            ? array_filter(array_map('strval', $subjectRaw))
            : $this->splitSubjects(is_string($subjectRaw) ? $subjectRaw : null);
        $payload['dcterms:subject'] = [];
        foreach ($this->splitter->sortByLanguage(array_map(function ($s) {
            return ['value' => $s, 'language' => $this->splitter->detectLanguage($s)];
        }, $subjectParts)) as $subject) {
            $payload['dcterms:subject'][] = $this->literal($subject['value'], $subject['language']);
        }
        if (!$payload['dcterms:subject']) {
            unset($payload['dcterms:subject']);
        }

        if (!empty($meta['date'])) {
            $payload['dcterms:date'] = [$this->literal((string) $meta['date'])];
        }
        $created = $this->createdLiteral($meta);
        if ($created) {
            $payload['dcterms:created'] = [$created];
        }
        if (!empty($meta['licenseurl'])) {
            $payload['dcterms:rights'] = [$this->uriValue((string) $meta['licenseurl'], null, null)];
        }
        if (!empty($meta['identifier-ark'])) {
            $payload['dcterms:identifier'][] = $this->literal(trim((string) $meta['identifier-ark']));
        }

        $payload['dcterms:description'] = [];
        foreach ($this->splitter->splitDescription(
            $meta['description'] ?? null,
            $splitDelim,
            $splitDescriptionByLanguage
        ) as $part) {
            $payload['dcterms:description'][] = $this->literal($part['value'], $part['language']);
        }
        if (!$payload['dcterms:description']) {
            unset($payload['dcterms:description']);
        }

        $langLiterals = $this->languageResolver->languageLiteralsFromMeta($meta);
        if ($langLiterals) {
            $payload['dcterms:language'] = $langLiterals;
        }

        $typeLiterals = $this->labels->mediatypeLiterals($meta['mediatype'] ?? null);
        if ($typeLiterals) {
            $payload['dcterms:type'] = $typeLiterals;
        }

        $thumb = $this->resolveThumbUrl($ia, $identifier);
        $includeIiifMedia = $this->shouldIncludeIiifPresentationMedia($ia, $identifier);
        $media = $this->buildMediaRows($identifier, $thumb, $includeIiifMedia);

        $syncMeta = [
            'include_iiif_media' => $includeIiifMedia,
            'thumbnail_url' => $thumb,
            'ia_identifier' => $identifier,
            'primary_language' => $primaryLang,
        ];

        return [
            'item' => $payload,
            'media' => $media,
            'meta' => $syncMeta,
        ];
    }

    /**
     * @param array<string, mixed> $fullPayload
     * @return array<string, mixed>
     */
    public function metadataPatchBody(array $fullPayload): array
    {
        $skip = ['o:media', 'o:resource_template', 'o:item_set'];
        $body = array_diff_key($fullPayload, array_flip($skip));
        $body['dcterms:isPartOf'] = [];
        return $body;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildMediaRows(string $identifier, string $thumbnailUrl, bool $includeIiifMedia): array
    {
        $mediaKey = 'ia-media:' . $identifier;
        $rows = [
            [
                'o:ingester' => 'url',
                'ingest_url' => $thumbnailUrl,
                'o:source' => $thumbnailUrl,
                'o:position' => 0,
                'dcterms:title' => $this->labels->mediaTitleLiterals('thumb'),
                'dcterms:identifier' => [$this->literal($mediaKey . ':thumb')],
            ],
        ];
        if ($includeIiifMedia) {
            $rows[] = $this->iiifPresentationMediaRow($identifier, 1);
        }
        $rows[] = [
            'o:ingester' => 'html',
            'html' => $this->embedIframeHtml($identifier),
            'o:position' => $includeIiifMedia ? 2 : 1,
            'dcterms:title' => $this->labels->mediaTitleLiterals('embed'),
            'dcterms:identifier' => [$this->literal($mediaKey . ':embed')],
        ];
        return $rows;
    }

    /**
     * Whether to add an IIIF Presentation media row (images and single-PDF texts).
     *
     * @param array<string, mixed> $ia
     */
    public function shouldIncludeIiifPresentationMedia(array $ia, string $identifier): bool
    {
        if (str_contains(IaPath::normalize($identifier), '/')) {
            return false;
        }
        if ($this->isAudioVideoItem($ia)) {
            return false;
        }
        $mediatype = strtolower((string) (($ia['metadata'] ?? [])['mediatype'] ?? ''));
        if (in_array($mediatype, ['texts', 'text'], true) && $this->countOriginalPdfs($ia) > 1) {
            return false;
        }
        return $this->iiifProbe->manifestAvailable($identifier);
    }

    /**
     * @param array<string, mixed> $ia
     */
    protected function isAudioVideoItem(array $ia): bool
    {
        return $this->isAudioItem($ia) || $this->isVideoItem($ia);
    }

    /**
     * @param array<string, mixed> $ia
     */
    protected function isAudioItem(array $ia): bool
    {
        $mediatype = strtolower((string) (($ia['metadata'] ?? [])['mediatype'] ?? ''));
        return $mediatype === 'audio';
    }

    /**
     * @param array<string, mixed> $ia
     */
    protected function isVideoItem(array $ia): bool
    {
        $mediatype = strtolower((string) (($ia['metadata'] ?? [])['mediatype'] ?? ''));
        return in_array($mediatype, ['movies', 'video'], true);
    }

    /**
     * @param array<string, mixed> $ia
     */
    public function resolveThumbUrl(array $ia, string $identifier): string
    {
        if (!$this->hasThumbFile($ia)) {
            $image = ($ia['metadata'] ?? [])['image'] ?? null;
            return $this->fallbackThumbUrl($image, $identifier);
        }
        $locs = $ia['alternate_locations'] ?? [];
        foreach (['workable', 'servers'] as $key) {
            $entries = $locs[$key] ?? [];
            if ($entries) {
                $entry = $entries[0];
                $server = rtrim((string) ($entry['server'] ?? ''), '/');
                $dir = (string) ($entry['dir'] ?? '');
                if ($server && $dir) {
                    return 'https://' . $server . $dir . '/' . self::IA_THUMB_FILENAME;
                }
            }
        }
        return 'https://archive.org/download/' . $identifier . '/' . self::IA_THUMB_FILENAME;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>|null
     */
    protected function createdLiteral(array $meta): ?array
    {
        $raw = $meta['publicdate'] ?? $meta['addeddate'] ?? null;
        if (!$raw || trim((string) $raw) === '') {
            return null;
        }
        $text = trim((string) $raw);
        if (strlen($text) >= 10 && $text[4] === '-' && $text[7] === '-') {
            $text = substr($text, 0, 10);
        }
        return $this->literal($text);
    }

    /**
     * @return string[]
     */
    protected function splitSubjects(?string $raw): array
    {
        if (!$raw || trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/\s*;\s*/', $raw) ?: [])));
    }

    /**
     * @param array<string, mixed> $ia
     */
    protected function hasThumbFile(array $ia): bool
    {
        foreach ($ia['files'] ?? [] as $f) {
            if (($f['name'] ?? '') === self::IA_THUMB_FILENAME) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $ia
     */
    protected function countOriginalPdfs(array $ia): int
    {
        $count = 0;
        foreach ($ia['files'] ?? [] as $f) {
            $name = strtolower((string) ($f['name'] ?? ''));
            if (!str_ends_with($name, '.pdf') || str_ends_with($name, '_text.pdf')) {
                continue;
            }
            if (strtolower((string) ($f['source'] ?? '')) === 'derivative') {
                continue;
            }
            ++$count;
        }
        return $count;
    }

    protected function fallbackThumbUrl($imageField, string $identifier): string
    {
        if (!$imageField) {
            return 'https://archive.org/services/img/' . $identifier;
        }
        $image = (string) $imageField;
        if (str_starts_with($image, 'http')) {
            return $image;
        }
        if (str_starts_with($image, '/')) {
            return 'https://archive.org' . $image;
        }
        return $image;
    }

    public function iiifManifestUrl(string $identifier): string
    {
        return IaPath::iiifManifestUrl($identifier);
    }

    /**
     * @return array<string, mixed>
     */
    public function iiifPresentationMediaRow(string $identifier, int $position): array
    {
        $mediaKey = 'ia-media:' . $identifier;
        return [
            'o:ingester' => 'iiif_presentation',
            'o:source' => $this->iiifManifestUrl($identifier),
            'o:position' => $position,
            'dcterms:title' => $this->labels->mediaTitleLiterals('iiif'),
            'dcterms:identifier' => [$this->literal($mediaKey . ':iiif')],
        ];
    }

    public static function embedViewerThumbnailPath(): string
    {
        return dirname(__DIR__, 2) . '/' . self::IA_EMBED_VIEWER_THUMBNAIL_FILE;
    }

    public function embedIframeHtml(string $identifier): string
    {
        return '<iframe src="' . htmlspecialchars(IaPath::embedUrl($identifier), ENT_QUOTES, 'UTF-8')
            . '" width="100%" height="420" frameborder="0" webkitallowfullscreen="true" '
            . 'mozallowfullscreen="true" allowfullscreen></iframe>';
    }

    /**
     * @return array<string, mixed>
     */
    public function embedMediaRow(string $identifier, int $position): array
    {
        $mediaKey = 'ia-media:' . $identifier;
        return [
            'o:ingester' => 'html',
            'html' => $this->embedIframeHtml($identifier),
            'o:position' => $position,
            'dcterms:title' => $this->labels->mediaTitleLiterals('embed'),
            'dcterms:identifier' => [$this->literal($mediaKey . ':embed')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function literal(string $value, ?string $language = null): array
    {
        $row = [
            'type' => 'literal',
            'property_id' => 'auto',
            '@value' => $value,
            'is_public' => true,
        ];
        if ($language) {
            $row['@language'] = $language;
        }
        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    protected function uriValue(string $url, ?string $label, ?string $language): array
    {
        $row = [
            'type' => 'uri',
            'property_id' => 'auto',
            '@id' => $url,
            'is_public' => true,
        ];
        if ($label) {
            $row['o:label'] = $label;
        }
        if ($language) {
            $row['o:lang'] = $language;
        }
        return $row;
    }
}
