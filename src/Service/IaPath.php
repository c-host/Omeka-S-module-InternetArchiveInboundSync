<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

/**
 * Encode Internet Archive item paths (identifier or identifier/subpath).
 */
class IaPath
{
    public static function normalize(string $identifier): string
    {
        $identifier = trim($identifier);
        $identifier = preg_replace('#/+#', '/', $identifier) ?? $identifier;

        return trim($identifier, '/');
    }

    public static function encode(string $identifier): string
    {
        $identifier = self::normalize($identifier);
        if ($identifier === '') {
            return '';
        }

        $parts = explode('/', $identifier);

        return implode('/', array_map('rawurlencode', $parts));
    }

    public static function metadataUrl(string $identifier): string
    {
        return 'https://archive.org/metadata/' . self::encode($identifier);
    }

    public static function detailsUrl(string $identifier): string
    {
        return 'https://archive.org/details/' . self::encode($identifier);
    }

    public static function embedUrl(string $identifier): string
    {
        return 'https://archive.org/embed/' . self::encode($identifier);
    }

    public static function iiifManifestUrl(string $identifier): string
    {
        return 'https://iiif.archive.org/iiif/' . self::encode($identifier) . '/manifest.json';
    }
}
