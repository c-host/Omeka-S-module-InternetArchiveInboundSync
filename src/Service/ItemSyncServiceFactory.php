<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemSyncServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ItemSyncService(
            $services->get(IaMetadataClient::class),
            $services->get(PayloadBuilder::class),
            $services->get(ModuleSettings::class),
            $services->get('Omeka\ApiManager'),
            $services->get(EmbedMediaThumbnailService::class)
        );
    }
}
