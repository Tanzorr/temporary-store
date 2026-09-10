$(function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('.delete-button').on('click', function () {
        var $button = $(this);
        var uuid = $button.data('uuid');

        if (!window.confirm('Delete this document? This cannot be undone.')) {
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: '/documents/' + uuid,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken },
        }).done(function () {
            $button.closest('tr').remove();
        }).fail(function () {
            $button.prop('disabled', false);
            window.alert('Failed to delete the document — please try again.');
        });
    });
});
