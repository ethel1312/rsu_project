$(function () {
    const list = $('#maintenance-list');
    const modalElement = $('#maintenance-modal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement[0]);
    const body = modalElement.find('.modal-body');
    let listRequest;
    let openingForm = false;

    function reload(url = window.location.href) {
        listRequest?.abort();
        listRequest = $.get(url).done(function (html) {
            list.html(html);
            const lastPage = list.find('[data-last-page]').data('last-page');
            if (lastPage) {
                reload(lastPage);
                return;
            }
            window.history.replaceState(null, '', url);
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                Swal.fire('Error', 'No se pudo actualizar el listado.', 'error');
            }
        });
    }

    function errorMessage(xhr, fallback) {
        return xhr.status >= 500 ? fallback : xhr.responseJSON?.message || fallback;
    }

    list.on('click', '.pagination a', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        reload(this.href);
    });

    $(document).on('click', '[data-maintenance-form]', function () {
        if (openingForm) return;
        openingForm = true;
        const button = $(this).prop('disabled', true);
        $.get(button.data('maintenance-form')).done(function (html) {
            modalElement.find('.modal-title').text(button.data('title'));
            body.html(html);
            modal.show();
        }).fail(function (xhr) {
            Swal.fire('Error', errorMessage(xhr, 'No se pudo abrir el formulario.'), 'error');
        }).always(function () {
            openingForm = false;
            button.prop('disabled', false);
        });
    });

    modalElement.on('change', '[name="start_date"]', function () {
        body.find('[name="end_date"]').attr('min', this.value);
    });

    modalElement.on('submit', '[data-maintenance-save]', function (event) {
        event.preventDefault();
        const form = $(this);
        const button = form.find('[type="submit"]');
        if (button.prop('disabled')) return;
        const errors = form.find('[data-form-errors]').empty().addClass('d-none');
        const closeButtons = modalElement.find('[data-bs-dismiss="modal"]');
        button.prop('disabled', true);
        closeButtons.prop('disabled', true);

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            headers: { Accept: 'application/json' }
        }).done(function (response) {
            modal.hide();
            reload();
            Swal.fire('Guardado', response.message, 'success');
        }).fail(function (xhr) {
            const messages = xhr.responseJSON?.errors
                ? Object.values(xhr.responseJSON.errors).flat()
                : [errorMessage(xhr, 'No se pudo guardar el registro.')];
            messages.forEach(message => $('<div>').text(message).appendTo(errors));
            errors.removeClass('d-none').attr('tabindex', '-1').trigger('focus');
        }).always(function () {
            button.prop('disabled', false);
            closeButtons.prop('disabled', false);
        });
    });

    list.on('submit', '[data-maintenance-delete]', async function (event) {
        event.preventDefault();
        const form = $(this);
        const button = form.find('[type="submit"]');
        if (button.prop('disabled')) return;
        button.prop('disabled', true);
        try {
            const result = await Swal.fire({
                title: form.data('title') + ' ¿Confirmas?',
                text: form.data('confirmation'),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                focusCancel: true
            });
            if (!result.isConfirmed) return;

            const response = await $.ajax({
                url: form.attr('action'),
                method: 'POST',
                data: form.serialize(),
                headers: { Accept: 'application/json' }
            });
            reload();
            Swal.fire('Eliminado', response.message, 'success');
        } catch (xhr) {
            Swal.fire('Error', errorMessage(xhr, 'No se pudo eliminar el registro.'), 'error');
        } finally {
            button.prop('disabled', false);
        }
    });
});
