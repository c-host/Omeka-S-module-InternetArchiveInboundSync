<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Form;

use InternetArchiveInboundSync\Service\ModuleSettings;
use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    protected ?ModuleSettings $moduleSettings = null;

    protected bool $initialized = false;

    public function init(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        $this->setAttribute('id', 'internet-archive-inbound-sync-config');

        $this->add([
            'name' => 'default_resource_template_id',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Default resource template', // @translate
                'info' => 'Pre-selects the resource template on IA Inbound → Import. Required for every import.', // @translate
                'empty_option' => '— None —', // @translate
            ],
        ]);
        $this->add([
            'name' => 'default_item_set_id',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Default item set', // @translate
                'info' => 'Optional item set pre-selected on the Import form.', // @translate
                'empty_option' => '— None —', // @translate
            ],
        ]);
        $this->add([
            'name' => 'default_site_ids',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Default sites', // @translate
                'info' => 'Sites pre-checked on the Import form. Imported items are linked to every site checked.', // @translate
            ],
        ]);
        $this->add([
            'name' => 'chunk_size',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Items per background job', // @translate
                'info' => 'Each Omeka background job processes this many IA items before the next job starts. Smaller values reduce timeout risk; larger values mean fewer jobs for big imports.', // @translate
                'value_options' => [
                    '1' => '1 item per job',
                    '3' => '3 items per job',
                    '5' => '5 items per job (recommended)',
                    '10' => '10 items per job',
                    '20' => '20 items per job',
                ],
            ],
        ]);
        $this->add([
            'name' => 'request_delay_seconds',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Pause between items (seconds)', // @translate
                'info' => 'Delay between consecutive item requests to Internet Archive during batch jobs. Internet Archive asks automated clients to avoid hammering their servers.', // @translate
                'value_options' => [
                    '0' => 'No delay',
                    '0.5' => '0.5 seconds (recommended)',
                    '1' => '1 second',
                    '2' => '2 seconds',
                ],
            ],
        ]);
        $this->add([
            'name' => 'ia_identifier_prefix',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Item lookup identifier prefix', // @translate
                'info' => 'Used when matching existing items stored with a prefixed IA identifier (e.g. ia:item-id). New imports store the plain IA identifier.', // @translate
            ],
            'attributes' => ['readonly' => true, 'size' => 10],
        ]);
        $this->add([
            'name' => 'sync_mode',
            'type' => Element\Hidden::class,
        ]);
    }

    /**
     * @param \Omeka\Api\Manager $api
     */
    public function configureResourceOptions($api): void
    {
        $templates = ['' => '— None —'];
        foreach ($api->search('resource_templates', ['limit' => 200])->getContent() as $tpl) {
            $templates[(string) $tpl->id()] = $tpl->label();
        }
        $this->get('default_resource_template_id')->setValueOptions($templates);

        $itemSets = ['' => '— None —'];
        foreach ($api->search('item_sets', ['limit' => 200])->getContent() as $set) {
            $itemSets[(string) $set->id()] = $set->displayTitle();
        }
        $this->get('default_item_set_id')->setValueOptions($itemSets);

        $sites = [];
        foreach ($api->search('sites', ['limit' => 100])->getContent() as $site) {
            $sites[(string) $site->id()] = $site->title();
        }
        $this->get('default_site_ids')->setValueOptions($sites);
    }

    public function loadFromModuleSettings(): void
    {
        $ms = $this->moduleSettings;

        $defaultTpl = $ms->defaultResourceTemplateId();
        if ($defaultTpl) {
            $this->get('default_resource_template_id')->setValue((string) $defaultTpl);
        }
        $defaultSet = $ms->defaultItemSetId();
        if ($defaultSet) {
            $this->get('default_item_set_id')->setValue((string) $defaultSet);
        }
        $siteIds = $ms->defaultSiteIds();
        if ($siteIds) {
            $this->get('default_site_ids')->setValue(array_map('strval', $siteIds));
        }
        $this->get('chunk_size')->setValue((string) $ms->chunkSize());
        $delay = (string) $ms->requestDelaySeconds();
        if (!in_array($delay, ['0', '0.5', '1', '2'], true)) {
            $delay = '0.5';
        }
        $this->get('request_delay_seconds')->setValue($delay);

        $this->get('ia_identifier_prefix')->setValue($ms->iaIdentifierPrefix());
        $this->get('sync_mode')->setValue($ms->syncMode());
    }

    public function setModuleSettings(ModuleSettings $settings): void
    {
        $this->moduleSettings = $settings;
    }

    public function save(): void
    {
        $s = $this->moduleSettings->getOmekaSettings();
        $p = ModuleSettings::KEY_PREFIX;
        $data = $this->getData();

        $templateId = (int) ($data['default_resource_template_id'] ?? 0);
        if ($templateId > 0) {
            $s->set($p . 'default_resource_template_id', $templateId);
        } else {
            $s->delete($p . 'default_resource_template_id');
        }
        $itemSetId = (int) ($data['default_item_set_id'] ?? 0);
        if ($itemSetId > 0) {
            $s->set($p . 'default_item_set_id', $itemSetId);
        } else {
            $s->delete($p . 'default_item_set_id');
        }
        $siteIds = array_map('intval', $data['default_site_ids'] ?? []);
        $s->set($p . 'default_site_ids', json_encode(array_values($siteIds)));
        $s->set($p . 'chunk_size', max(1, (int) ($data['chunk_size'] ?? 5)));
        $s->set($p . 'request_delay_seconds', (float) ($data['request_delay_seconds'] ?? 0.5));

        if (!empty($data['sync_mode'])) {
            $s->set($p . 'sync_mode', $data['sync_mode']);
        }
    }
}
