# Avance 04: hasta la página 3

Implementado en el menú **Programación**:

- **Zonas → Ver mapa de zonas**: todos los perímetros registrados, filtros de ubicación, estados, leyenda y reencuadre. Los datos de coordenadas inválidos se identifican sin impedir ver las otras zonas.
- **Feriados**: listado con filtros y contadores, registro manual, edición, eliminación, activación/desactivación y carga de los feriados nacionales de Perú por año. Los nuevos feriados están activos por defecto. Repetir una carga conserva las fechas existentes y sus cambios manuales. Ver [detalles de la integración](holidays-import.md).
- **Grupos de personal**: plantillas con nombre, turno, zona, vehículo, días de la semana, conductor, ayudantes y estado. Permiten cambiar el equipo y validar roles activos, duplicados y capacidad del vehículo. Los días de los turnos nocturnos corresponden al día en que comienza el turno.

Las plantillas no reservan fechas. La generación de programaciones empieza en la página 4 del documento y queda fuera de este avance. La opción de incluir/excluir feriados, los cruces de disponibilidad y los mensajes de rangos ya programados corresponden a esa siguiente etapa.

## Actualización y datos de demostración

Desde la carpeta del proyecto:

```powershell
php artisan migrate
php artisan db:seed --class=ProgrammingDemoSeeder
```

Las migraciones de personal reconocen tablas importadas que tengan las columnas esperadas; si la estructura está incompleta, se detienen con un mensaje sin recrear la tabla. Esto permite registrar el historial pendiente y ejecutar las migraciones nuevas sin borrar datos.

El cargador agrega dos zonas referenciales de José Leonardo Ortiz (provincia de Chiclayo), tres turnos, dos vehículos ficticios, tres conductores, cinco ayudantes, sus contratos y saldos de ejemplo, y dos grupos completos. Los registros nuevos se identifican como `demo`, con correos bajo `example.test`. No reemplaza registros existentes ni restaura ediciones hechas después de la primera carga. Los perímetros de las zonas son ilustrativos, no delimitaciones municipales oficiales.

Para cargar feriados reales, abra **Programación → Feriados → Cargar feriados de Perú** y seleccione el año. El servicio requiere conexión a Internet; también se permite registrarlos manualmente. No se crean usuarios ni se ejecuta el cargador general de usuarios de prueba.

## Verificación

```powershell
php artisan test tests/Feature/Programming
php artisan migrate:status
```

Las pruebas utilizan SQLite en memoria, no la base MySQL local. Cubren CRUD, reglas del equipo y capacidad, importación repetida y fallida de feriados, estados inactivos, datos importados, seguridad del mapa y carga repetida de datos demo sin duplicados.
