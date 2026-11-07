<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_public_landing_explains_the_workflow_in_each_supported_language(): void
    {
        $dir = sys_get_temp_dir().'/bizpilot-landing-test-views';
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }config(['view.compiled' => $dir]);
        foreach (['en' => 'Less paperwork.', 'gu' => 'ઓછું કાગળકામ.', 'hi' => 'कम कागज़ी काम।'] as $lang => $headline) {
            $r = $this->get('/?lang='.$lang)->assertOk()->assertSee($headline)->assertSee('lang="'.$lang.'"', false)->assertSee(route('login'))->assertSee('INV/001');
            $html = $r->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script\b|<style\b|\sstyle=|\son(?:click|submit)=/i', $html);
            $this->assertStringNotContainsString('Create revised plan', $html);
            $this->assertStringNotContainsString('AI assistant', $html);
            $this->assertSame(5, substr_count($html, '<details>'));
            foreach (['features', 'how-it-works', 'questions'] as $id) {
                $this->assertStringContainsString('id="'.$id.'"', $html);
            }
        }
        $this->get('/login')->assertOk();
    }
}
