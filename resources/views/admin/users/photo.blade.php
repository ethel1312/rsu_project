@if ($user->profile_photo_path)
    <img src="{{ Storage::disk('public')->url($user->profile_photo_path) }}" alt="Foto de {{ $user->name }}" width="40" height="40" class="rounded-circle" style="object-fit:cover">
@else
    <span class="badge bg-light text-dark p-2" aria-label="Sin foto"><i class="bi bi-person-circle" style="font-size:1.5rem"></i></span>
@endif
