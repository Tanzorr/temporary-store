<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Upload\MimeTypeDetector;
use App\Domain\Upload\UploadPolicy;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The server-side half of an UploadSession (I-1). Every rejection reason is
 * one of the fixed codes in conventions.md's Error Handling section — the
 * codes are the API contract, never a translated message.
 */
final class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            if (! $this->hasFile('file') || ! $this->file('file')->isValid()) {
                $validator->errors()->add('file', 'corrupt');

                return;
            }

            $file = $this->file('file');
            $policy = UploadPolicy::fromConfig();

            if (! $policy->allowsSize($file->getSize())) {
                $validator->errors()->add('file', 'too_large');

                return;
            }

            $mimeType = MimeTypeDetector::detect($file->getPathname());

            if ($policy->extensionForMimeType($mimeType) === null) {
                $validator->errors()->add('file', 'unsupported_type');
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(
            response()->json(['code' => $validator->errors()->first('file')], 422)
        );
    }
}
