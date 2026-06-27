<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\File\Store\StoreInterface;
use Omeka\File\TempFileFactory;
use Omeka\File\ThumbnailManager;
use Omeka\Stdlib\ErrorStore;

/**
 * Attach the bundled Internet Archive logo as the list thumbnail for HTML embed media rows.
 */
class EmbedMediaThumbnailService
{
    protected EntityManager $entityManager;

    protected TempFileFactory $tempFileFactory;

    protected StoreInterface $store;

    protected ThumbnailManager $thumbnailManager;

    public function __construct(
        EntityManager $entityManager,
        TempFileFactory $tempFileFactory,
        StoreInterface $store,
        ThumbnailManager $thumbnailManager
    ) {
        $this->entityManager = $entityManager;
        $this->tempFileFactory = $tempFileFactory;
        $this->store = $store;
        $this->thumbnailManager = $thumbnailManager;
    }

    public function attachIfMissing(int $mediaId, bool $replace = false): bool
    {
        $media = $this->entityManager->find(Media::class, $mediaId);
        if (!$media instanceof Media || $media->getIngester() !== 'html') {
            return false;
        }

        if (!$this->isIaEmbedMedia($media)) {
            return false;
        }

        if ($media->hasThumbnails()) {
            if (!$replace) {
                return false;
            }
            $this->clearThumbnails($media);
        }

        $sourcePath = PayloadBuilder::embedViewerThumbnailPath();
        if (!is_readable($sourcePath)) {
            return false;
        }

        $tempFile = $this->tempFileFactory->build();
        if (!copy($sourcePath, $tempFile->getTempPath())) {
            $tempFile->delete();

            return false;
        }
        $tempFile->setSourceName(basename($sourcePath));

        $request = new Request(Request::CREATE, 'media');
        $errorStore = new ErrorStore();
        $tempFile->mediaIngestFile($media, $request, $errorStore, false);
        if ($errorStore->hasErrors()) {
            return false;
        }

        $this->entityManager->flush();

        return $media->hasThumbnails();
    }

    protected function clearThumbnails(Media $media): void
    {
        $storageId = $media->getStorageId();
        if (!$storageId) {
            $media->setHasThumbnails(false);

            return;
        }

        foreach (array_keys($this->thumbnailManager->getTypeConfig()) as $type) {
            $this->store->delete(sprintf('%s/%s.jpg', $type, $storageId));
        }

        $media->setHasThumbnails(false);
    }

    protected function isIaEmbedMedia(Media $media): bool
    {
        foreach ($media->getValues() as $value) {
            $property = $value->getProperty();
            if (!$property || $property->getLocalName() !== 'identifier') {
                continue;
            }
            $vocabulary = $property->getVocabulary();
            if (!$vocabulary || $vocabulary->getPrefix() !== 'dcterms') {
                continue;
            }
            if (str_ends_with((string) $value->getValue(), ':embed')) {
                return true;
            }
        }

        $html = trim((string) (($media->getData() ?? [])['html'] ?? ''));
        return $html !== '' && str_contains($html, 'archive.org/embed');
    }
}
