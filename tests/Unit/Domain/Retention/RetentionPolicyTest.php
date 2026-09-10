<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Retention;

use App\Domain\Retention\RetentionPolicy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RetentionPolicyTest extends TestCase
{
    #[Test]
    public function it_computes_the_deadline_as_upload_time_plus_the_default_ttl(): void
    {
        $policy = new RetentionPolicy;
        $uploadedAt = CarbonImmutable::parse('2026-01-01 00:00:00');

        $deadline = $policy->deadlineFor($uploadedAt);

        $this->assertTrue($deadline->equalTo($uploadedAt->addHours(24)));
    }

    #[Test]
    public function it_honours_a_changed_ttl_config_value(): void
    {
        config(['retention.ttl_hours' => 48]);
        $policy = new RetentionPolicy;
        $uploadedAt = CarbonImmutable::parse('2026-01-01 00:00:00');

        $deadline = $policy->deadlineFor($uploadedAt);

        $this->assertTrue($deadline->equalTo($uploadedAt->addHours(48)));
    }
}
