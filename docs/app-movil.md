# RSU móvil

La aplicación Android usa el correo y la contraseña del conductor registrado en Personal. Solo el perfil ciudadano permite crear una cuenta desde la app. Los ciudadanos eligen una zona registrada y pueden localizarla mediante GPS. Ambos perfiles trabajan sobre las mismas programaciones diarias del Dashboard.

## Probar en Android Studio

1. En el proyecto Laravel, configura tu MySQL habitual y ejecuta `php artisan migrate`.
2. Ejecuta `php artisan serve --host=127.0.0.1 --port=8000` en la PC.
3. Abre el proyecto hermano `rsu_app` en Android Studio y pulsa **Run**. La versión de desarrollo usa `http://10.0.2.2:8000` desde el emulador. Para un teléfono por USB puedes usar `adb reverse tcp:8000 tcp:8000` y seleccionar `http://127.0.0.1:8000` en **Configurar servidor**. Para uso remoto escribe el dominio HTTPS, sin añadir `/api`.
4. Para probar el conductor, usa una programación de hoy asignada a su ficha y marca la entrada de todo el equipo en ese turno. En el emulador, habilita la ubicación precisa y usa **Extended controls → Location** para simular movimiento. Los puntos se guardan localmente si se pierde la conexión y se sincronizan antes del cierre.
5. Para probar el ciudadano, crea su cuenta y selecciona la zona de la programación. Abre el servicio para seguirlo, y después de finalizarlo registra su valoración. El historial permite elegir el mes.

## Publicar solo la API en Railway

Crea un servicio desde el repositorio Laravel y un servicio MySQL dentro del proyecto Railway. En el servicio Laravel especifica `RAILWAY_DOCKERFILE_PATH=deploy/mobile/Dockerfile`. La imagen sirve únicamente `/api/mobile/*` y `/up`. El Dashboard puede seguir ejecutándose localmente.

Variables del servicio Laravel: `DB_CONNECTION=mysql`, `DB_HOST=${{MySQL.MYSQLHOST}}`, `DB_PORT=${{MySQL.MYSQLPORT}}`, `DB_DATABASE=${{MySQL.MYSQLDATABASE}}`, `DB_USERNAME=${{MySQL.MYSQLUSER}}`, `DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}`. Ajusta «MySQL» al nombre real del servicio. Genera una clave estable con `php artisan key:generate --show` y guárdala como `APP_KEY` en Railway. Configura `APP_URL` con el dominio HTTPS.

Configura `php artisan migrate --force` como comando previo al despliegue y `/up` como comprobación de salud. Una base nueva requiere sus zonas, turnos, personal y programaciones: las migraciones solo crean la estructura. Si la web sigue en tu PC, apunta su conexión al mismo MySQL usando las credenciales externas de Railway. La app Android solo necesita el dominio de la API.
