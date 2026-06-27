<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ImportForm extends Form
{
    public function init(): void
    {
        $this->setAttribute('id', 'ia-inbound-import-form');
        $this->add([
            'name' => 'preset_id',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Import preset', // @translate
                'info' => 'Optional profile that updates localization settings for this import (e.g. bilingual ka+en labels vs single-language). Does not replace collection, identifiers, or URLs you enter below.', // @translate
                'empty_option' => '— None —', // @translate
            ],
        ]);
        $this->add([
            'name' => 'collection_id',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Internet Archive collection (optional)', // @translate
                'info' => 'Import every item in an IA collection (the short name from archive.org/details/<name>). Leave empty if you are only importing specific items. Items that are not in any collection cannot be imported this way—use identifiers or URLs below instead.', // @translate
            ],
            'attributes' => ['placeholder' => 'e.g. my-archive-collection'],
        ]);
        $this->add([
            'name' => 'identifiers_text',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Item identifiers (optional, one per line)', // @translate
                'info' => 'Plain IA item ids (the segment after /details/ in an archive.org URL). Optional if you use a collection or URL list. Identifiers and URLs are two ways to name the same items—you can use either or both; duplicates are merged.', // @translate
            ],
            'attributes' => ['rows' => 5],
        ]);
        $this->add([
            'name' => 'urls_text',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Archive.org URLs (optional, one per line)', // @translate
                'info' => 'Details, embed, download, or metadata URLs; each line is parsed to the same item id as in the identifier list. Optional if you use a collection or identifier list. Use whichever format is easier—both fields add to the same import set.', // @translate
            ],
            'attributes' => ['rows' => 5],
        ]);
        $this->add([
            'name' => 'resource_template_id',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Resource template', // @translate
                'info' => 'Omeka template applied to each new item.', // @translate
            ],
        ]);
        $this->add([
            'name' => 'item_set_id',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Item set', // @translate
                'info' => 'Optional item set membership for imported items.', // @translate
                'empty_option' => '— None —', // @translate
            ],
        ]);
        $this->add([
            'name' => 'site_ids',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Assign to sites', // @translate
                'info' => 'Imported items are linked to every site checked here.', // @translate
            ],
        ]);
        $this->add([
            'name' => 'dry_run',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Dry run', // @translate
                'info' => 'Fetch IA metadata and show a summary without creating or changing Omeka items.', // @translate
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0',
            ],
        ]);
        $this->add([
            'name' => 'sync_mode',
            'type' => Element\Hidden::class,
            'attributes' => ['value' => 'create_only'],
        ]);
        $this->add([
            'name' => 'update_metadata',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Update metadata on existing items', // @translate
                'info' => 'When an item with the same IA identifier already exists, refresh Dublin Core fields from IA instead of skipping. Also adds or removes IIIF Presentation media rows to match current rules (images and single-PDF texts; not audio/video or multi-PDF).', // @translate
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0',
            ],
        ]);
        $this->add([
            'name' => 'repair_embed_media',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Repair Internet Archive embed media on existing items', // @translate
                'info' => 'For items that already exist: refill empty HTML embed media with the archive.org iframe (e.g. after HTML purifier stripped iframes on import). Creates a missing embed row if none is present.', // @translate
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0',
            ],
        ]);
        $this->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'attributes' => ['value' => 'Run import'], // @translate
        ]);
    }
}
