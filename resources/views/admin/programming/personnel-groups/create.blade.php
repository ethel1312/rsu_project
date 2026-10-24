<form action="{{ route('admin.programming.personnel-groups.store') }}" method="POST" id="frmPersonnelGroup">
    @csrf
    @include('admin.programming.personnel-groups.template.form')
</form>
