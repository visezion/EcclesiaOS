<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_error_pages_render_brand_safe_messages(): void
    {
        $this->assertStringContainsString('Page not found', view('errors.404')->render());
        $this->assertStringContainsString('Access restricted', view('errors.403')->render());
        $this->assertStringContainsString('Something went wrong', view('errors.500')->render());
    }
}
