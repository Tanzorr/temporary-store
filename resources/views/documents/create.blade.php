@extends('layouts.app')

@section('title', 'Upload a Document')

@section('content')
    <h1 class="mb-4">Upload a Document</h1>

    <form id="upload-form" novalidate>
        <div id="drop-zone" class="border border-2 border-dashed rounded p-5 text-center mb-3">
            <p class="mb-2">Drag and drop a file here, or choose one below.</p>
            <input
                type="file"
                id="file-input"
                class="form-control"
                accept="{{ collect($policy->allowedExtensions)->map(fn ($extension) => ".{$extension}")->implode(',') }}"
                data-max-size-bytes="{{ $policy->maxSizeBytes }}"
                data-allowed-extensions="{{ implode(',', $policy->allowedExtensions) }}"
            >
            <p id="selected-file-name" class="mt-2 text-muted"></p>
        </div>

        <div id="progress-wrapper" class="progress mb-3" style="height: 1.5rem; display: none;">
            <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
        </div>

        <button type="submit" id="submit-button" class="btn btn-primary">Upload</button>
    </form>

    <div id="error-message" class="alert alert-danger mt-3" style="display: none;"></div>

    <div id="confirmation" class="mt-3" style="display: none;">
        <h2 class="h5">Uploaded</h2>
        <table class="table table-sm">
            <tbody>
                <tr>
                    <th scope="row">Name</th>
                    <td id="confirmation-name"></td>
                </tr>
                <tr>
                    <th scope="row">Size</th>
                    <td id="confirmation-size"></td>
                </tr>
                <tr>
                    <th scope="row">Expires</th>
                    <td id="confirmation-expires"></td>
                </tr>
            </tbody>
        </table>
        <a href="{{ route('documents.index') }}">View all documents</a>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/upload.js') }}"></script>
@endsection
