<?php declare(strict_types=1);

namespace InternetArchiveInboundSyncTest;

use InternetArchiveInboundSync\Service\BilingualTextSplitter;
use InternetArchiveInboundSync\Service\IaIdentifierParser;
use PHPUnit\Framework\TestCase;

class BilingualTextSplitterTest extends TestCase
{
    public function testSplitTitleOnPipe(): void
    {
        $splitter = new BilingualTextSplitter(true);
        $raw = 'The Wound, Andro Chiaureli, 1989 | ჭრილობა, ანდრო ჭიაურელი,1989';
        $parts = $splitter->splitTitle($raw, true);
        $this->assertCount(2, $parts);
        $this->assertSame('en', $parts[0]['language']);
        $this->assertSame('ka', $parts[1]['language']);
    }

    public function testStripHtmlFromDescription(): void
    {
        $splitter = new BilingualTextSplitter(true);
        $raw = '<div><p><strong>Title</strong></p><p>First point</p></div>';
        $parts = $splitter->splitDescription($raw, false, false);
        $this->assertNotEmpty($parts);
        $combined = implode(' ', array_column($parts, 'value'));
        $this->assertStringNotContainsString('<', $combined);
        $this->assertStringContainsString('Title', $combined);
        $this->assertStringContainsString('First point', $combined);
    }

    public function testParseArchiveUrl(): void
    {
        $parser = new IaIdentifierParser();
        $this->assertSame(
            'gutenberg',
            $parser->parse('https://archive.org/details/gutenberg')
        );
    }
}
