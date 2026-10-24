<form action="{{ route('admin.programming.personnel-groups.update', $group) }}" method="POST" id="frmPersonnelGroup">
    @csrf
    @method('PUT')
    @include('admin.programming.personnel-groups.template.form')
</form>
