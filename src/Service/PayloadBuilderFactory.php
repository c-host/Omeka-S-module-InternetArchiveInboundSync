<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PayloadBuilderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new PayloadBuilder(
            $services->get(ModuleSettings::class),
            $services->get(BilingualTextSplitter::class),
            $services->get(IaLanguageResolver::class),
            $services->get(LabelCatalog::class),
            $services->get(IaIiifProbe::class)
        );
    }
}
