# Asistencias

El módulo se encuentra en `Employees` (modelo, controladores y servicio) y en `admin/employees/attendances` (vistas), siguiendo la organización del proyecto.

## Acceso

- Administración: `/admin/attendances`, desde Gestión de Personal → Asistencias. Requiere la sesión del sistema.
- Marcación del personal: `/employees/attendances/clock`, también accesible desde el botón “Marcación del Personal”. Solo pide DNI y la contraseña registrada en Personal; no inicia sesión administrativa.

## Funcionamiento

- El listado carga la fecha actual de Lima. Permite seleccionar otra fecha y usar Anterior, Hoy y Siguiente. El buscador permite localizar DNI, nombres, apellidos y notas.
- El administrador registra y corrige personal, fecha, hora, estado (presente/ausente) y notas mediante modales. También puede eliminar una marcación errónea.
- El selector de personal busca por DNI, nombres y apellidos desde 2 caracteres. Consulta al servidor en grupos de 20 resultados y carga más al desplazarse; al editar, carga inicialmente solo el personal seleccionado.
- Los registros presentes se ordenan por hora, por persona y por día: ingreso, salida, ingreso, salida. Se recalculan al registrar, modificar o eliminar; al cambiar de persona o fecha, se corrigen ambos grupos.
- Las ausencias muestran “No aplica” en el tipo y no alteran la alternancia.
- No se aceptan dos registros del mismo personal con idéntica fecha y hora. El servidor calcula el tipo incluso si se manipula el formulario.
- La pantalla independiente toma la hora y fecha del servidor en `America/Lima`, registra estado presente y rechaza credenciales incorrectas o personal inactivo. Limita los intentos y evita el doble envío desde el formulario.
- La marcación acepta las contraseñas ya registradas por el módulo de Personal. Si una contraseña estaba guardada sin hash, se convierte a hash después de una marcación correcta, conservando la misma contraseña para el usuario.
- La asignación automática de turno y las reglas de programación quedan pendientes del módulo de Programación, según el alcance solicitado.

## Base de datos

Con las tablas de Personal disponibles, ejecutar únicamente:

```sh
php artisan migrate --path=database/migrations/2026_10_06_172605_create_attendances_table.php
```

La migración crea `attendances` si no existe y admite la tabla compatible ya importada desde SQL, sin borrar sus filas. En la base local esta migración ya fue aplicada.

El repositorio trae un problema previo ajeno a asistencias: la creación de `employee_types` ya incluye `is_default` y otra migración vuelve a añadirla. Además, en la base importada algunas tablas de Personal existen aunque sus migraciones figuren pendientes. Por ello, un `php artisan migrate` general puede fallar; estos archivos de Personal se dejaron intactos para respetar el trabajo del grupo. No es necesario usar `migrate:fresh` ni borrar datos para instalar asistencias.

## Verificación

```sh
php artisan test --compact tests/Feature/Employees/AttendanceTest.php
npm run build
```

Las pruebas usan SQLite en memoria y las migraciones necesarias para este módulo, omitiendo la migración duplicada de Personal. Cubren acceso, fechas de Lima, listado, formularios, búsqueda, alternancia, correcciones, ausencias, duplicados, validación, marcación y limitación de intentos.
