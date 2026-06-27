<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LabelCatalogFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new LabelCatalog($services->get(ModuleSettings::class));
    }
}
