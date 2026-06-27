<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Controller\Admin;

use InternetArchiveInboundSync\Form\ImportForm;
use InternetArchiveInboundSync\Job\SyncImport;
use InternetArchiveInboundSync\Service\IaMetadataClient;
use InternetArchiveInboundSync\Service\IdentifierResolverService;
use InternetArchiveInboundSync\Service\ModuleSettings;
use InternetArchiveInboundSync\Service\PayloadBuilder;
use InternetArchiveInboundSync\Service\SetupStatusService;
use InternetArchiveInboundSync\Service\SyncPresetService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
class ImportController extends AbstractActionController
{
    public function indexAction()
    {
        $services = $this->getEvent()->getApplication()->getServiceManager();
        $settings = $services->get(ModuleSettings::class);
        $presets = $services->get(SyncPresetService::class);
        $api = $services->get('Omeka\ApiManager');
        $formManager = $services->get('FormElementManager');
        /** @var ImportForm $form */
        $form = $formManager->get(ImportForm::class);
        $form->init();
        $this->populateImportForm($form, $settings, $api, $presets);

        $setupStatus = $services->get(SetupStatusService::class)->getStatus($services);
        $dryRunSummary = null;

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $data = $form->getData();
                $data = $this->mergePresetIntoImportData($data, $presets, $settings);

                $resolver = $services->get(IdentifierResolverService::class);
                $identifiers = $resolver->resolve([
                    'collection_id' => $data['collection_id'] ?? '',
                    'identifiers_text' => $data['identifiers_text'] ?? '',
                    'urls_text' => $data['urls_text'] ?? '',
                ]);

                if (!$identifiers) {
                    $this->messenger()->addWarning('No identifiers resolved from your input.');
                } elseif (empty($data['resource_template_id'])) {
                    $this->messenger()->addError('Resource template is required.');
                } elseif (!empty($data['dry_run'])) {
                    $dryRunSummary = $this->runDryRun(
                        $services->get(IaMetadataClient::class),
                        $services->get(PayloadBuilder::class),
                        $identifiers,
                        (int) $data['resource_template_id'],
                        $data['item_set_id'] ? (int) $data['item_set_id'] : null
                    );
                    $this->messenger()->addSuccess(sprintf(
                        'Dry run: %d identifier(s) processed.',
                        count($identifiers)
                    ));
                } else {
                    $runOptions = [
                        'resource_template_id' => (int) $data['resource_template_id'],
                        'item_set_id' => $data['item_set_id'] ?: null,
                        'site_ids' => $data['site_ids'] ?? [],
                        'dry_run' => false,
                        'sync_mode' => $data['sync_mode'] ?? 'create_only',
                        'update_metadata' => !empty($data['update_metadata']),
                        'repair_embed_media' => !empty($data['repair_embed_media']),
                    ];
                    $identity = $this->identity();
                    $job = $this->jobDispatcher()->dispatch(SyncImport::class, [
                        'import_input' => [
                            'collection_id' => $data['collection_id'] ?? '',
                            'identifiers_text' => $data['identifiers_text'] ?? '',
                            'urls_text' => $data['urls_text'] ?? '',
                        ],
                        'run_options' => $runOptions,
                        'chunk_size' => $settings->chunkSize(),
                        'owner_id' => $identity ? $identity->getId() : null,
                    ]);
                    $this->messenger()->addSuccess(sprintf(
                        'Import queued as job #%d (%d identifiers). Review imported items in Omeka afterward—titles, subjects, descriptions, and languages may need manual correction.',
                        $job->getId(),
                        count($identifiers)
                    ));
                }
            }
        } else {
            $siteIds = $settings->defaultSiteIds();
            if ($siteIds) {
                $form->get('site_ids')->setValue($siteIds);
            }
        }

        $view = new ViewModel([
            'form' => $form,
            'setupStatus' => $setupStatus,
            'dryRunSummary' => $dryRunSummary,
        ]);
        $view->setTemplate('internet-archive-inbound-sync/admin/import/index');
        return $view;
    }

    protected function populateImportForm(
        ImportForm $form,
        ModuleSettings $settings,
        $api,
        SyncPresetService $presets
    ): void {
        $presetOptions = ['' => '— None —'];
        foreach ($presets->listPresets() as $row) {
            $presetOptions[$row['id']] = $row['label'];
        }
        $form->get('preset_id')->setValueOptions($presetOptions);

        $templates = [];
        foreach ($api->search('resource_templates', ['limit' => 200])->getContent() as $tpl) {
            $templates[$tpl->id()] = $tpl->label();
        }
        $form->get('resource_template_id')->setValueOptions($templates);
        $defaultTpl = $settings->defaultResourceTemplateId();
        if ($defaultTpl) {
            $form->get('resource_template_id')->setValue($defaultTpl);
        }

        $itemSets = ['' => '— None —'];
        foreach ($api->search('item_sets', ['limit' => 200])->getContent() as $set) {
            $itemSets[$set->id()] = $set->displayTitle();
        }
        $form->get('item_set_id')->setValueOptions($itemSets);
        $defaultSet = $settings->defaultItemSetId();
        if ($defaultSet) {
            $form->get('item_set_id')->setValue($defaultSet);
        }

        $sites = [];
        foreach ($api->search('sites', ['limit' => 50])->getContent() as $site) {
            $sites[$site->id()] = $site->title();
        }
        $form->get('site_ids')->setValueOptions($sites);

        $form->get('sync_mode')->setValue('create_only');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mergePresetIntoImportData(
        array $data,
        SyncPresetService $presets,
        ModuleSettings $settings
    ): array {
        $presetId = (int) ($data['preset_id'] ?? 0);
        if ($presetId <= 0) {
            return $data;
        }
        $preset = $presets->getPreset($presetId);
        if (!$preset) {
            return $data;
        }
        $ps = $preset['settings'];
        $this->applyPresetToModuleSettings($settings, $ps);

        if (trim((string) ($data['collection_id'] ?? '')) === '') {
            $collectionId = ModuleSettings::valueAsString($ps['default_collection_id'] ?? '');
            if ($collectionId !== '') {
                $data['collection_id'] = $collectionId;
            }
        }
        if (!empty($ps['sync_mode'])) {
            $data['sync_mode'] = $ps['sync_mode'];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $presetSettings
     */
    protected function applyPresetToModuleSettings(ModuleSettings $settings, array $presetSettings): void
    {
        $s = $settings->getOmekaSettings();
        $p = ModuleSettings::KEY_PREFIX;

        if (isset($presetSettings['metadata_label_mode'])) {
            $s->set($p . 'metadata_label_mode', $presetSettings['metadata_label_mode']);
        }
        if (isset($presetSettings['sync_mode'])) {
            $s->set($p . 'sync_mode', $presetSettings['sync_mode']);
        }
    }

    /**
     * @param string[] $identifiers
     * @return array<string, mixed>
     */
    protected function runDryRun(
        IaMetadataClient $ia,
        PayloadBuilder $builder,
        array $identifiers,
        int $templateId,
        ?int $itemSetId
    ): array {
        $summary = ['ok' => 0, 'failed' => 0, 'items' => []];
        foreach ($identifiers as $id) {
            $id = trim((string) $id);
            try {
                $payload = $builder->build($ia->fetch($id), $templateId, $itemSetId);
                $summary['ok']++;
                $summary['items'][] = [
                    'identifier' => $id,
                    'title_count' => count($payload['item']['dcterms:title'] ?? []),
                    'media_count' => count($payload['media']),
                    'include_iiif_media' => $payload['meta']['include_iiif_media'] ?? false,
                ];
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['items'][] = [
                    'identifier' => $id,
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $summary;
    }
}
