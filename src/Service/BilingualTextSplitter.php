<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

/**
 * Split IA bilingual plain text for Omeka literals (ka / en).
 */
class BilingualTextSplitter
{
    /** @var string[] */
    protected const TEXT_SEPARATORS = [' | ', ' / '];

    protected const LANGUAGE_ORDER = ['en', 'ka'];

    protected bool $stripContributorAttribution;

    public function __construct(bool $stripContributorAttribution = true)
    {
        $this->stripContributorAttribution = $stripContributorAttribution;
    }

    /**
     * @return array<int, array{value: string, language: ?string}>
     */
    public function splitTitle(?string $raw, bool $splitOnDelimiters): array
    {
        if (!$raw || trim($raw) === '') {
            return [];
        }
        $text = trim($raw);
        if ($splitOnDelimiters) {
            foreach (self::TEXT_SEPARATORS as $sep) {
                if (str_contains($text, $sep)) {
                    $parts = array_filter(array_map('trim', explode($sep, $text)));
                    if (count($parts) >= 2) {
                        $out = [];
                        $seen = [];
                        foreach ($parts as $part) {
                            if (isset($seen[$part])) {
                                continue;
                            }
                            $seen[$part] = true;
                            $out[] = ['value' => $part, 'language' => $this->detectLanguage($part)];
                        }
                        return $this->sortByLanguage($out);
                    }
                }
            }
        }
        return $this->sortByLanguage([['value' => $text, 'language' => $this->detectLanguage($text)]]);
    }

    /**
     * @return array<int, array{value: string, language: ?string}>
     */
    /**
     * @param bool $splitByLanguageBlocks When true, split on | / delimiters and merge paragraphs by detected language.
     */
    public function splitDescription(?string $raw, bool $splitOnDelimiters, bool $splitByLanguageBlocks): array
    {
        if (!$raw || trim($raw) === '') {
            return [];
        }
        $text = $this->stripHtml($raw);
        if ($text === '') {
            return [];
        }

        $attribution = null;
        if ($this->stripContributorAttribution
            && preg_match('/(?:\n|\s)*Contributed to the .+ Archive by:\s*.+$/is', $text, $m, PREG_OFFSET_CAPTURE)
        ) {
            $attributionText = trim($m[0][0]);
            $text = trim(substr($text, 0, $m[0][1]));
            $attribution = [
                'value' => $attributionText,
                'language' => $this->detectLanguage($attributionText) ?? 'en',
            ];
        }

        if ($splitOnDelimiters && $splitByLanguageBlocks) {
            $parts = $this->splitOnSeparators($text);
            if ($parts !== null) {
                $out = [];
                $seen = [];
                foreach ($parts as $part) {
                    if (isset($seen[$part])) {
                        continue;
                    }
                    $seen[$part] = true;
                    $out[] = ['value' => $part, 'language' => $this->detectLanguage($part)];
                }
                if ($attribution) {
                    $out[] = $attribution;
                }
                return $this->sortByLanguage($out);
            }
        }

        $paragraphs = preg_split('/\n\s*\n+/', $text) ?: [];
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));
        if (!$paragraphs) {
            $paragraphs = [$text];
        }

        if (count($paragraphs) === 1 || !$splitByLanguageBlocks) {
            $out = array_map(
                fn (string $p) => ['value' => $p, 'language' => $this->detectLanguage($p)],
                $paragraphs
            );
        } else {
            $out = $this->mergeParagraphsByLanguage($paragraphs);
        }
        if ($attribution) {
            $out[] = $attribution;
        }
        return $this->sortByLanguage($out);
    }

    /**
     * @return array<int, array{value: string, language: ?string}>
     */
    public function splitCreators(?string $raw, bool $splitOnDelimiters): array
    {
        if (!$raw || trim($raw) === '') {
            return [];
        }
        $text = trim($raw);
        $parts = $this->splitTitle($text, $splitOnDelimiters);
        if (count($parts) >= 2) {
            return $parts;
        }
        if (count($parts) === 1) {
            return $parts;
        }
        if (str_contains($text, ',') && !str_contains($text, ';')) {
            $rows = [];
            foreach (explode(',', $text) as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $rows[] = ['value' => $p, 'language' => $this->detectLanguage($p)];
                }
            }
            return $this->sortByLanguage($rows);
        }
        return $this->sortByLanguage([['value' => $text, 'language' => $this->detectLanguage($text)]]);
    }

    public function detectLanguage(string $segment): ?string
    {
        $text = trim($segment);
        if ($text === '') {
            return null;
        }
        if (preg_match('/[\x{10A0}-\x{10FF}]/u', $text) && preg_match('/https?:\/\/|www\./i', $text)) {
            return 'ka';
        }
        $prose = preg_replace('/https?:\/\/\S+|www\.\S+/i', ' ', $text) ?? $text;
        $prose = trim($prose) ?: $text;
        $hasKa = (bool) preg_match('/[\x{10A0}-\x{10FF}]/u', $prose);
        $hasEn = (bool) preg_match('/[A-Za-z]/', $prose);
        if ($hasKa && !$hasEn) {
            return 'ka';
        }
        if ($hasEn && !$hasKa) {
            return 'en';
        }
        if ($hasKa && $hasEn) {
            preg_match_all('/[\x{10A0}-\x{10FF}]/u', $prose, $kaM);
            preg_match_all('/[A-Za-z]/', $prose, $enM);
            return count($kaM[0] ?? []) >= count($enM[0] ?? []) ? 'ka' : 'en';
        }
        return null;
    }

    public function stripHtml(string $raw): string
    {
        $text = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/p\s*>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<\/div\s*>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\n\s*\n+/', "\n\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * @param array<int, array{value: string, language: ?string}> $values
     * @return array<int, array{value: string, language: ?string}>
     */
    public function sortByLanguage(array $values): array
    {
        $order = array_flip(self::LANGUAGE_ORDER);
        usort($values, function ($a, $b) use ($order) {
            $la = $a['language'] ? ($order[$a['language']] ?? 99) : 100;
            $lb = $b['language'] ? ($order[$b['language']] ?? 99) : 100;
            if ($la !== $lb) {
                return $la <=> $lb;
            }
            return strcmp($a['value'], $b['value']);
        });
        return $values;
    }

    /**
     * @return string[]|null
     */
    protected function splitOnSeparators(string $text): ?array
    {
        foreach (self::TEXT_SEPARATORS as $sep) {
            if (str_contains($text, $sep)) {
                $parts = array_filter(array_map('trim', explode($sep, $text)));
                if (count($parts) >= 2) {
                    return array_values($parts);
                }
            }
        }
        return null;
    }

    /**
     * @param string[] $paragraphs
     * @return array<int, array{value: string, language: ?string}>
     */
    protected function mergeParagraphsByLanguage(array $paragraphs): array
    {
        $groups = [];
        foreach ($paragraphs as $para) {
            $lang = $this->detectLanguage($para);
            if ($groups && end($groups)['lang'] === $lang) {
                $groups[count($groups) - 1]['paras'][] = $para;
            } else {
                $groups[] = ['lang' => $lang, 'paras' => [$para]];
            }
        }
        $out = [];
        $seen = [];
        foreach ($groups as $g) {
            $value = trim(implode("\n\n", $g['paras']));
            if ($value === '') {
                continue;
            }
            $key = $value . '|' . ($g['lang'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['value' => $value, 'language' => $g['lang']];
        }
        return $out;
    }
}
