<form action="{{ route('admin.maintenances.store') }}" method="POST" class="frmMaintenance">
    @csrf
    @include('admin.maintenances.template.form')
</form>
