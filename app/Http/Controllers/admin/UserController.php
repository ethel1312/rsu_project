<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::of(User::query()->select([
                'id', 'name', 'last_name', 'dni', 'email', 'phone', 'user_type',
                'is_active', 'is_admin', 'profile_photo_path', 'updated_at',
            ]))
                ->addColumn('photo', fn ($user) => view('admin.users.photo', compact('user'))->render())
                ->editColumn('is_active', fn ($user) => $user->is_active ? 'Activo' : 'Inactivo')
                ->editColumn('updated_at', fn ($user) => $user->updated_at?->timezone('America/Lima')->format('d/m/Y H:i'))
                ->addColumn('actions', fn ($user) => view('admin.users.actions', compact('user'))->render())
                ->rawColumns(['photo', 'actions'])->make(true);
        }
        return view('admin.users.index');
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['name' => '']), 'types' => $this->types()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->persist(new User, $data, $request);
        return response()->json(['message' => 'Usuario creado. Asigne sus permisos para habilitar el acceso a los módulos.'], 201);
    }

    public function edit(Request $request, User $user)
    {
        $this->protectAdministrator($request, $user);
        return view('admin.users.form', ['user' => $user, 'types' => $this->types()]);
    }

    public function update(Request $request, User $user)
    {
        $this->protectAdministrator($request, $user);
        $data = $this->validated($request, $user);
        DB::transaction(function () use ($request, $user, $data) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->protectAdministrator($request, $locked);
            if (! $data['is_active']) $this->assertCanDisable($request, $locked);
            $this->persist($locked, $data, $request);
            if (! $data['is_active'] || ! empty($data['password'])) $this->revokeSessions($locked);
        });
        return response()->json(['message' => 'Usuario actualizado correctamente.']);
    }

    public function destroy(Request $request, User $user)
    {
        $photo = $user->profile_photo_path;
        DB::transaction(function () use ($request, $user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->protectAdministrator($request, $locked);
            $this->assertCanDisable($request, $locked);
            $this->revokeSessions($locked);
            $locked->delete();
        });
        if ($photo) Storage::disk('public')->delete($photo);
        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    public function permissions(Request $request, User $user)
    {
        $this->protectAdministrator($request, $user);
        return view('admin.users.permissions', ['user' => $user, 'sections' => UserPermissions::catalog()]);
    }

    public function updatePermissions(Request $request, User $user)
    {
        $this->protectAdministrator($request, $user);
        abort_if($user->is_admin, 422, 'El administrador principal conserva todos los permisos.');
        $data = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(UserPermissions::keys())],
        ]);
        $user->forceFill(['permissions' => array_values($data['permissions'] ?? [])])->save();
        return response()->json(['message' => 'Permisos actualizados. Se aplicarán desde la siguiente solicitud del usuario.']);
    }

    private function types(): array
    {
        return ['Administrador', 'Supervisor', 'Operador'];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:150'],
            'dni' => ['required', 'regex:/^\d{8}$/', Rule::unique('users')->ignore($user)],
            'birth_date' => ['required', 'date', 'before:today'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() \-]+$/'],
            'license' => ['nullable', 'string', 'max:50'],
            'user_type' => ['required', Rule::in($this->types())],
            'address' => ['required', 'string', 'max:1000'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8), 'max:255'],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
        ], [
            'dni.regex' => 'El DNI debe contener exactamente 8 dígitos.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);
    }

    private function persist(User $user, array $data, Request $request): void
    {
        $oldPhoto = $user->profile_photo_path;
        $newPhoto = null;
        unset($data['photo'], $data['remove_photo']);
        if (empty($data['password'])) unset($data['password']);
        if (isset($data['password'])) $data['remember_token'] = Str::random(60);
        if ($user->exists && $user->email !== $data['email']) $data['email_verified_at'] = null;
        try {
            if ($request->hasFile('photo')) {
                $newPhoto = $request->file('photo')->store('profile-photos', 'public');
                $data['profile_photo_path'] = $newPhoto;
            } elseif ($request->boolean('remove_photo')) {
                $data['profile_photo_path'] = null;
            }
            // Only the validated profile fields are writable; privileges use their own endpoint.
            $user->forceFill($data)->save();
        } catch (\Throwable $error) {
            if ($newPhoto) Storage::disk('public')->delete($newPhoto);
            throw $error;
        }
        if ($oldPhoto && ($newPhoto || $request->boolean('remove_photo'))) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($oldPhoto));
        }
    }

    private function protectAdministrator(Request $request, User $user): void
    {
        abort_if($user->is_admin && ! $request->user()->is_admin, 403, 'Solo un administrador principal puede modificar esta cuenta.');
    }

    private function assertCanDisable(Request $request, User $user): void
    {
        abort_if($request->user()->is($user), 409, 'No puede desactivar ni eliminar su propia cuenta.');
        if ($user->is_admin) {
            $admins = User::where('is_admin', true)->where('is_active', true)->lockForUpdate()->get();
            abort_if($admins->where('id', '!=', $user->id)->isEmpty(), 409, 'Debe conservar al menos un administrador activo.');
        }
    }

    private function revokeSessions(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        if (Schema::hasTable('sessions')) DB::table('sessions')->where('user_id', $user->id)->delete();
        if (Schema::hasTable('personal_access_tokens')) $user->tokens()->delete();
    }
}
