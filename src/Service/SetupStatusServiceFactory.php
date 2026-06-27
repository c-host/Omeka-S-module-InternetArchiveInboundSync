<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SetupStatusServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null): SetupStatusService
    {
        return new SetupStatusService($services->get(ModuleSettings::class));
    }
}
