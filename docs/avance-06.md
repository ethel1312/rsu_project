# Avance 06: programación y gestión de cambios

## Acceso

- Inicio de administración y **Programación > Dashboard diario**: asistencia, recursos y cambios de una fecha, con filtro por turno.
- **Gestión de Cambios > Motivos**: crear, editar y eliminar motivos del catálogo.
- **Gestión de Cambios > Cambios**: filtrar por fecha de registro y tipo, consultar detalles, aplicar un cambio masivo y revertirlo.
- **Programación > Programaciones > Recorrido**: iniciar, finalizar y consultar el trazado del día seleccionado.

## Requisitos implementados

| Requisito | Comportamiento |
| --- | --- |
| Dashboard del PDF | Totales, equipos completos e incompletos según asistencia de entrada, personal faltante, filtros de fecha/turno y tarjetas por programación/zona. |
| Detalles del dashboard | Personal presente/faltante, cambios de turno/vehículo y reemplazos del mismo rol que asistieron y están disponibles. |
| Restricciones del profesor | Solo hoy desde el dashboard; no se reemplaza a personal que asistió; no se cambia un recorrido iniciado ni uno cuyo horario de inicio ya pasó. Todas las reglas se verifican en el servidor. |
| Disponibilidad | Rol, personal activo, capacidad del vehículo, zona activa, vacaciones aprobadas, ausencias y cruces de recursos; incluye turnos nocturnos y días adyacentes. |
| Individual y masiva | Comparten disponibilidad y validación de personal; ambas ofrecen la misma selección de feriados que se omiten. Las solicitudes pendientes de vacaciones siguen siendo advertencias. |
| Motivos | Nombre y descripción obligatorios; catálogo compartido por cambios individuales, masivos y del dashboard. Eliminar un motivo lo retira de las opciones y conserva el motivo histórico. |
| Cambios masivos | Rango de fechas, zona opcional, tipo, valor anterior/nuevo, motivo y detalle; vista previa con cada fecha afectada y confirmación. Si alguna fecha falla, no se aplica ninguna. |
| Historial | Cada cambio conserva programación, fecha de servicio, usuario, motivo, origen, valores anteriores/nuevos y operación masiva; además inserta en el historial de la programación. |
| Reversión | “Eliminar y revertir” restaura los campos originales y registra la reversión. No borra la auditoría. Rechaza recorridos iniciados, conflictos de disponibilidad y cambios posteriores del mismo tipo. |
| Recorridos | Inicio y finalización independientes por fecha. Finalizar no afecta los demás días del periodo. Botón “Ver recorrido” después de finalizar. |

La instrucción adicional del profesor sobre revertir cambios prevalece sobre la frase “irreversible” del PDF. La confirmación masiva se conserva y explica el alcance real de la operación.

**Completa** en el dashboard significa que asistió todo el equipo, como en la referencia del PDF. El estado **sin iniciar / en curso / finalizado** del recorrido se muestra por separado.

## Demostración sugerida

1. Registrar o editar un motivo en Gestión de Cambios.
2. Crear una programación para hoy con un turno que todavía no comience. Verificar la disponibilidad antes de guardar.
3. Registrar la entrada de un reemplazo; dejar sin asistencia a un integrante asignado.
4. Abrir Dashboard diario > Ver detalles. El personal presente no ofrece reemplazo; el faltante ofrece candidatos que sí asistieron, del mismo rol y sin asignación en ese día/turno.
5. Guardar el reemplazo con motivo. Revisar el cambio en el dashboard, en Gestión de Cambios y en el historial de la programación de ese día.
6. Crear un cambio masivo para días futuros. Revisar valores y fechas en la vista previa y confirmar. Abrir el detalle de cada cambio.
7. Eliminar y revertir uno de los cambios. Verificar el valor restaurado y la auditoría de reversión. Si existen cambios posteriores del mismo tipo, revertirlos en orden inverso.
8. Cuando todos los integrantes hayan marcado entrada, iniciar el recorrido. Registrar en el mapa los puntos seguidos y finalizar. Consultar “Ver recorrido”. Comprobar que los demás días siguen programados.

## Recorrido registrado

El proyecto no tenía una fuente de GPS. El trazado implementado es **declarado manualmente** sobre el mapa (o mediante pares de coordenadas JSON), se valida y queda guardado al finalizar. No se presenta el perímetro de la zona como si fuera el recorrido real. Leaflet y las teselas de OpenStreetMap requieren conexión; el formulario de coordenadas permite registrar y consultar el trazado cuando el mapa no carga.

## Organización técnica

- `ScheduleChangeService`: reglas compartidas, aislamiento de una fecha, aplicación de cambios, reversión e historial.
- `ScheduleAvailabilityService`: comprobaciones de disponibilidad reutilizadas; considera las fechas excluidas de un periodo y los cruces con sus días adyacentes.
- `ProgrammingSchedule::runsOn`: única comprobación de pertenencia de una fecha al periodo.
- `ScheduleChangeController`: listado, detalle, vista previa y confirmación masiva transaccional.
- `ScheduleDashboardController`: consulta diaria, reemplazos y recorridos.
- `ChangeReasonController`: CRUD de motivos.
- Tablas nuevas: `change_reasons`, `schedule_changes` y `schedule_runs`. Se conserva `programming_schedule_histories`.
- Un día modificado se separa en una programación hija y se excluye del periodo padre, sin alterar los demás días.
- Las operaciones de asignación, cambios, reversión e inicio comparten bloqueos de recursos dentro de transacciones.

## Instalación en otra copia

```text
php artisan migrate
php artisan db:seed --class=ChangeReasonSeeder
npm run build
```

En la base local de este trabajo las migraciones y el catálogo inicial ya se aplicaron. El seeder de motivos es idempotente y no sobrescribe motivos editados ni recupera motivos eliminados.

## Verificación

```text
php vendor/phpunit/phpunit/phpunit tests/Feature/Programming/ScheduleOperationsTest.php
php vendor/phpunit/phpunit/phpunit
php artisan view:cache
npm run build
```

- Avance 06: **19 pruebas y 118 aserciones correctas**.
- Suite completa: **108 pruebas, 644 aserciones, 2 fallos previos y 7 omitidas**. Los dos fallos también se observaron antes de añadir las pruebas del avance 06, sin cambios en sus archivos de implementación:
  - `PersonnelGroupTest::test_creates_an_active_reusable_template_with_sorted_days_and_helpers`: espera reutilizar el mismo personal en otro grupo, pero el controlador existente lo rechaza por disponibilidad del grupo.
  - `ProgrammingDemoSeederTest::test_demo_data_can_be_loaded_twice_without_duplicates_or_overwriting_existing_changes`: espera 8 empleados, pero el seeder existente genera 15 (también amplió turnos y vehículos).
- Compilación de Blade y build de Vite correctos.
- Dashboard y formulario masivo revisados visualmente con HTML generado por Laravel y datos ficticios aislados.
- JavaScript de las nuevas pantallas renderizadas comprobado con `node --check`.
