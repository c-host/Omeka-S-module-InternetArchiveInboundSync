<?php declare(strict_types=1);
/**
 * Build a payload preview from IA metadata (no Omeka required).
 * Usage: php test/build_payload.php <ia-identifier>
 */

$identifier = $argv[1] ?? '027-video-chiatura-under-the-temporary-rule-of-capital-mautskebeli-2021';

require dirname(__DIR__) . '/src/Service/IaPath.php';
require dirname(__DIR__) . '/src/Service/IaHttpClient.php';
require dirname(__DIR__) . '/src/Service/IaMetadataClient.php';
require dirname(__DIR__) . '/src/Service/ModuleSettings.php';
require dirname(__DIR__) . '/src/Service/BilingualTextSplitter.php';
require dirname(__DIR__) . '/src/Service/Iso6392LanguageCatalog.php';
require dirname(__DIR__) . '/src/Service/IaLanguageResolver.php';
require dirname(__DIR__) . '/src/Service/LabelCatalog.php';
require dirname(__DIR__) . '/src/Service/IaIiifProbe.php';
require dirname(__DIR__) . '/src/Service/PayloadBuilder.php';

use InternetArchiveInboundSync\Service\BilingualTextSplitter;
use InternetArchiveInboundSync\Service\IaHttpClient;
use InternetArchiveInboundSync\Service\IaIiifProbe;
use InternetArchiveInboundSync\Service\IaLanguageResolver;
use InternetArchiveInboundSync\Service\IaMetadataClient;
use InternetArchiveInboundSync\Service\LabelCatalog;
use InternetArchiveInboundSync\Service\ModuleSettings;
use InternetArchiveInboundSync\Service\PayloadBuilder;

$store = [
    ModuleSettings::KEY_PREFIX . 'metadata_label_mode' => 'bilingual_display',
    ModuleSettings::KEY_PREFIX . 'split_on_delimiters' => true,
    ModuleSettings::KEY_PREFIX . 'split_description_html' => true,
];
$fake = new class ($store) {
    private array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function get($id, $default = null) { return $this->data[$id] ?? $default; }
    public function set($id, $value): void { $this->data[$id] = $value; }
    public function delete($id): void { unset($this->data[$id]); }
};
$settings = new ModuleSettings($fake);
$http = new IaHttpClient($settings);
$metadata = new IaMetadataClient($http);
$probe = new IaIiifProbe($http);
$builder = new PayloadBuilder(
    $settings,
    new BilingualTextSplitter(),
    new IaLanguageResolver($settings, new Iso6392LanguageCatalog()),
    new LabelCatalog($settings),
    $probe
);

$ia = $metadata->fetch($identifier);
$built = $builder->build($ia, 1, null);
echo json_encode($built['item'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
