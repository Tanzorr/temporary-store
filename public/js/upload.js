$(function () {
    var $fileInput = $('#file-input');
    var $dropZone = $('#drop-zone');
    var $selectedFileName = $('#selected-file-name');
    var $form = $('#upload-form');
    var $submitButton = $('#submit-button');
    var $progressWrapper = $('#progress-wrapper');
    var $progressBar = $('#progress-bar');
    var $errorMessage = $('#error-message');
    var $confirmation = $('#confirmation');

    var maxSizeBytes = parseInt($fileInput.data('max-size-bytes'), 10);
    var allowedExtensions = String($fileInput.data('allowed-extensions')).split(',');
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    var selectedFile = null;

    function formatSize(bytes) {
        if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }

        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function formatUtcTimestamp(isoString) {
        return isoString.substring(0, 10) + ' ' + isoString.substring(11, 16) + ' UTC';
    }

    function extensionOf(fileName) {
        var parts = fileName.split('.');

        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function setSelectedFile(file) {
        selectedFile = file;
        $selectedFileName.text(file ? file.name : '');
    }

    function hideMessages() {
        $errorMessage.hide();
        $confirmation.hide();
    }

    function showError(message) {
        $errorMessage.text(message).show();
    }

    function showConfirmation(document) {
        $('#confirmation-name').text(document.original_name);
        $('#confirmation-size').text(formatSize(document.size_bytes));
        $('#confirmation-expires').text(formatUtcTimestamp(document.expires_at));
        $confirmation.show();
    }

    function clientRejectionMessage(file) {
        if (file.size > maxSizeBytes) {
            return 'File exceeds the ' + formatSize(maxSizeBytes) + ' limit';
        }

        if (allowedExtensions.indexOf(extensionOf(file.name)) === -1) {
            return 'Only PDF and DOCX files are accepted';
        }

        return null;
    }

    function serverErrorMessage(xhr) {
        var parsed = null;

        try {
            parsed = JSON.parse(xhr.responseText);
        } catch (error) {
            parsed = null;
        }

        if (parsed === null) {
            return 'File is too large for the server to accept';
        }

        switch (parsed.code) {
            case 'too_large':
                return 'File exceeds the ' + formatSize(maxSizeBytes) + ' limit';
            case 'unsupported_type':
                return 'Only PDF and DOCX files are accepted';
            case 'corrupt':
                return 'The upload did not complete — please try again';
            default:
                return 'Upload failed — please try again';
        }
    }

    function uploadFile(file) {
        hideMessages();

        var rejection = clientRejectionMessage(file);
        if (rejection !== null) {
            showError(rejection);

            return;
        }

        var formData = new FormData();
        formData.append('file', file);

        $submitButton.prop('disabled', true);
        $progressWrapper.show();
        $progressBar.css('width', '0%');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/documents');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);

        xhr.upload.onprogress = function (event) {
            if (event.lengthComputable) {
                $progressBar.css('width', (event.loaded / event.total * 100) + '%');
            }
        };

        xhr.onload = function () {
            $submitButton.prop('disabled', false);

            if (xhr.status === 201) {
                showConfirmation(JSON.parse(xhr.responseText));

                return;
            }

            showError(serverErrorMessage(xhr));
        };

        xhr.onerror = xhr.onabort = function () {
            $submitButton.prop('disabled', false);
            showError('Upload failed — check your connection and try again');
        };

        xhr.send(formData);
    }

    $fileInput.on('change', function () {
        if (this.files && this.files.length) {
            setSelectedFile(this.files[0]);
        }
    });

    $dropZone.on('dragover', function (event) {
        event.preventDefault();
    });

    $dropZone.on('drop', function (event) {
        event.preventDefault();

        var files = event.originalEvent.dataTransfer.files;
        if (files && files.length) {
            setSelectedFile(files[0]);
        }
    });

    $form.on('submit', function (event) {
        event.preventDefault();

        if (selectedFile !== null) {
            uploadFile(selectedFile);
        }
    });
});
