<form action="{{ route('admin.maintenance-days.update', [$schedule, $day]) }}" method="POST" enctype="multipart/form-data" class="frmMaintenance">
    @csrf @method('PUT')
    @include('admin.maintenances.days.template.form')
</form>
