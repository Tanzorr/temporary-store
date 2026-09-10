@extends('layouts.app')

@section('title', 'Documents')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Documents</h1>
        <a href="{{ route('documents.create') }}" class="btn btn-outline-secondary">Upload another</a>
    </div>

    @if ($rows->isEmpty())
        <p class="text-muted">No documents are currently stored.</p>
    @else
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Size</th>
                    <th scope="col">Type</th>
                    <th scope="col">Uploaded</th>
                    <th scope="col">Expires</th>
                    <th scope="col">Time remaining</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr data-uuid="{{ $row->uuid }}">
                        <td>{{ $row->originalName }}</td>
                        <td>{{ $row->sizeLabel }}</td>
                        <td>{{ $row->typeLabel }}</td>
                        <td>{{ $row->uploadedAtLabel }}</td>
                        <td>{{ $row->expiresAtLabel }}</td>
                        <td>{{ $row->timeRemainingLabel }}</td>
                        <td class="text-end">
                            @if ($row->awaitingSweep)
                                <span
                                    class="btn btn-sm btn-outline-primary disabled"
                                    aria-disabled="true"
                                    title="Past its retention deadline — awaiting sweep"
                                >Download</span>
                            @else
                                <a
                                    href="{{ route('documents.download', $row->uuid) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >Download</a>
                            @endif
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger delete-button"
                                data-uuid="{{ $row->uuid }}"
                            >Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $paginator->links() }}
    @endif
@endsection

@section('scripts')
    <script src="{{ asset('js/documents.js') }}"></script>
@endsection
