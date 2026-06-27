<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

/**
 * EN/KA labels for source, mediatype, media titles (layer C).
 */
class LabelCatalog
{
    protected ModuleSettings $settings;

    public function __construct(ModuleSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sourceUriValues(string $url): array
    {
        if ($this->settings->isBilingualDisplay()) {
            return [
                $this->uriValue($url, 'Internet Archive', 'en'),
                $this->uriValue($url, 'ინტერნეტ არქივი', 'ka'),
            ];
        }
        return [$this->uriValue($url, 'Internet Archive', 'en')];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mediatypeLiterals(?string $mediatype): array
    {
        $labels = $this->mediatypeLabels($mediatype);
        if (!$labels) {
            return [];
        }
        if ($this->settings->isBilingualDisplay()) {
            return [
                $this->literal($labels[0], 'en'),
                $this->literal($labels[1], 'ka'),
            ];
        }
        return [$this->literal($labels[0], 'en')];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mediaTitleLiterals(string $suffix): array
    {
        $labels = $this->mediaTitleLabels($suffix);
        if (!$labels) {
            return [];
        }
        if ($this->settings->isBilingualDisplay()) {
            return [
                $this->literal($labels[0], 'en'),
                $this->literal($labels[1], 'ka'),
            ];
        }
        return [$this->literal($labels[0], 'en')];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    protected function mediatypeLabels(?string $mediatype): ?array
    {
        if (!$mediatype || trim($mediatype) === '') {
            return null;
        }
        $key = strtolower(trim($mediatype));
        $map = [
            'movies' => ['movies', 'ვიდეო'],
            'video' => ['video', 'ვიდეო'],
            'image' => ['image', 'სურათი'],
            'audio' => ['audio', 'აუდიო'],
            'texts' => ['texts', 'ტექსტები'],
            'text' => ['text', 'ტექსტი'],
        ];
        return $map[$key] ?? [$key, $key];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    protected function mediaTitleLabels(string $suffix): ?array
    {
        $map = [
            'thumb' => ['Thumbnail', 'მინიატურა'],
            'iiif' => ['IIIF Viewer', 'IIIF ნახვა'],
            'embed' => ['Internet Archive Viewer', 'ინტერნეტ არქივის ნახვა'],
        ];
        return $map[$suffix] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function literal(string $value, ?string $language): array
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
    protected function uriValue(string $url, string $label, ?string $language): array
    {
        $row = [
            'type' => 'uri',
            'property_id' => 'auto',
            '@id' => $url,
            'o:label' => $label,
            'is_public' => true,
        ];
        if ($language) {
            $row['o:lang'] = $language;
        }
        return $row;
    }
}
