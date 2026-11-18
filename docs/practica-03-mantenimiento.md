# Práctica 03: mantenimiento de vehículos

## Acceso y estructura

Menú **Gestión de Vehículos → Mantenimiento** (`/admin/maintenances`). Requiere iniciar sesión con un usuario del sistema.

Se conserva AdminLTE, los colores de `public/css/custom.css`, los listados de `custom-listados.css` y los modales de `custom-modales.css`. Los registros se cargan con DataTables. Los formularios se abren con AJAX y se guardan con `FormData`, incluyendo imágenes y el token CSRF. Las eliminaciones usan AJAX y confirmación SweetAlert.

| Parte del examen | Implementación |
| --- | --- |
| Menú y períodos | `config/adminlte.php`, `MaintenanceController`, `Maintenance` |
| Horarios por vehículo y responsable | `MaintenanceScheduleController`, `MaintenanceSchedule` |
| Fechas automáticas y cruces | `app/Services/Vehicles/MaintenanceService.php` |
| Observación, imagen y estado | `MaintenanceDayController`, `MaintenanceDay` |
| Interfaz | `resources/views/admin/maintenances/` |
| Rutas | Grupo autenticado de mantenimiento en `routes/admin.php` |
| Tablas | `2026_11_17_000000_create_maintenance_tables.php` |
| Pruebas | `tests/Feature/MaintenanceTest.php` |

Relaciones: un mantenimiento tiene muchos horarios; cada horario pertenece a un vehículo y a un empleado responsable, y tiene muchos días. Los vehículos y responsables se consultan de las tablas existentes; no se duplican catálogos.

## Reglas y decisiones

- El PDF contiene una contradicción: se interpreta **inicio <= fin**, de acuerdo con el segundo enunciado y los ejemplos. Se usan las fechas ingresadas, no el mes escrito en el nombre.
- Los extremos del período son inclusivos. Dos períodos se cruzan cuando `inicio_existente <= fin_nuevo` y `fin_existente >= inicio_nuevo`. Al editar se excluye el propio registro.
- Se genera cada ocurrencia del día de semana dentro del rango, incluso si abarca varios meses. Si el rango no contiene ese día se rechaza el horario.
- Las horas pertenecen al mismo día y deben cumplir `inicio < fin`. Un cruce requiere el mismo vehículo, una fecha común y `inicio_existente < fin_nuevo` y `fin_existente > inicio_nuevo`. Por eso 08:00–11:00 y 11:00–12:00 son compatibles.
- Los tipos son Preventivo, Limpieza y Reparación. Responsable significa personal de la tabla `employees`.
- El seguimiento distingue Pendiente, Realizado y No realizado. Las imágenes admiten JPG, PNG y WebP hasta 5 MB; al reemplazar una imagen se elimina la anterior.
- Al editar un período se conservan los días coincidentes y su seguimiento. Se impide quitar fechas que ya tienen observación, imagen o estado registrado. Un horario con seguimiento no puede cambiar sus datos de programación; se puede seguir editando el detalle de cada día.
- No se permite eliminar un mantenimiento que tenga horarios. El horario sí puede eliminarse, previa confirmación explícita de que se perderán todos sus días e imágenes.
- Las escrituras del calendario se realizan en transacciones. La fila auxiliar `maintenance_calendar_locks` serializa cambios simultáneos para evitar cruces entre dos solicitudes concurrentes en MySQL.

## Recorrido para estudiar

1. Crear **MANT. DICIEMBRE 2025**, del **01/12/2025** al **31/12/2025**.
2. Abrir el botón de calendario **Ver horarios**. Crear un horario con un vehículo existente, un responsable, tipo Limpieza, lunes, de 08:00 a 11:00.
3. Abrir **Ver días generados**. Deben aparecer **01, 08, 15, 22 y 29 de diciembre de 2025**.
4. Editar un día: escribir una observación, elegir Realizado y subir una imagen. Volver a abrirlo y comprobar que conserva los datos.
5. Intentar registrar otro horario del mismo vehículo el lunes de 10:00 a 11:00: debe rechazarse. De 11:00 a 12:00, con otro vehículo o en otro día, puede registrarse.
6. Probar un período superpuesto o con fin anterior al inicio: debe rechazarse.
7. Probar la edición de un horario sin seguimiento y verificar que regenera las fechas.
8. Intentar borrar un período con horarios: debe rechazarse. Eliminar primero sus horarios mediante la confirmación y después el período.

Para explicar la generación: `dates()` encuentra la primera fecha del día elegido y avanza de siete en siete. `syncDays()` conserva las fechas coincidentes, agrega las nuevas y retira las sobrantes sin seguimiento. `saveSchedule()` valida los cruces antes de guardar.

## Instalación en otra copia

Con las dependencias y la base original del proyecto ya configuradas:

```powershell
php artisan migrate --path=database/migrations/2026_11_17_000000_create_maintenance_tables.php
php artisan storage:link
```

Si `public/storage` ya existe, no hace falta recrearlo. Esta implementación no necesita un nuevo build de Vite porque reutiliza los assets existentes.

En la copia local se aplicó únicamente esta migración. Hay migraciones anteriores pendientes de otros módulos que no forman parte de este ejercicio.

## Pruebas aisladas

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:APP_CONFIG_CACHE='bootstrap/cache/maintenance-test-no-config.php'
php vendor/phpunit/phpunit/phpunit tests/Feature/MaintenanceTest.php
```

Ejecutar en una terminal separada y cerrarla al terminar: estas variables no deben reutilizarse para iniciar la aplicación normal. La clase de pruebas comprueba que la conexión sea SQLite en memoria antes de ejecutar migraciones. No usa la base MySQL local.

La entrega en GitHub/Drive y el aula virtual indicada por el PDF es un paso de entrega académica; no se publicó ni envió el proyecto a servicios externos.
