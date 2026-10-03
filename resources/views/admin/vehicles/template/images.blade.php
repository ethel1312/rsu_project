<div class="row">
    <!-- Zona Dropzone -->
    <div class="col-12 mb-4">
        <form action="{{ route('admin.vehicles.images.upload', $vehicle->id) }}" class="dropzone border-primary text-center rounded" id="imageDropzone" style="border: 2px dashed #007bff; background: #f8f9fa;">
            @csrf
            <div class="dz-message my-4">
                <i class="bi bi-cloud-arrow-up display-4 text-primary"></i>
                <h5>Arrastra y suelta imágenes aquí</h5>
                <p class="text-muted">Archivos permitidos: JPEG, PNG, JPG (Máx. 2MB)</p>
            </div>
        </form>
    </div>

    <!-- Galería -->
    <div class="col-12">
        <h6 class="font-weight-bold mb-3 border-bottom pb-2">Imágenes Actuales</h6>
        <div class="row" id="galleryContainer">
            @forelse($vehicle->images as $image)
                <div class="col-md-3 mb-3 text-center" id="imgBox-{{ $image->id }}">
                    <div class="card h-100 {{ $image->is_profile ? 'border-primary shadow' : '' }}">
                        <img src="{{ asset('storage/' . $image->image_path) }}" class="card-img-top" style="height: 130px; object-fit: cover;" alt="Vehículo">
                        <div class="card-body p-2 d-flex justify-content-between align-items-center bg-light">
                            
                            <button class="btn btn-sm w-100 me-1 btnSetProfile {{ $image->is_profile ? 'btn-primary' : 'btn-outline-secondary' }}" data-id="{{ $image->id }}">
                                <i class="bi bi-star-fill"></i> {{ $image->is_profile ? 'Perfil' : 'Fijar' }}
                            </button>
                            
                            <button class="btn btn-sm btn-danger btnDeleteImage" data-id="{{ $image->id }}">
                                <i class="bi bi-trash"></i>
                            </button>
                            
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted">
                    <p>No hay imágenes registradas para este vehículo.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<script>
    // Evitar que Dropzone choque si se abre el modal varias veces
    Dropzone.autoDiscover = false;

    $(document).ready(function() {
        
        // Inicializar Dropzone
        var myDropzone = new Dropzone("#imageDropzone", {
            dictDefaultMessage: "Arrastra archivos aquí para subirlos",
            acceptedFiles: "image/jpeg,image/png,image/jpg",
            maxFilesize: 2, 
            success: function(file, response) {
                // Al subir correctamente, recargamos la galería dentro del modal
                $.ajax({
                    url: "{{ route('admin.vehicles.images', $vehicle->id) }}",
                    type: "GET",
                    success: function(html) {
                        $('#formModal .modal-body').html(html);
                    }
                });
            },
            error: function(file, response) {
                let msg = response.error ? response.error : "Error al subir archivo.";
                Swal.fire('Error', msg, 'error');
                this.removeFile(file);
            }
        });

        // Hacer Perfil
        $('.btnSetProfile').click(function() {
            let imageId = $(this).data('id');
            $.ajax({
                url: "{{ route('admin.vehicles.images.profile', ':id') }}".replace(':id', imageId),
                type: "POST",
                data: { _token: "{{ csrf_token() }}" },
                success: function(response) {
                    $.ajax({
                        url: "{{ route('admin.vehicles.images', $vehicle->id) }}",
                        type: "GET",
                        success: function(html) {
                            $('#formModal .modal-body').html(html);
                        }
                    });
                }
            });
        });

        // Eliminar
        $('.btnDeleteImage').click(function() {
            let imageId = $(this).data('id');
            Swal.fire({
                title: '¿Eliminar imagen?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, borrar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('admin.vehicles.images.destroy', ':id') }}".replace(':id', imageId),
                        type: "DELETE",
                        data: { _token: "{{ csrf_token() }}" },
                        success: function(response) {
                            $('#imgBox-' + imageId).fadeOut(300, function() { $(this).remove(); });
                        }
                    });
                }
            });
        });
    });
</script>