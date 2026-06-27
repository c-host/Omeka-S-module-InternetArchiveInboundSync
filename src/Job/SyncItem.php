<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Job;

use InternetArchiveInboundSync\Service\ItemSyncService;
use InternetArchiveInboundSync\Service\ModuleSettings;
use InternetArchiveInboundSync\Service\SyncRunOptions;
use InternetArchiveInboundSync\Service\SyncRunService;
class SyncItem extends AbstractIaSyncJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $sync = $services->get(ItemSyncService::class);
        $settings = $services->get(ModuleSettings::class);
        $identifier = (string) $this->getArg('identifier');
        if ($identifier === '') {
            $this->getJobLogger()->err('SyncItem: missing identifier');
            return;
        }
        $options = new SyncRunOptions($this->getArg('run_options') ?? []);
        $result = $sync->syncOne($identifier, $options);
        $this->getJobLogger()->notice(
            '{identifier}: {status} — {message}',
            [
                'identifier' => $identifier,
                'status' => $result['status'],
                'message' => $result['message'],
            ]
        );
        $runId = (int) ($this->getArg('run_id') ?? 0);
        if ($runId) {
            $runs = $services->get(SyncRunService::class);
            $row = $runs->getRun($runId);
            $stats = $row['stats'] ?? [];
            $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;
            $stats['results'] = $stats['results'] ?? [];
            $stats['results'][] = ['identifier' => $identifier] + $result;
            $runs->updateRun(
                $runId,
                $stats,
                sprintf("%s: %s — %s\n", $identifier, $result['status'], $result['message'])
            );
        }
        usleep((int) ($settings->requestDelaySeconds() * 1000000));
    }
}
