<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BilingualTextSplitterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $settings = $services->get(ModuleSettings::class);
        return new BilingualTextSplitter($settings->stripContributorAttribution());
    }
}
