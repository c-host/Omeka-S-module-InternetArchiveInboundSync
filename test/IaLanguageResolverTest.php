<?php declare(strict_types=1);

namespace InternetArchiveInboundSyncTest;

use InternetArchiveInboundSync\Service\IaLanguageResolver;
use InternetArchiveInboundSync\Service\Iso6392LanguageCatalog;
use InternetArchiveInboundSync\Service\ModuleSettings;
use PHPUnit\Framework\TestCase;

class IaLanguageResolverTest extends TestCase
{
    public function testEnglishLanguagePair(): void
    {
        $settings = $this->fakeSettings(['metadata_label_mode' => 'bilingual_display']);
        $resolver = new IaLanguageResolver($settings, new Iso6392LanguageCatalog());
        $primary = $resolver->resolvePrimary(['language' => 'English']);
        $this->assertSame('en', $primary);
        $literals = $resolver->languageLiterals($primary);
        $values = array_column($literals, '@value');
        $this->assertContains('english', $values);
        $this->assertContains('ინგლისური', $values);
    }

    public function testSingleLanguageMode(): void
    {
        $settings = $this->fakeSettings(['metadata_label_mode' => 'single_language']);
        $resolver = new IaLanguageResolver($settings, new Iso6392LanguageCatalog());
        $literals = $resolver->languageLiterals('ka');
        $this->assertCount(1, $literals);
        $this->assertSame('ქართული', $literals[0]['@value']);
    }

    public function testEmptyMetaProducesNoLanguageLiterals(): void
    {
        $settings = $this->fakeSettings(['metadata_label_mode' => 'bilingual_display']);
        $resolver = new IaLanguageResolver($settings, new Iso6392LanguageCatalog());
        $this->assertSame([], $resolver->languageLiteralsFromMeta([]));
        $this->assertNull($resolver->resolvePrimary([]));
    }

    public function testRussianFromMarcCode(): void
    {
        $settings = $this->fakeSettings(['metadata_label_mode' => 'bilingual_display']);
        $resolver = new IaLanguageResolver($settings, new Iso6392LanguageCatalog());
        $meta = ['language' => 'rus'];
        $this->assertSame(['ru'], $resolver->resolveLanguagesFromMeta($meta));
        $values = array_column($resolver->languageLiteralsFromMeta($meta), '@value');
        $this->assertContains('russian', $values);
        $this->assertContains('რუსული', $values);
    }

    public function testGeorgianFromMarcCode(): void
    {
        $settings = $this->fakeSettings(['metadata_label_mode' => 'bilingual_display']);
        $resolver = new IaLanguageResolver($settings, new Iso6392LanguageCatalog());
        $meta = ['language' => 'geo'];
        $this->assertSame(['ka'], $resolver->resolveLanguagesFromMeta($meta));
        $values = array_column($resolver->languageLiteralsFromMeta($meta), '@value');
        $this->assertContains('georgian', $values);
        $this->assertContains('ქართული', $values);
    }

    public function testMultipleLanguagesPreserveOrder(): void
    {
        $settings = $this->fakeSettings(['metadata_label_mode' => 'bilingual_display']);
        $resolver = new IaLanguageResolver($settings, new Iso6392LanguageCatalog());
        $meta = ['language' => ['geo', 'rus']];
        $this->assertSame(['ka', 'ru'], $resolver->resolveLanguagesFromMeta($meta));
        $values = array_column($resolver->languageLiteralsFromMeta($meta), '@value');
        $this->assertSame(
            ['georgian', 'ქართული', 'russian', 'რუსული'],
            $values
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function fakeSettings(array $overrides): ModuleSettings
    {
        $store = ModuleSettings::defaultInstallSettings();
        $p = ModuleSettings::KEY_PREFIX;
        foreach ($overrides as $k => $v) {
            $store[$p . $k] = $v;
        }
        $fake = new class ($store) {
            private array $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function get($id, $default = null)
            {
                return $this->data[$id] ?? $default;
            }

            public function set($id, $value): void
            {
                $this->data[$id] = $value;
            }

            public function delete($id): void
            {
                unset($this->data[$id]);
            }
        };

        return new ModuleSettings($fake);
    }
}
