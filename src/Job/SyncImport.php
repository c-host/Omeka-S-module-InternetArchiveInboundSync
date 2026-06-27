<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Job;

use InternetArchiveInboundSync\Service\IdentifierResolverService;
use InternetArchiveInboundSync\Service\ModuleSettings;
use InternetArchiveInboundSync\Service\SyncRunService;
use Omeka\Job\Dispatcher;

class SyncImport extends AbstractIaSyncJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $resolver = $services->get(IdentifierResolverService::class);
        $settings = $services->get(ModuleSettings::class);
        $runs = $services->get(SyncRunService::class);
        $dispatcher = $services->get(Dispatcher::class);

        $input = $this->getArg('import_input') ?? [];
        $identifiers = $resolver->resolve($input);
        $runOptions = $this->getArg('run_options') ?? [];
        $ownerId = (int) ($this->getArg('owner_id') ?? 0) ?: null;
        $runId = $runs->createRun($this->job->getId(), $ownerId, [
            'identifiers_count' => count($identifiers),
            'run_options' => $runOptions,
            'import_input' => $input,
        ]);

        $chunkSize = (int) ($this->getArg('chunk_size') ?? $settings->chunkSize());
        $chunks = array_chunk($identifiers, max(1, $chunkSize));
        $this->getJobLogger()->notice(
            'SyncImport: {count} identifiers in {chunks} batch job(s)',
            ['count' => count($identifiers), 'chunks' => count($chunks)]
        );

        foreach ($chunks as $chunk) {
            $dispatcher->dispatch(SyncBatch::class, [
                'identifiers' => $chunk,
                'run_options' => $runOptions,
                'run_id' => $runId,
            ]);
        }
    }
}
