<?php declare(strict_types=1);

namespace InternetArchiveInboundSync;

use InternetArchiveInboundSync\Form\ConfigForm;
use InternetArchiveInboundSync\Service\InstallDefaultsService;
use InternetArchiveInboundSync\Service\ModuleSettings;
use InternetArchiveInboundSync\Service\SetupStatusService;
use InternetArchiveInboundSync\Service\SyncPresetService;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;
use Omeka\Permissions\Acl;

class Module extends AbstractModule
{
    /**
     * Omeka instantiates not-yet-active modules via `new Module` during install,
     * before Laminas registers getAutoloaderConfig(). Load src/ classes explicitly.
     */
    protected function registerModuleAutoloader(): void
    {
        if (class_exists(ModuleSettings::class, false)) {
            return;
        }
        $loader = new \Laminas\Loader\StandardAutoloader([
            'namespaces' => [
                'InternetArchiveInboundSync' => __DIR__ . '/src',
            ],
        ]);
        $loader->register();
    }

    public function getConfig()
    {
        $this->registerModuleAutoloader();

        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(\Laminas\Mvc\MvcEvent $event)
    {
        parent::onBootstrap($event);
        $services = $this->getServiceLocator();
        $this->registerModuleAutoloader();

        $acl = $services->get('Omeka\Acl');
        $acl->allow(
            [Acl::ROLE_GLOBAL_ADMIN],
            [
                'InternetArchiveInboundSync\Controller\Admin\Import',
                'InternetArchiveInboundSync\Controller\Admin\History',
            ]
        );
    }

    public function install(ServiceLocatorInterface $services): void
    {
        $this->registerModuleAutoloader();

        $conn = $services->get('Omeka\Connection');
        $table = ModuleSettings::TABLE_SYNC_RUN;
        $conn->exec(<<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id INT AUTO_INCREMENT NOT NULL,
    job_id INT DEFAULT NULL,
    owner_id INT DEFAULT NULL,
    started DATETIME NOT NULL,
    parameters LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
    stats LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
    log LONGTEXT DEFAULT NULL,
    INDEX IDX_ia_inbound_sync_run_job (job_id),
    INDEX IDX_ia_inbound_sync_run_owner (owner_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
SQL);

        $settings = $services->get('Omeka\Settings');
        foreach (ModuleSettings::defaultInstallSettings() as $key => $value) {
            $settings->set($key, $value);
        }
        (new SyncPresetService($conn))->seedDefaults();
        InstallDefaultsService::seed($services);
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services)
    {
        $this->registerModuleAutoloader();
        $conn = $services->get('Omeka\Connection');
        (new SyncPresetService($conn))->seedDefaults();
        InstallDefaultsService::seed($services);
    }

    public function uninstall(ServiceLocatorInterface $services): void
    {
        $this->registerModuleAutoloader();

        $conn = $services->get('Omeka\Connection');
        $conn->exec('SET FOREIGN_KEY_CHECKS=0;');
        $conn->exec('DROP TABLE IF EXISTS ' . ModuleSettings::TABLE_SYNC_RUN);
        $conn->exec('DROP TABLE IF EXISTS ' . ModuleSettings::TABLE_SYNC_PRESET);
        $conn->exec('SET FOREIGN_KEY_CHECKS=1;');

        $settings = $services->get('Omeka\Settings');
        $keys = array_keys(ModuleSettings::defaultInstallSettings());
        $p = ModuleSettings::KEY_PREFIX;
        $keys[] = $p . 'default_resource_template_id';
        $keys[] = $p . 'default_item_set_id';
        foreach ($keys as $key) {
            $settings->delete($key);
        }
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        /** @var ConfigForm $form */
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->setModuleSettings($services->get(ModuleSettings::class));
        $form->init();
        $form->configureResourceOptions($api);
        $form->loadFromModuleSettings();

        $setupStatus = $services->get(SetupStatusService::class)->getStatus($services);
        $checklist = $renderer->partial(
            'internet-archive-inbound-sync/admin/partials/setup-checklist',
            ['setupStatus' => $setupStatus]
        );

        $intro = '<p>' . $renderer->escapeHtml(
            'Set import defaults and HTTP settings here. The checklist below shows what else you may need before imports and public media pages work as intended.'
        ) . '</p>'
        . '<p>' . $renderer->escapeHtml(
            'IA Inbound works on its own. To push Omeka metadata back to Internet Archive, install the separate IA Outbound module (requires an IA collection and S3 API credentials).'
        ) . '</p>';

        return $intro . $checklist . $renderer->formCollection($form, false);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        /** @var ConfigForm $form */
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->setModuleSettings($services->get(ModuleSettings::class));
        $form->init();
        $form->configureResourceOptions($services->get('Omeka\ApiManager'));
        $form->setData($this->normalizeConfigPost($controller->params()->fromPost()));
        if (!$form->isValid()) {
            $messages = [];
            foreach ($form->getMessages() as $field => $fieldMessages) {
                if (is_array($fieldMessages)) {
                    foreach ($fieldMessages as $msg) {
                        $messages[] = is_string($field) ? "$field: $msg" : (string) $msg;
                    }
                }
            }
            if ($messages) {
                $controller->messenger()->addError(implode(' ', $messages));
            }
            return false;
        }
        try {
            $form->save();
        } catch (\Throwable $e) {
            $controller->messenger()->addError($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    protected function normalizeConfigPost(array $post): array
    {
        if (isset($post['import_defaults']) || isset($post['advanced'])) {
            return array_merge(
                $post,
                (array) ($post['import_defaults'] ?? []),
                (array) ($post['advanced'] ?? [])
            );
        }
        return $post;
    }
}
