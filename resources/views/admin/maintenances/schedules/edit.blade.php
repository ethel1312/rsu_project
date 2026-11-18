<form action="{{ route('admin.maintenances.schedules.update', [$maintenance, $schedule]) }}" method="POST" class="frmMaintenance">
    @csrf @method('PUT')
    @include('admin.maintenances.schedules.template.form')
</form>
