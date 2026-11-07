<?php

namespace Tests\Feature;

use App\Support\PublicSeo;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://billing.example.com', 'seo.indexable' => true, 'session.driver' => 'array']);
    }

    public function test_local_and_staging_stay_out_of_search(): void
    {
        foreach (['http://localhost/invoice', 'https://127.0.0.1', 'https://preview.test', 'https://preview.local'] as $url) {
            config(['app.url' => $url]);
            $this->assertFalse(PublicSeo::indexable());
            $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->get('/sitemap.xml')->assertOk()->assertDontSee('<loc>', false);
        }
        config(['app.url' => 'https://billing.example.com', 'seo.indexable' => false]);
        $this->assertFalse(PublicSeo::indexable());
    }

    public function test_public_metadata_uses_configured_domain_languages_and_clean_urls(): void
    {
        foreach (['en','gu','hi'] as $locale) {
            $r = $this->get('/?lang='.$locale.'&utm_source=test');
            $r->assertOk()->assertSee('<html lang="'.$locale.'">', false)
                ->assertSee('<link rel="canonical" href="'.PublicSeo::url('', $locale).'">', false)
                ->assertSee('hreflang="x-default"', false)->assertSee('name="twitter:card"', false)
                ->assertSee('assets/brand/favicon.svg', false);
            $this->assertFalse($r->headers->has('X-Robots-Tag'));
        }
        $this->get('/?lang=invalid')->assertOk()->assertSee('<html lang="en">', false);
        $r = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($r->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(3, $xml->url);
        $r->assertDontSee('login')->assertDontSee('invoices')->assertSee('https://billing.example.com/?lang=gu', false);
        $this->get('/robots.txt')->assertSee('Sitemap: https://billing.example.com/sitemap.xml');
        $this->get('/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('name="robots" content="noindex, nofollow"', false);
        $this->get('/register')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/invoices')->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_subdirectory_urls_and_brand_assets_are_valid(): void
    {
        config(['app.url' => 'https://example.com/invoice']);
        $this->assertSame('https://example.com/invoice/?lang=hi', PublicSeo::url('', 'hi'));
        $this->assertSame('https://example.com/invoice/sitemap.xml', PublicSeo::url('sitemap.xml'));
        foreach (['apple-touch-icon.png' => [180,180], 'social-preview.png' => [1200,630], 'favicon.ico' => [32,32]] as $file => $size) {
            $actual = getimagesize(public_path('assets/brand/'.$file));
            $this->assertSame($size, [$actual[0], $actual[1]]);
        }
        $this->assertNotFalse(simplexml_load_file(public_path('assets/brand/favicon.svg')));
    }
}
