<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

class IaCollectionClient
{
    protected IaHttpClient $http;

    protected ModuleSettings $settings;

    public function __construct(IaHttpClient $http, ModuleSettings $settings)
    {
        $this->http = $http;
        $this->settings = $settings;
    }

    /**
     * @return string[]
     */
    public function fetchIdentifiers(string $collectionId, int $pageSize = 500): array
    {
        $identifiers = [];
        $page = 1;
        $delay = $this->settings->requestDelaySeconds();
        while (true) {
            $params = http_build_query([
                'q' => 'collection:' . $collectionId,
                'fl[]' => 'identifier',
                'rows' => $pageSize,
                'page' => $page,
                'output' => 'json',
            ], '', '&', PHP_QUERY_RFC3986);
            $url = 'https://archive.org/advancedsearch.php?' . $params;
            $data = $this->http->getJson($url, 120);
            $response = $data['response'] ?? [];
            $docs = $response['docs'] ?? [];
            foreach ($docs as $row) {
                $iid = $row['identifier'] ?? null;
                if ($iid !== null && $iid !== '') {
                    $identifiers[] = trim((string) $iid);
                }
            }
            $numFound = (int) ($response['numFound'] ?? 0);
            if ($page * $pageSize >= $numFound || !$docs) {
                break;
            }
            ++$page;
            if ($delay > 0) {
                usleep((int) ($delay * 1_000_000));
            }
        }
        return $identifiers;
    }
}
