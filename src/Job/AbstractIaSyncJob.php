<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Job;

use Laminas\Log\Logger;
use Omeka\Job\AbstractJob;

abstract class AbstractIaSyncJob extends AbstractJob
{
    protected function getJobLogger(): Logger
    {
        return $this->getServiceLocator()->get('Omeka\Logger');
    }
}
