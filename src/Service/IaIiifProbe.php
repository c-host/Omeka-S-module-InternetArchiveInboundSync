<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

class IaIiifProbe
{
    protected IaHttpClient $http;

    public function __construct(IaHttpClient $http)
    {
        $this->http = $http;
    }

    public function manifestAvailable(string $identifier): bool
    {
        return $this->manifestReachable($this->manifestUrl($identifier), 20);
    }

    public function manifestReachable(?string $manifestUrl, int $timeoutSeconds = 5): bool
    {
        if ($manifestUrl === null || $manifestUrl === '') {
            return false;
        }

        return $this->http->headOk($manifestUrl, $timeoutSeconds);
    }

    public function manifestUrl(string $identifier): string
    {
        return IaPath::iiifManifestUrl($identifier);
    }
}
