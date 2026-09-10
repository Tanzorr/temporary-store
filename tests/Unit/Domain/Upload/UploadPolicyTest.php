<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Upload;

use App\Domain\Upload\UploadPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UploadPolicyTest extends TestCase
{
    #[Test]
    public function it_builds_from_config(): void
    {
        config([
            'uploads.max_size_bytes' => 2048,
            'uploads.allowed_mime_types' => ['application/pdf'],
            'uploads.allowed_extensions' => ['pdf'],
        ]);

        $policy = UploadPolicy::fromConfig();

        $this->assertSame(2048, $policy->maxSizeBytes);
        $this->assertSame(['application/pdf'], $policy->allowedMimeTypes);
        $this->assertSame(['pdf'], $policy->allowedExtensions);
    }

    #[Test]
    public function it_allows_a_size_at_or_under_the_maximum(): void
    {
        $policy = new UploadPolicy(1024, [], []);

        $this->assertTrue($policy->allowsSize(1024));
        $this->assertFalse($policy->allowsSize(1025));
    }

    #[Test]
    public function it_maps_a_whitelisted_mime_type_to_its_canonical_extension(): void
    {
        $policy = new UploadPolicy(
            1024,
            ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['pdf', 'docx'],
        );

        $this->assertSame('pdf', $policy->extensionForMimeType('application/pdf'));
        $this->assertSame(
            'docx',
            $policy->extensionForMimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        );
    }

    #[Test]
    public function it_returns_null_for_a_mime_type_outside_the_whitelist(): void
    {
        $policy = new UploadPolicy(1024, ['application/pdf'], ['pdf']);

        $this->assertNull($policy->extensionForMimeType('application/zip'));
    }
}
