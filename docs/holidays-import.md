# Carga de feriados de Perú

El módulo consulta la [Community API v4 de Nager.Holidays](https://nagerholidays.com/api) mediante `GET /api/v4/Holidays/PE/{año}`. El año actual de Lima aparece seleccionado y se permite consultar hasta cinco años adelante, según el alcance del proveedor. La URL puede configurarse con `HOLIDAYS_API_URL`.

La respuesta debe ser una lista no vacía con fechas válidas del año solicitado, país PE, nombre, indicador nacional y tipos de feriado reconocidos. Se importan únicamente feriados nacionales de tipo Public. Los días regionales y no laborables compensables requieren registro manual; no se incluyen automáticamente.

Se aplican dos correcciones explícitas a los datos del proveedor, contrastadas con la [Ley 31788 publicada en El Peruano](https://busquedas.elperuano.pe/dispositivo/NL/2187453-1) y el [calendario 2026 publicado por PROMPERÚ](https://peru.info/es-pe/turismo/blogperu/3/48/peru-y-sus-feriados-2026-el-calendario-para-celebrar-lo-que-somos): se incluye el 7 de junio desde 2024 y se excluye el Domingo de Pascua. La respuesta real consultada al desarrollar esta integración omitía la primera fecha y marcaba la segunda como feriado público. La corrección no calcula ni inventa feriados móviles. Los nombres conocidos se muestran en español; se conserva `localName` si la API lo ofrece.

La carga valida toda la respuesta antes de escribir y guarda en una transacción. Una fecha ya registrada se conserva con su descripción y estado, por lo que repetir la carga no duplica ni reactiva feriados. Los cambios normativos futuros y feriados locales pueden incorporarse manualmente; si cambia la información del proveedor, debe revisarse esta reconciliación.

El tiempo máximo de consulta es de 15 segundos, con hasta 5 segundos para conectar. Ante errores de red, respuesta inválida o indisponibilidad, la interfaz presenta un mensaje y ofrece el registro manual.

Pruebas del módulo: `php vendor/phpunit/phpunit/phpunit --filter=HolidayTest`. Usan SQLite en memoria y solicitudes simuladas; no modifican la base MySQL.
