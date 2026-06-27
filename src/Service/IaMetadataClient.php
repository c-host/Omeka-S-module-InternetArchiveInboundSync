<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

class IaMetadataClient
{
    protected IaHttpClient $http;

    public function __construct(IaHttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $identifier): array
    {
        $identifier = IaPath::normalize($identifier);
        if ($identifier === '') {
            throw new \InvalidArgumentException('IA identifier is empty');
        }

        if (!str_contains($identifier, '/')) {
            return $this->http->getJson(IaPath::metadataUrl($identifier), 60);
        }

        [$parent, $subpath] = explode('/', $identifier, 2);
        $ia = $this->http->getJson(IaPath::metadataUrl($parent), 60);
        if (!isset($ia['metadata']) || !is_array($ia['metadata'])) {
            $ia['metadata'] = [];
        }

        $ia['metadata']['identifier'] = $identifier;
        $ia['metadata']['ia_parent_identifier'] = $parent;
        $ia['metadata']['ia_subpath'] = $subpath;
        $ia['metadata']['title'] = $this->subpathTitle($ia, $subpath, (string) ($ia['metadata']['title'] ?? ''));

        return $ia;
    }

    /**
     * @param array<string, mixed> $ia
     */
    protected function subpathTitle(array $ia, string $subpath, string $parentTitle): string
    {
        $pdfName = $subpath;
        if (!str_ends_with(strtolower($pdfName), '.pdf')) {
            $pdfName .= '.pdf';
        }

        foreach ($ia['files'] ?? [] as $file) {
            if (!is_array($file)) {
                continue;
            }
            if (strcasecmp((string) ($file['name'] ?? ''), $pdfName) !== 0) {
                continue;
            }
            $label = trim(str_replace(['_', '-'], ' ', $subpath));
            if ($label !== '') {
                return $parentTitle !== ''
                    ? $parentTitle . ' / ' . $label
                    : $label;
            }
        }

        $label = trim(str_replace(['_', '-'], ' ', $subpath));

        return $parentTitle !== '' && $label !== ''
            ? $parentTitle . ' / ' . $label
            : ($label !== '' ? $label : $parentTitle);
    }
}
