<form action="{{ route('admin.maintenances.schedules.store', $maintenance) }}" method="POST" class="frmMaintenance">
    @csrf
    @include('admin.maintenances.schedules.template.form')
</form>
