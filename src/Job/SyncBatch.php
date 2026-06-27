<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Job;

use InternetArchiveInboundSync\Service\ItemSyncService;
use InternetArchiveInboundSync\Service\ModuleSettings;
use InternetArchiveInboundSync\Service\SyncRunOptions;
use InternetArchiveInboundSync\Service\SyncRunService;
class SyncBatch extends AbstractIaSyncJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $sync = $services->get(ItemSyncService::class);
        $settings = $services->get(ModuleSettings::class);
        $runs = $services->get(SyncRunService::class);
        $identifiers = $this->getArg('identifiers') ?? [];
        $runId = (int) ($this->getArg('run_id') ?? 0);
        $options = new SyncRunOptions($this->getArg('run_options') ?? []);
        $stats = [
            'created' => 0,
            'skipped' => 0,
            'updated' => 0,
            'failed' => 0,
            'results' => [],
        ];
        $log = '';
        foreach ($identifiers as $identifier) {
            if ($this->shouldStop()) {
                $this->getJobLogger()->warn('SyncBatch stopped by user');
                break;
            }
            $identifier = (string) $identifier;
            $result = $sync->syncOne($identifier, $options);
            $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;
            $stats['results'][] = ['identifier' => $identifier] + $result;
            $line = sprintf("%s: %s — %s\n", $identifier, $result['status'], $result['message']);
            $log .= $line;
            $this->getJobLogger()->notice($line);
            usleep((int) ($settings->requestDelaySeconds() * 1000000));
        }
        if ($runId) {
            $existing = $runs->getRun($runId);
            $merged = $existing['stats'] ?? [];
            foreach (['created', 'skipped', 'updated', 'failed'] as $key) {
                $merged[$key] = ($merged[$key] ?? 0) + ($stats[$key] ?? 0);
            }
            $merged['results'] = array_merge($merged['results'] ?? [], $stats['results']);
            $runs->updateRun($runId, $merged, $log);
        }
    }
}
