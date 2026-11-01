# Avance 07: Gestión de Usuarios

## Activación

Ejecutar desde el proyecto:

```bash
php artisan migrate
php artisan storage:link
```

La migración conserva las cuentas existentes y designa como administrador principal al usuario con menor ID. Las demás cuentas mantienen su contraseña y quedan activas, sin permisos asignados. Si la base estaba vacía, crear una cuenta y designarla explícitamente desde la consola:

```bash
php artisan users:make-admin correo@ejemplo.com
```

El enlace de almacenamiento permite mostrar las fotos guardadas en `storage/app/public`. Si ya existe, no es necesario volver a crearlo.

## Uso

En Gestión de Usuarios se pueden crear, editar, desactivar y eliminar cuentas. El botón de llave abre la asignación de permisos, con selección global y por sección. El tipo de usuario describe su cargo; no concede privilegios automáticamente.

El administrador principal conserva todos los permisos. Las cuentas nuevas, incluidas las registradas desde el formulario público, no tienen acceso a módulos hasta recibir permisos. No se puede eliminar o desactivar la propia cuenta desde este módulo ni dejar el sistema sin un administrador activo.

Las cuentas desactivadas no pueden iniciar sesión. La desactivación, eliminación o cambio de contraseña administrativo revoca las sesiones y tokens de la cuenta. Los permisos se vuelven a consultar en cada petición.

## Alcance

Los permisos cubren las rutas administrativas actuales, incluidos imágenes de vehículos, mapas, operaciones del dashboard y cambios de programación. Mantenimientos y Ubigeo aparecen deshabilitados porque sus módulos todavía no existen. No se han creado esos módulos como parte de este avance.

El catálogo está en `config/user_permissions.php`; la correspondencia entre rutas y permisos, en `app/Support/UserPermissions.php`. Al agregar una ruta administrativa se debe definir su permiso: las rutas no reconocidas se rechazan por defecto.

## Verificación

```bash
php artisan test --do-not-cache-result tests/Feature/UserManagementTest.php
```

Las pruebas usan SQLite en memoria y no modifican la base configurada para el uso diario.
