<?php

namespace Tests\Unit;

use App\Services\ProspectCrawler;
use PHPUnit\Framework\TestCase;

class ProspectCrawlerTest extends TestCase
{
    public function test_extracts_only_shared_company_mailboxes(): void
    {
        $crawler = new ProspectCrawler;
        $this->assertSame(['info@firma.com', 'satis@firma.com'], $crawler->emails('<a href="mailto:INFO@firma.com">info@firma.com</a> satis&#64;firma.com ali@firma.com info@gmail.com logo@2x.png'));
        $this->assertSame(['https://firma.com/contact'], $crawler->contactLinks('<a href="/contact">Contact</a><a href="https://evil.com/contact">Contact</a>', 'https://firma.com'));
    }

    public function test_rejects_private_networks(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ProspectCrawler)->publicAddress('http://127.0.0.1/admin');
    }

    public function test_rejects_non_http_schemes(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ProspectCrawler)->publicAddress('file:///etc/passwd');
    }

    public function test_direct_site_mode_collects_published_addresses_and_limits_links(): void
    {
        $crawler = new ProspectCrawler;
        $this->assertSame(['ali@firma.com', 'firma@gmail.com'], $crawler->emails('ali@firma.com firma@gmail.com logo@2x.png', false));
        $this->assertSame(['https://firma.com/contact', 'https://firma.com/about'], $crawler->siteLinks('<a href="/about">About</a><a href="/contact#form">Contact</a><a href="https://other.test">Other</a><a href="mailto:info@firma.com">Mail</a><a href="/catalog.pdf">PDF</a><a href="/?page=2">Page</a>', 'https://firma.com'));
    }
}
