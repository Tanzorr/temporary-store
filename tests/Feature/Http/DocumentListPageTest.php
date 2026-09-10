<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DocumentListPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_available_documents_with_their_display_values(): void
    {
        $now = CarbonImmutable::parse('2026-09-10 12:00:00');
        $this->travelTo($now);

        $document = Document::factory()->create([
            'original_name' => '<img src=x onerror=alert(1)>.pdf',
            'extension' => 'pdf',
            'size_bytes' => 2048,
            'uploaded_at' => CarbonImmutable::parse('2026-09-09 12:00:00'),
            'expires_at' => $now->addHour(),
        ]);

        $response = $this->get('/documents');

        $response->assertOk();
        $response->assertSee('2.0 KB');
        $response->assertSee('PDF');
        $response->assertSee('2026-09-09 12:00 UTC');
        $response->assertSee('2026-09-10 13:00 UTC');
        $response->assertSee('1h 0m');

        // I-7: the filename is escaped, never injected raw.
        $response->assertDontSee($document->original_name, false);
        $response->assertSee('&lt;img src=x onerror=alert(1)&gt;.pdf', false);
    }

    #[Test]
    public function it_excludes_deleted_documents(): void
    {
        Document::factory()->create(['original_name' => 'keep.pdf']);
        Document::factory()->deleted()->create(['original_name' => 'gone.pdf']);

        $response = $this->get('/documents');

        $response->assertSee('keep.pdf');
        $response->assertDontSee('gone.pdf');
    }

    #[Test]
    public function it_paginates_at_twenty_five_per_page_ordered_newest_first_with_an_id_tiebreak(): void
    {
        $sameSecond = CarbonImmutable::parse('2026-09-10 00:00:00');

        foreach (range(0, 25) as $index) {
            Document::factory()->create([
                'original_name' => "doc-{$index}.pdf",
                'uploaded_at' => $sameSecond,
            ]);
        }

        $firstPage = $this->get('/documents');
        $firstPage->assertOk();
        $firstPageNames = $firstPage->viewData('rows')->map->originalName->all();

        $this->assertCount(25, $firstPageNames);
        $this->assertSame('doc-25.pdf', $firstPageNames[0]);
        $this->assertSame('doc-1.pdf', $firstPageNames[24]);

        $secondPage = $this->get('/documents?page=2');
        $secondPageNames = $secondPage->viewData('rows')->map->originalName->all();

        $this->assertSame(['doc-0.pdf'], $secondPageNames);
    }

    #[Test]
    public function it_labels_a_document_past_its_deadline_as_awaiting_sweep(): void
    {
        Document::factory()->expired()->create(['original_name' => 'overdue.pdf']);

        $response = $this->get('/documents');

        $response->assertSee('overdue.pdf');
        $response->assertSee('awaiting sweep');
    }
}
