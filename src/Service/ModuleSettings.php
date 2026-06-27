<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

/**
 * Module configuration from Omeka\Settings.
 */
class ModuleSettings
{
    public const KEY_PREFIX = 'internet_archive_inbound_sync_';

    public const TABLE_SYNC_RUN = 'internet_archive_inbound_sync_run';

    public const TABLE_SYNC_PRESET = 'internet_archive_inbound_sync_preset';

    public const DEFAULT_USER_AGENT = 'Omeka-InternetArchiveInboundSync/1.0 (https://omeka.org/s)';

    public const MODE_BILINGUAL = 'bilingual_display';
    public const MODE_SINGLE = 'single_language';

    protected $settings;

    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return \Omeka\Settings\SettingInterface
     */
    public function getOmekaSettings()
    {
        return $this->settings;
    }

    protected function key(string $name): string
    {
        return self::KEY_PREFIX . $name;
    }

    public function metadataLabelMode(): string
    {
        $mode = (string) $this->settings->get($this->key('metadata_label_mode'), self::MODE_BILINGUAL);
        return $mode === self::MODE_SINGLE ? self::MODE_SINGLE : self::MODE_BILINGUAL;
    }

    public function isBilingualDisplay(): bool
    {
        return $this->metadataLabelMode() === self::MODE_BILINGUAL;
    }

    public function userAgent(): string
    {
        $ua = self::valueAsString(
            $this->settings->get($this->key('user_agent')),
            self::DEFAULT_USER_AGENT
        );
        if ($ua === '') {
            return self::DEFAULT_USER_AGENT;
        }

        return $ua;
    }

    public function requestDelaySeconds(): float
    {
        return (float) $this->settings->get($this->key('request_delay_seconds'), 0.5);
    }

    public function chunkSize(): int
    {
        return max(1, (int) $this->settings->get($this->key('chunk_size'), 5));
    }

    public function iaIdentifierPrefix(): string
    {
        $prefix = (string) $this->settings->get($this->key('ia_identifier_prefix'), 'ia:');
        if ($prefix && !str_ends_with($prefix, ':')) {
            $prefix .= ':';
        }
        return $prefix;
    }

    public function syncMode(): string
    {
        return (string) $this->settings->get($this->key('sync_mode'), 'create_only');
    }

    public function splitOnDelimiters(): bool
    {
        return true;
    }

    public function splitDescriptionHtml(): bool
    {
        return true;
    }

    public function stripContributorAttribution(): bool
    {
        return true;
    }

    public function defaultCollectionId(): string
    {
        return self::valueAsString($this->settings->get($this->key('default_collection_id'), ''));
    }

    /**
     * Omeka stores settings as JSON; empty strings often round-trip as [].
     */
    public static function valueAsString($value, string $default = ''): string
    {
        if ($value === null || is_array($value)) {
            return $default;
        }
        return trim((string) $value);
    }

    public function defaultResourceTemplateId(): ?int
    {
        $id = $this->settings->get($this->key('default_resource_template_id'));
        return $id ? (int) $id : null;
    }

    public function defaultItemSetId(): ?int
    {
        $id = $this->settings->get($this->key('default_item_set_id'));
        return $id ? (int) $id : null;
    }

    /**
     * @return int[]
     */
    public function defaultSiteIds(): array
    {
        $raw = $this->settings->get($this->key('default_site_ids'), '[]');
        if (is_array($raw)) {
            return array_map('intval', $raw);
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    /**
     * @return array<string, array{bcp47?: string, marc?: string, label?: string}>
     */
    public function iaLanguageMap(): array
    {
        $defaults = [
            'en' => ['bcp47' => 'en', 'marc' => 'eng', 'label' => 'English'],
            'eng' => ['bcp47' => 'en', 'marc' => 'eng', 'label' => 'English'],
            'english' => ['bcp47' => 'en', 'marc' => 'eng', 'label' => 'English'],
            'geo' => ['bcp47' => 'ka', 'marc' => 'geo', 'label' => 'Georgian'],
            'ka' => ['bcp47' => 'ka', 'marc' => 'geo', 'label' => 'Georgian'],
            'georgian' => ['bcp47' => 'ka', 'marc' => 'geo', 'label' => 'Georgian'],
            'ru' => ['bcp47' => 'ru', 'marc' => 'rus', 'label' => 'Russian'],
            'rus' => ['bcp47' => 'ru', 'marc' => 'rus', 'label' => 'Russian'],
            'russian' => ['bcp47' => 'ru', 'marc' => 'rus', 'label' => 'Russian'],
        ];
        $raw = $this->settings->get($this->key('ia_language_map'));
        if (!$raw) {
            return $defaults;
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        return array_merge($defaults, $decoded);
    }

    public static function defaultInstallSettings(): array
    {
        $p = self::KEY_PREFIX;
        return [
            $p . 'metadata_label_mode' => self::MODE_BILINGUAL,
            $p . 'user_agent' => self::DEFAULT_USER_AGENT,
            $p . 'request_delay_seconds' => 0.5,
            $p . 'chunk_size' => 5,
            $p . 'ia_identifier_prefix' => 'ia:',
            $p . 'sync_mode' => 'create_only',
            $p . 'split_on_delimiters' => true,
            $p . 'split_description_html' => true,
            $p . 'strip_contributor_attribution' => true,
            $p . 'default_collection_id' => '',
            $p . 'default_site_ids' => json_encode([]),
            $p . 'ia_language_map' => json_encode([
                'en' => ['bcp47' => 'en', 'marc' => 'eng', 'label' => 'English'],
                'eng' => ['bcp47' => 'en', 'marc' => 'eng', 'label' => 'English'],
                'geo' => ['bcp47' => 'ka', 'marc' => 'geo', 'label' => 'Georgian'],
                'ka' => ['bcp47' => 'ka', 'marc' => 'geo', 'label' => 'Georgian'],
                'ru' => ['bcp47' => 'ru', 'marc' => 'rus', 'label' => 'Russian'],
                'rus' => ['bcp47' => 'ru', 'marc' => 'rus', 'label' => 'Russian'],
            ]),
        ];
    }
}
