<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UploadPageTest extends TestCase
{
    #[Test]
    public function it_renders_the_upload_form_with_the_published_policy(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('documents.create');
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee(
            'data-max-size-bytes="'.config('uploads.max_size_bytes').'"',
            false
        );
        $response->assertSee(
            'data-allowed-extensions="'.implode(',', config('uploads.allowed_extensions')).'"',
            false
        );
    }
}
