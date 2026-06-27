<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Controller\Admin;

use InternetArchiveInboundSync\Service\SyncRunService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class HistoryController extends AbstractActionController
{
    public function browseAction()
    {
        $runs = $this->getEvent()->getApplication()->getServiceManager()
            ->get(SyncRunService::class)
            ->listRuns(100);
        $view = new ViewModel(['runs' => $runs]);
        $view->setTemplate('internet-archive-inbound-sync/admin/history/browse');
        return $view;
    }

    public function showAction()
    {
        $id = (int) $this->params('id');
        $run = $this->getEvent()->getApplication()->getServiceManager()
            ->get(SyncRunService::class)
            ->getRun($id);
        if (!$run) {
            return $this->redirect()->toRoute(
                'admin/internet-archive-inbound-sync/default',
                ['controller' => 'history', 'action' => 'browse']
            );
        }
        $view = new ViewModel(['run' => $run]);
        $view->setTemplate('internet-archive-inbound-sync/admin/history/show');
        return $view;
    }
}
