<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IaLanguageResolverFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new IaLanguageResolver(
            $services->get(ModuleSettings::class),
            $services->get(Iso6392LanguageCatalog::class)
        );
    }
}
