<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Deletion\DeletionTrigger;
use App\Domain\Upload\UploadPolicy;
use App\Http\Presenters\DocumentRow;
use App\Http\Requests\UploadDocumentRequest;
use App\Models\Document;
use App\Services\DeleteDocument;
use App\Services\UploadDocument;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentController extends Controller
{
    public function create(): View
    {
        return view('documents.create', ['policy' => UploadPolicy::fromConfig()]);
    }

    public function store(UploadDocumentRequest $request, UploadDocument $uploadDocument): JsonResponse
    {
        $document = $uploadDocument->handle($request->file('file'));

        return response()->json([
            'uuid' => $document->uuid,
            'original_name' => $document->original_name,
            'size_bytes' => $document->size_bytes,
            'mime_type' => $document->mime_type,
            'uploaded_at' => $document->uploaded_at,
            'expires_at' => $document->expires_at,
        ], 201);
    }

    public function index(): View
    {
        $now = CarbonImmutable::now();

        $documents = Document::query()
            ->available()
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->paginate(25);

        $rows = $documents->getCollection()->map(
            fn (Document $document): DocumentRow => DocumentRow::from($document, $now)
        );

        return view('documents.index', ['rows' => $rows, 'paginator' => $documents]);
    }

    public function download(string $uuid): StreamedResponse
    {
        $document = Document::query()->downloadable()->where('uuid', $uuid)->firstOrFail();

        return Storage::disk($document->disk)->download(
            $document->relative_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type]
        );
    }

    public function destroy(string $uuid, DeleteDocument $deleteDocument): JsonResponse
    {
        $document = Document::query()->available()->where('uuid', $uuid)->firstOrFail();

        $deleteDocument->handle($document, DeletionTrigger::MANUAL_DELETION);

        return response()->json(['uuid' => $document->uuid]);
    }
}
