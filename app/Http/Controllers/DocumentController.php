<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadDocumentRequest;
use App\Services\UploadDocument;
use Illuminate\Http\JsonResponse;

final class DocumentController extends Controller
{
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
}
