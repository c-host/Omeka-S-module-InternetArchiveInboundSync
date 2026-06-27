<?php declare(strict_types=1);
/**
 * Quick smoke tests (no PHPUnit required).
 * Run: php test/smoke.php
 */

require dirname(__DIR__) . '/src/Service/IaPath.php';
require dirname(__DIR__) . '/src/Service/BilingualTextSplitter.php';
require dirname(__DIR__) . '/src/Service/IaIdentifierParser.php';
require dirname(__DIR__) . '/src/Service/Iso6392LanguageCatalog.php';
require dirname(__DIR__) . '/src/Service/IaLanguageResolver.php';
require dirname(__DIR__) . '/src/Service/ModuleSettings.php';

use InternetArchiveInboundSync\Service\BilingualTextSplitter;
use InternetArchiveInboundSync\Service\IaIdentifierParser;
use InternetArchiveInboundSync\Service\IaLanguageResolver;
use InternetArchiveInboundSync\Service\Iso6392LanguageCatalog;
use InternetArchiveInboundSync\Service\ModuleSettings;

$failures = 0;

$splitter = new BilingualTextSplitter(true);
$parts = $splitter->splitTitle(
    'The Wound | ჭრილობა',
    true
);
if (count($parts) !== 2 || ($parts[0]['language'] ?? '') !== 'en') {
    echo "FAIL title split\n";
    ++$failures;
} else {
    echo "OK title split\n";
}

$parser = new IaIdentifierParser();
$id = $parser->parse('https://archive.org/details/gutenberg');
if ($id !== 'gutenberg') {
    echo "FAIL URL parser\n";
    ++$failures;
} else {
    echo "OK URL parser\n";
}

$subpath = $parser->parse(
    'https://archive.org/details/khudoni_hpp_newspaper_collection_1989_2003/Binder1_gazeti/'
);
if ($subpath !== 'khudoni_hpp_newspaper_collection_1989_2003/Binder1_gazeti') {
    echo "FAIL subpath URL parser: $subpath\n";
    ++$failures;
} else {
    echo "OK subpath URL parser\n";
}

$store = ModuleSettings::defaultInstallSettings();
$fake = new class ($store) {
    private array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function get($id, $default = null) { return $this->data[$id] ?? $default; }
    public function set($id, $value): void { $this->data[$id] = $value; }
    public function delete($id): void { unset($this->data[$id]); }
};
$settings = new ModuleSettings($fake);
$resolver = new IaLanguageResolver($settings, new Iso6392LanguageCatalog());
$literals = $resolver->languageLiterals(
    $resolver->resolvePrimary(['language' => 'English'])
);
$values = array_column($literals, '@value');
if (!in_array('english', $values, true) || !in_array('ინგლისური', $values, true)) {
    echo "FAIL language pair\n";
    ++$failures;
} else {
    echo "OK language pair\n";
}

exit($failures ? 1 : 0);
