<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IdentifierResolverServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new IdentifierResolverService(
            $services->get(IaCollectionClient::class),
            $services->get(IaIdentifierParser::class)
        );
    }
}
