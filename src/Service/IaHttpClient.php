<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Laminas\Http\Client;
use Laminas\Http\Request;
use RuntimeException;

/**
 * HTTP client for Internet Archive read APIs.
 */
class IaHttpClient
{
    protected ModuleSettings $settings;

    public function __construct(ModuleSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function getJson(string $url, int $timeoutSeconds = 60): array
    {
        $client = new Client($url, [
            'timeout' => $timeoutSeconds,
        ]);
        $client->setMethod(Request::METHOD_GET);
        $client->setHeaders([
            'User-Agent' => $this->settings->userAgent(),
            'Accept' => 'application/json',
        ]);

        $response = $client->send();
        $status = $response->getStatusCode();
        if ($status === 429 || $status === 503) {
            usleep(500000);
            $response = $client->send();
            $status = $response->getStatusCode();
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf(
                'Internet Archive request failed (%d): %s',
                $status,
                $url
            ));
        }
        $body = $response->getBody();
        if ($body === '') {
            return [];
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON from Internet Archive: ' . $url);
        }
        return $data;
    }

    public function headOk(string $url, int $timeoutSeconds = 20): bool
    {
        $client = new Client($url, [
            'timeout' => $timeoutSeconds,
        ]);
        $client->setMethod(Request::METHOD_HEAD);
        $client->setHeaders([
            'User-Agent' => $this->settings->userAgent(),
        ]);
        try {
            $response = $client->send();
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Exception $e) {
            return false;
        }
    }
}
