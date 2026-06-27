<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EmbedMediaThumbnailServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null): EmbedMediaThumbnailService
    {
        return new EmbedMediaThumbnailService(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\File\TempFileFactory'),
            $services->get('Omeka\File\Store'),
            $services->get('Omeka\File\ThumbnailManager')
        );
    }
}
