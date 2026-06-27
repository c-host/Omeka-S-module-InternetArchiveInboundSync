<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

class IdentifierResolverService
{
    protected IaCollectionClient $collectionClient;

    protected IaIdentifierParser $parser;

    public function __construct(IaCollectionClient $collectionClient, IaIdentifierParser $parser)
    {
        $this->collectionClient = $collectionClient;
        $this->parser = $parser;
    }

    /**
     * @param array{collection_id?: string, identifiers_text?: string, urls_text?: string} $input
     * @return string[]
     */
    public function resolve(array $input): array
    {
        $ids = [];
        $collectionId = trim((string) ($input['collection_id'] ?? ''));
        if ($collectionId !== '') {
            $ids = array_merge($ids, $this->collectionClient->fetchIdentifiers($collectionId));
        }
        if (!empty($input['identifiers_text'])) {
            $ids = array_merge($ids, $this->parser->parseLines((string) $input['identifiers_text']));
        }
        if (!empty($input['urls_text'])) {
            $ids = array_merge($ids, $this->parser->parseLines((string) $input['urls_text']));
        }
        $unique = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $unique[$id] = true;
            }
        }
        return array_keys($unique);
    }
}
