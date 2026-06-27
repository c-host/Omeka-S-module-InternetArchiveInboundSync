<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IaCollectionClientFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new IaCollectionClient(
            $services->get(IaHttpClient::class),
            $services->get(ModuleSettings::class)
        );
    }
}
