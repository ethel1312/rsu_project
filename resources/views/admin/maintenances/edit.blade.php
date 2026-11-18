<form action="{{ route('admin.maintenances.update', $maintenance) }}" method="POST" class="frmMaintenance">
    @csrf @method('PUT')
    @include('admin.maintenances.template.form')
</form>
