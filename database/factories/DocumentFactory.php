<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Document\DocumentStatus;
use App\Domain\Retention\RetentionPolicy;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
final class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uploadedAt = CarbonImmutable::now();
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'original_name' => $this->faker->word().'.pdf',
            'stored_name' => Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => $this->faker->numberBetween(1024, 5_000_000),
            'checksum_sha256' => hash('sha256', $uuid),
            'disk' => 'local',
            'relative_path' => 'documents/'.$uuid.'.pdf',
            'status' => DocumentStatus::Available,
            'uploaded_at' => $uploadedAt,
            'expires_at' => app(RetentionPolicy::class)->deadlineFor($uploadedAt),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => CarbonImmutable::now()->subHour(),
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Deleted,
        ]);
    }
}
