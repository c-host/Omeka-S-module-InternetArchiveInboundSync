<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

class IaLanguageResolver
{
    protected ModuleSettings $settings;

    protected Iso6392LanguageCatalog $catalog;

    /** @var array<string, string> MARC bibliographic code => Georgian label */
    protected array $georgianLabels;

    public function __construct(ModuleSettings $settings, Iso6392LanguageCatalog $catalog)
    {
        $this->settings = $settings;
        $this->catalog = $catalog;
        $this->georgianLabels = $this->loadGeorgianLabels();
    }

    /**
     * Resolve primary catalog language from IA metadata.language (first distinct value).
     *
     * @param array<string, mixed> $meta
     */
    public function resolvePrimary(array $meta): ?string
    {
        $languages = $this->resolveLanguagesFromMeta($meta);

        return $languages[0] ?? null;
    }

    /**
     * Distinct BCP47 codes from IA metadata.language, preserving IA order.
     *
     * @param array<string, mixed> $meta
     * @return string[]
     */
    public function resolveLanguagesFromMeta(array $meta): array
    {
        $values = $this->rawLanguageValues($meta);
        $out = [];
        foreach ($values as $value) {
            $bcp47 = $this->resolveRawToBcp47($value);
            if ($bcp47 !== null && !in_array($bcp47, $out, true)) {
                $out[] = $bcp47;
            }
        }

        return $out;
    }

    /**
     * dcterms:language literals from IA metadata.language.
     *
     * @param array<string, mixed> $meta
     * @return array<int, array<string, mixed>>
     */
    public function languageLiteralsFromMeta(array $meta): array
    {
        $codes = $this->resolveLanguagesFromMeta($meta);
        if ($codes === []) {
            return [];
        }

        $literals = [];
        foreach ($codes as $code) {
            foreach ($this->languageLiterals($code) as $literal) {
                $literals[] = $literal;
            }
        }

        return $literals;
    }

    /**
     * dcterms:language literals for a resolved BCP47 code.
     *
     * @return array<int, array<string, mixed>>
     */
    public function languageLiterals(?string $primary): array
    {
        if ($primary === null || trim($primary) === '') {
            return [];
        }

        $primary = strtolower(trim($primary));
        $marc = $this->bcp47ToMarc($primary);
        $english = $this->englishLabel($primary, $marc);
        $georgian = $marc !== null ? ($this->georgianLabels[$marc] ?? null) : null;

        if ($this->settings->isBilingualDisplay()) {
            $literals = [];
            if ($english !== null) {
                $literals[] = $this->literal($english, 'en');
            }
            if ($georgian !== null) {
                $literals[] = $this->literal($georgian, 'ka');
            }

            return $literals;
        }

        if ($primary === 'ka' && $georgian !== null) {
            return [$this->literal($georgian, 'ka')];
        }
        if ($english !== null) {
            return [$this->literal($english, $primary === 'ka' ? 'ka' : 'en')];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $meta
     * @return string[]
     */
    protected function rawLanguageValues(array $meta): array
    {
        $raw = $meta['language'] ?? null;
        if ($raw === null) {
            return [];
        }
        if (is_array($raw)) {
            return array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                $raw
            ), static fn (string $value): bool => $value !== ''));
        }
        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        return [$text];
    }

    protected function resolveRawToBcp47(string $raw): ?string
    {
        $key = strtolower(trim($raw));
        if ($key === '') {
            return null;
        }

        $map = $this->settings->iaLanguageMap();
        if (isset($map[$key]['bcp47']) && is_string($map[$key]['bcp47'])) {
            return strtolower(trim($map[$key]['bcp47']));
        }

        $marc = $this->catalog->resolveBibliographic($key);
        if ($marc !== null) {
            return $this->marcToBcp47($marc, $map);
        }

        return null;
    }

    /**
     * @param array<string, array{bcp47?: string, marc?: string, label?: string}> $map
     */
    protected function marcToBcp47(string $marc, array $map): ?string
    {
        $marc = strtolower(trim($marc));
        foreach ($map as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryMarc = isset($entry['marc']) ? strtolower(trim((string) $entry['marc'])) : '';
            if ($entryMarc === $marc && !empty($entry['bcp47'])) {
                return strtolower(trim((string) $entry['bcp47']));
            }
        }

        $iso6391 = $this->catalog->iso6391ForBibliographic($marc);
        if ($iso6391 !== null && $iso6391 !== '') {
            return $iso6391;
        }

        return null;
    }

    protected function bcp47ToMarc(string $bcp47): ?string
    {
        $bcp47 = strtolower(trim($bcp47));
        $map = $this->settings->iaLanguageMap();
        foreach ($map as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryBcp47 = isset($entry['bcp47']) ? strtolower(trim((string) $entry['bcp47'])) : '';
            if ($entryBcp47 === $bcp47 && !empty($entry['marc'])) {
                return strtolower(trim((string) $entry['marc']));
            }
        }

        return $this->catalog->resolveBibliographic($bcp47);
    }

    protected function englishLabel(string $bcp47, ?string $marc): ?string
    {
        $map = $this->settings->iaLanguageMap();
        foreach ($map as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryBcp47 = isset($entry['bcp47']) ? strtolower(trim((string) $entry['bcp47'])) : '';
            if ($entryBcp47 === $bcp47 && !empty($entry['label'])) {
                return strtolower(trim((string) $entry['label']));
            }
        }

        if ($marc !== null) {
            $english = $this->catalog->englishNameForBibliographic($marc);
            if ($english !== null && $english !== '') {
                return strtolower($english);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function loadGeorgianLabels(): array
    {
        $path = dirname(__DIR__, 2) . '/data/language-georgian-labels.json';
        if (!is_readable($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $marc => $label) {
            $marcKey = strtolower(trim((string) $marc));
            $labelText = trim((string) $label);
            if (strlen($marcKey) === 3 && $labelText !== '') {
                $out[$marcKey] = $labelText;
            }
        }

        return $out;
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
}
